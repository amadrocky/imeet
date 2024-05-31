<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\AddressFormType;
use App\Repository\AddressRepository;
use App\Repository\EventRepository;
use App\Repository\OrderRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/user', name: 'app_user')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EventRepository $eventRepository,
        private readonly UserService $userService
    ) {
    }

    #[Route('/dashboard', name: '_dashboard')]
    public function index(#[CurrentUser] ?User $user): Response
    {
        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'form' => $this->createForm(AddressFormType::class),
            'userAddress' => $this->addressRepository->findOneBy(['email' => $user->getEmail()]),
            'orders' => $this->orderRepository->findBy(['email' => $user->getEmail()], ['updatedAt' => 'DESC']),
            'events' => $this->eventRepository->findBy(['email' => $user->getEmail()], ['updatedAt' => 'DESC'])
        ]);
    }

    #[Route('/dashboard/update-address', name: '_dashboard_address_update')]
    public function updateAddress(Request $request, #[CurrentUser] ?User $user): RedirectResponse
    {
        $datas = $request->request->all('address_form');
        $datas['email'] = $user->getEmail();

        $this->userService->createOrUpdateAddress($user, $datas);

        $this->addFlash('success', 'Les informations ont bien été enregistrées.');

        return $this->redirectToRoute('app_user_dashboard', [
            'id' => $user->getId(),
        ]);
    }
}
