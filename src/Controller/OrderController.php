<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Event;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Ticket;
use App\Entity\User;
use App\Form\AddressFormType;
use App\Helpers\Constants;
use App\Message\Tickets;
use App\Repository\AddressRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\EventService;
use App\Service\GlobalService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    private const STRIPE_API_KEY = 'stripe_api_key';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly AddressRepository $addressRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserService $userService,
        private readonly GlobalService $globalService,
        private readonly EventService $eventService
    ) {
    }

    #[Route('/{slug}', name: '_recap')]
    public function index(Product $product): Response
    {
        return $this->render('order/index.html.twig', [
            'product' => $product
        ]);
    }

    #[Route('/{slug}/address', name: '_address')]
    public function address(Request $request, Product $product): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userAddress = !is_null($user) ? $this->addressRepository->findOneBy(['email' => $user->getEmail()]) : null;
        $formQuantity = $request->request->get('formQuantity');
        $orderTotal = ($product->getPrice() * $formQuantity) / 100;

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

    #[Route('/{slug}/confirm', name: '_confirm')]
    public function confirm(Request $request, Product $product): Response
    {
        $datas = $request->request->all();

        return $this->render('order/confirm.html.twig', [
            'product' => $product,
            'orderQuantity' => $datas['quantity'],
            'orderTotal' => ($product->getPrice() * $datas['quantity']) / 100,
            'datas' => $datas['address_form']
        ]);
    }

    #[Route('/{slug}/payment', name: '_payment')]
    public function payment(Request $request, Product $product): Response
    {
        $datas = json_decode($request->request->get('datas'));
        $orderQuantity = intval($request->request->get('quantity'));
        $orderTotal = ($product->getPrice() * $orderQuantity);

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
                            'images' => ["https://imeet.lndo.site.fr/build/logos/imeet.png"],
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
    public function paymentSuccess(Request $request, Product $product, MessageBusInterface $bus): Response
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

        if ($stripeSession->payment_status == "paid") {
            $user = $this->getUser();

            $this->userService->createOrUpdateAddress($user, $datas);

            $event = $this->createEvent($datas);

            $order = $this->createOrder($product, $event, $orderQuantity, $datas);

            $this->createTickets($order, $product, $orderQuantity, $event);

            $bus->dispatch(new Tickets($event->getId()));
        }

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

        $order->setNumber(uniqid());
        $order->setProduct($product);
        $order->setEmail($datas['email']);
        $order->setEvent($event);
        $order->setQuantity($quantity);
        $order->setTotal($product->getPrice() * $quantity);
        $order->setState(Constants::ORDER_STATE_PAID);
        $this->globalService->persistAndFlush($order);

        return $order;
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

            $this->entityManager->persist($ticket);
        }

        $this->entityManager->flush();
    }

    private function createQrCode(string $number): string
    {
        $writer = new PngWriter();

        $qrCode = new QrCode('https://www.imeet.fr/app/qr/' . $number);

        $qrCodeName = $number . '.png';

        $result = $writer->write($qrCode);

        $result->saveToFile($this->getParameter('kernel.project_dir').Constants::QRCODES_FOLDER_PATH. $qrCodeName);

        return $qrCodeName;
    }

}
