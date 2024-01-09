<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Event;
use App\Entity\Product;
use App\Form\AddressFormType;
use App\Repository\AddressRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private AddressRepository $addressRepository,
        private EntityManagerInterface $entityManager
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
            'form' => $form
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
            // Stripe checkout
            $stripe = new \Stripe\StripeClient($this->getParameter('stripe_api_key'));
            $customer = $stripe->customers->create([
                'email' => $datas->email,
            ]);

            Stripe::setApiKey($this->getParameter('stripe_api_key'));
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
                            // 'images' => ["https://histoiresdoliviers.fr/images/logoFullBlackGold.png"],
                        ],
                    ],
                    'quantity' => 1, // Check if we can change or not (if action on stripe price)
                ]],
                'mode' => 'payment',
                'success_url' => $this->generateUrl('app_order_payment_success', ['slug' => $product->getSlug(), 'datas' => $datas], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('app_order_payment_error', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
            
            $session = Session::create($parameters);

            $request->getSession()->set('payment_intent', $session->payment_intent);

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
        $datas = json_decode($request->request->get('datas'));

        // $user = null;
            
        // /** @var Address */
        // $address = $this->addressRepository->findByEmail();

        // $this->createOrUpdateAddress($address, $datas);

        // // persist($address)
        
        // // Create order

        // if (isset($datas->event)) {
        //     $event = new Event();

        //     $event->setName($datas->eventName);
        //     $event->setStartDate($datas->eventDate);
        //     $event->setUser($user);
        // }

        return $this->render('order/success.html.twig', [
            'product' => $product,
            'datas' => $datas
        ]);
    }

    #[Route('/{slug}/payment/error', name: '_payment_error')]
    public function paymentError(Product $product): Response
    {
        return $this->render('order/error.html.twig', [
            'product' => $product
        ]);
    }

    private function createOrUpdateAddress(?Address $address, $datas)
    {
        if (is_null($address)) {
            $address = new Address();
        }

        $user = $address->getUser();
        $user->setLastname($datas->lastname);
        $user->setFirstname($datas->firstname);
        // persist($user)

        $address->setStreet($datas->street);
        $address->setPostcode($datas->postcode);
        $address->setCity($datas->city);
        $address->setCountry($datas->country);
        $address->setPhoneNumber($datas->phoneNumber);
        $address->setEmail($datas->email);
        // $address->setUser(user);

        // persist($address)
        // flush()
    }
}
