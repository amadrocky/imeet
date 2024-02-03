<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AddressFormType;
use App\Repository\AddressRepository;
use App\Repository\EventRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user', name: 'app_user')]
class UserController extends AbstractController
{
    public function __construct(
        private AddressRepository $addressRepository,
        private OrderRepository $orderRepository,
        private EventRepository $eventRepository
    ) {
    }

    #[Route('/{id}/dashboard', name: '_dashboard')]
    public function index(User $user): Response
    {
        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'form' => $this->createForm(AddressFormType::class),
            'userAddress' => $this->addressRepository->findOneBy(['email' => $user->getEmail()]),
            'orders' => $this->orderRepository->findBy(['email' => $user->getEmail()], ['updatedAt' => 'DESC']),
            'events' => $this->eventRepository->findBy(['email' => $user->getEmail()], ['updatedAt' => 'DESC'])
        ]);
    }
}
