<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Event;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Ticket;
use App\Entity\User;
use App\Form\AddressFormType;
use App\Repository\AddressRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function PHPUnit\Framework\isNull;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    private const STRIPE_API_KEY = 'stripe_api_key';

    public function __construct(
        private ProductRepository $productRepository,
        private AddressRepository $addressRepository,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
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
        $formQuantity = $request->query->get('formQuantity');
        $orderTotal = ($product->getPrice() * $formQuantity) / 100;

        $form = $this->createForm(AddressFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();

            return $this->redirectToRoute('app_order_confirm', [
                'datas' => $datas,
                'product' => $product,
                'orderQuantity' => $formQuantity,
                'orderTotal' => $orderTotal,
            ]);
        }

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
        $orderTotal = ($product->getPrice() * $datas['quantity']) / 100;

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
        $orderQuantity = $request->request->get('quantity');
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
                        'unit_amount' => $orderTotal,
                        'product_data' => [
                            'name' => $product->getName(),
                            'images' => ["https://imeet.lndo.site.fr/build/logos/imeet.png"],
                        ],
                    ],
                    'quantity' => 1, // Check if we can change or not (if action on stripe price)
                ]],
                'mode' => 'payment',
                'success_url' => $this->generateUrl('app_order_payment_success', ['slug' => $product->getSlug(), 'datas' => $datas], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('app_order_payment_error', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
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
    public function paymentSuccess(Request $request, Product $product): Response
    {
        $stripeSessionId = $request->getSession()->get('stripe_session_id');

        if (!isset($stripeSessionId)) {
            return $this->redirectToRoute('app_home');
        }

        $stripe = new \Stripe\StripeClient($this->getParameter(self::STRIPE_API_KEY));
        $stripeSession = $stripe->checkout->sessions->retrieve($stripeSessionId, []);

        $datas = $request->query->all('datas');
        $orderQuantity = $request->request->get('quantity');
        $event = null;

        if ($stripeSession->payment_status == "paid") {
            $user = $this->getUser();

            $this->createOrUpdateAddress($user, $datas);

            // if (!empty($data['eventName'])) {
            //     $event = $this->createEvent($datas);
            // }

            // $order = $this->createOrder($product, $event, $orderQuantity, $datas);

            // $this->createTickets($order, $product, $orderQuantity, $event);
        }

        return $this->render('order/success.html.twig', [
            'product' => $product,
            'datas' => $datas
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

    private function createOrUpdateAddress(?User $user, array $datas): Address
    {
        $email = $datas['email'];

        if (is_null($user)) {
            $existentUser = $this->userRepository->findOneBy(['email' => $email]);

            if (empty($existentUser)) {
                $address = new Address();
            } else {
                $user = $existentUser;
                $user->setLastname($datas['lastname']);
                $user->setFirstname($datas['firstname']);
                $this->persistAndFlush($user);

                $address = $this->getOrInitUserAddress($user, $email);

                $address->setUser($user);
            }
        } else {
            $address = $this->getOrInitUserAddress($user, $email);

            $user->setLastname($datas['lastname']);
            $user->setFirstname($datas['firstname']);
            $this->persistAndFlush($user);

            $address->setUser($user);
        }

        $address->setEmail($datas['email']);

        if (!empty($datas['street'])) {
            $address->setStreet($datas['street']);
        }

        if (!empty($datas['postcode'])) {
            $address->setPostcode($datas['postcode']);
        }

        if (!empty($datas['city'])) {
            $address->setCity($datas['city']);
        }
        
        if (!empty($datas['country'])) {
            $address->setCountry($datas['country']);
        }

        if (!empty($datas['phoneNumber'])) {
            $address->setPhoneNumber($datas['phoneNumber']);
        }

        $this->persistAndFlush($address);

        return $address;
    }

    private function createEvent(array $datas): Event
    {
        $event = new Event();

        $event->setEmail($datas['email']);
        $event->setName($datas['eventName']);
        $event->setStartDate($datas['eventDate']);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

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
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createTickets(Order $order, Product $product, int $quantity, ?Event $event = null)
    {
        $totalTickets = $product->getQuantity() * $quantity;

        for ($i = 0; $i < $totalTickets; $i++) {
            $ticket = new Ticket();
            $number = strtoupper(uniqId(rand()));

            $writer = new PngWriter();
            $qrCode = new QrCode('https://www.imeet.fr/app/qr/' . $number);

            // name qrCode
            $qrCodeName = $number . '.png';

            $result = $writer->write($qrCode);

            // Save it to a file
            $result->saveToFile('/var/www/imeet/imeet/public/imeet/images/qrCodes/'. $qrCodeName); // try __DIR__./$qrCodeName

            $ticket->setBill($order);
            $ticket->setEvent($event);
            $ticket->setNumber($number);
            $ticket->setQrCode($qrCodeName);
            $ticket->setState('active');

            $this->entityManager->persist($ticket);
        }

        $this->entityManager->flush();
    }

    private function getOrInitUserAddress(User $user, string $email): Address
    {
        $address = $user->getAddress();

        if (is_null($address)) {
            $existingAddress = $this->addressRepository->findOneBy(['email' => $email]);

            $address = empty($existingAddress) ? new Address() : $existingAddress;
        }

        return $address;
    }

    private function persistAndFlush($object): void
    {
        $this->entityManager->persist($object);
        $this->entityManager->flush();
    }
}
