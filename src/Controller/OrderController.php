<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Event;
use App\Entity\Order;
use App\Entity\OrderAddress;
use App\Entity\Product;
use App\Entity\Ticket;
use App\Entity\User;
use App\Form\Type\AddressFormType;
use App\Helpers\Constants;
use App\Message\Tickets;
use App\Repository\AddressRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\EventService;
use App\Service\GlobalService;
use App\Service\MailerService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    private const STRIPE_API_KEY = 'stripe_api_key';
    private const FREE_MODE = 'free_mode';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly AddressRepository $addressRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserService $userService,
        private readonly GlobalService $globalService,
        private readonly EventService $eventService,
        private readonly MailerService $mailerService
    ) {
    }

    #[Route('/{slug}', name: '_recap')]
    public function index(Product $product): Response
    {
        return $this->render('order/index.html.twig', [
            'product' => $product
        ]);
    }

    #[Route('/{slug}/address', name: '_address', methods: ['POST'])]
    public function address(Request $request, Product $product, #[CurrentUser] ?User $user): Response
    {
        $userAddress = !is_null($user) ? $this->addressRepository->findOneBy(['email' => $user->getEmail()]) : null;
        $formQuantity = $request->request->get('formQuantity');
        $orderTotal = ($product->getPrice() * $formQuantity) / 100;
        $orderTotal = number_format($orderTotal, 2);

        $form = $this->createForm(AddressFormType::class);

        return $this->render('order/address.html.twig', [
            'product' => $product,
            'orderQuantity' => $formQuantity,
            'orderTotal' => $orderTotal,
            'form' => $form,
            'user' => $user,
            'userAddress' => $userAddress
        ]);
    }

    #[Route('/{slug}/confirm', name: '_confirm', methods: ['POST'])]
    public function confirm(Request $request, Product $product): Response
    {
        $datas = $request->request->all();
        $orderTotal = ($product->getPrice() * $datas['quantity']) / 100;
        $orderTotal = number_format($orderTotal, 2);

        return $this->render('order/confirm.html.twig', [
            'product' => $product,
            'orderQuantity' => $datas['quantity'],
            'orderTotal' => $orderTotal,
            'datas' => $datas['address_form']
        ]);
    }

    #[Route('/{slug}/payment', name: '_payment')]
    public function payment(Request $request, Product $product): Response
    {
        $datas = json_decode($request->request->get('datas'));
        $orderQuantity = intval($request->request->get('quantity'));
        $orderTotal = ($product->getPrice() * $orderQuantity);

        /* FREE ORDER */
        if ($this->getParameter(self::FREE_MODE)) {
            return $this->redirectToRoute('app_order_free_order', [
                'slug' => $product->getSlug(), 
                'datas' => $datas,
                'quantity' => $orderQuantity
            ]);
        }

        if ($orderQuantity > 0) {
            $stripe = new \Stripe\StripeClient($this->getParameter(self::STRIPE_API_KEY));
            $customer = $stripe->customers->create([
                'email' => $datas->email,
            ]);

            $parameters = [
                'customer' => $customer,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $product->getName(),
                        ],
                        'unit_amount' => $product->getPrice(),
                        'product_data' => [
                            'name' => $product->getName(),
                            'images' => ["https://tiiix.fr/logos/tiiix.png"],
                        ],
                    ],
                    'quantity' => $orderQuantity,
                ]],
                'mode' => 'payment',
                'success_url' => $this->generateUrl('app_order_payment_success', [
                    'slug' => $product->getSlug(), 
                    'datas' => $datas,
                    'quantity' => $orderQuantity
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('app_order_payment_error', [
                    'slug' => $product->getSlug()
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];

            $session = $stripe->checkout->sessions->create($parameters);

            $request->getSession()->set('stripe_session_id', $session->id);

            return $this->redirect($session->url, Response::HTTP_SEE_OTHER);
        } else {
            $this->addFlash('warning', 'La quantitée séléctionnée est nulle.');

            return $this->render('order/confirm.html.twig', [
                'product' => $product,
                'orderQuantity' => $datas['quantity'],
                'orderTotal' => $orderTotal,
                'datas' => $datas['address_form']
            ]);
        }
    }

    #[Route('/{slug}/payment/success', name: '_payment_success')]
    public function paymentSuccess(Request $request, Product $product, MessageBusInterface $bus, #[CurrentUser] ?User $user): Response
    {
        $stripeSessionId = $request->getSession()->get('stripe_session_id');

        if (!isset($stripeSessionId)) {
            return $this->redirectToRoute('app_home');
        }

        $stripe = new \Stripe\StripeClient($this->getParameter(self::STRIPE_API_KEY));
        $stripeSession = $stripe->checkout->sessions->retrieve($stripeSessionId, []);

        $datas = $request->query->all('datas');
        $orderQuantity = intval($request->query->get('quantity'));
        $event = null;

        if (Constants::STRIPE_PAYMENT_STATUS_PAID == $stripeSession->payment_status) {
            $this->userService->createOrUpdateAddress($user, $datas);

            $event = $this->createEvent($datas);

            $order = $this->createOrder($product, $event, $orderQuantity, $datas);

            $this->createOrderAddress($order);

            $this->createTickets($order, $product, $orderQuantity, $event);

            $bus->dispatch(new Tickets($event->getId()));

            $this->mailerService->sendBrevoEmail(
                $user ? $user->getEmail() : $order->getEmail(),
                Constants::ORDER_CONFIRMATION_EMAIL_TEMPLATE,
                [
                    'FIRSTNAME' => $user ? $user->getFirstname() : '',
                    'NUMBER' => strtoupper($order->getNumber()),
                    'PRODUCT' => $order->getProduct()->getName(),
                    'QUANTITY' => $order->getQuantity(),
                    'TOTAL' => $order->getTotal() / 100,
                ]
            );
        }

        return $this->render('order/success.html.twig', [
            'product' => $product,
            'datas' => $datas,
            'orderNumber' => $order->getNumber()
        ]);
    }

    #[Route('/{slug}/payment/free', name: '_free_order')]
    public function freeOrder(Request $request, Product $product, MessageBusInterface $bus, #[CurrentUser] ?User $user): Response
    {
        $datas = $request->query->all('datas');
        $orderQuantity = intval($request->query->get('quantity'));
        $event = null;

        $this->userService->createOrUpdateAddress($user, $datas);

        $event = $this->createEvent($datas);

        $order = $this->createOrder($product, $event, $orderQuantity, $datas);

        $this->createOrderAddress($order);

        $this->createTickets($order, $product, $orderQuantity, $event);

        $bus->dispatch(new Tickets($event->getId()));

        $this->mailerService->sendBrevoEmail(
            $user ? $user->getEmail() : $order->getEmail(),
            Constants::ORDER_CONFIRMATION_EMAIL_TEMPLATE,
            [
                'FIRSTNAME' => $user ? $user->getFirstname() : '',
                'NUMBER' => strtoupper($order->getNumber()),
                'PRODUCT' => $order->getProduct()->getName(),
                'QUANTITY' => $order->getQuantity(),
                'TOTAL' => 0.00, /* FREE ORDER */
            ]
        );

        return $this->render('order/success.html.twig', [
            'product' => $product,
            'datas' => $datas,
            'orderNumber' => $order->getNumber()
        ]);
    }

    #[Route('/{slug}/payment/error', name: '_payment_error')]
    public function paymentError(Request $request, Product $product): Response
    {
        $stripeSessionId = $request->getSession()->get('stripe_session_id');

        if (!isset($stripeSessionId)) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('order/error.html.twig', [
            'product' => $product
        ]);
    }

    private function createEvent(array $datas): Event
    {
        $event = new Event();

        $event->setEmail($datas['email']);
        $event->setName($datas['eventName']);
        $event->setStartDate(new DateTime($datas['eventDate']));
        $this->globalService->persistAndFlush($event);

        return $event;
    }

    private function createOrder(Product $product, ?Event $event, int $quantity, array $datas): Order
    {
        $order = new Order();

        /* FREE ORDER */
        $orderTotal = $this->getParameter(self::FREE_MODE) ? 000 : $product->getPrice() * $quantity;

        $order->setNumber(uniqid());
        $order->setProduct($product);
        $order->setEmail($datas['email']);
        $order->setEvent($event);
        $order->setQuantity($quantity);
        $order->setTotal($orderTotal);
        $order->setState(Constants::ORDER_STATE_PAID);
        $this->globalService->persistAndFlush($order);

        return $order;
    }

    private function createOrderAddress(Order $order): void
    {
        $orderAddress = new OrderAddress();

        /** @var Address $userAddress */
        $userAddress = $this->addressRepository->findOneBy(['email' => $order->getEmail()]);

        $orderAddress->setBill($order);
        $orderAddress->setStreet($userAddress->getStreet());
        $orderAddress->setPostcode($userAddress->getPostcode());
        $orderAddress->setCity($userAddress->getCity());
        $orderAddress->setCountry($userAddress->getCountry());
        $orderAddress->setPhoneNumber($userAddress->getPhoneNumber());
        $orderAddress->setEmail($userAddress->getEmail());

        $this->globalService->persistAndFlush($orderAddress);

        return;
    }

    private function createTickets(Order $order, Product $product, int $quantity, ?Event $event = null): void
    {
        $totalTickets = $product->getQuantity() * $quantity;

        for ($i = 0; $i < $totalTickets; $i++) {
            $ticket = new Ticket();
            $number = strtoupper(uniqId(rand()));

            if ($product->hasETickets()) {
                $qrCodeName = $this->createQrCode($number);
                $ticket->setQrCode($qrCodeName);
            }

            $ticket->setBill($order);
            $ticket->setEvent($event);
            $ticket->setNumber($number);
            $ticket->setState(Constants::TICKET_STATE_ACTIVE);

            $event->addTicket($ticket);

            $this->entityManager->persist($ticket);
        }

        $this->entityManager->flush();

        return;
    }

    private function createQrCode(string $number): string
    {
        $writer = new PngWriter();

        $qrCode = new QrCode('https://www.tiiix.fr/app/qr/' . $number);

        $qrCodeName = $number . '.png';

        $result = $writer->write($qrCode);

        $result->saveToFile($this->getParameter('kernel.project_dir').Constants::QRCODES_FOLDER_PATH. $qrCodeName);

        return $qrCodeName;
    }

}
