<?php

namespace App\Controller;

use App\Entity\Event;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app', name: 'app_event')]
class EventController extends AbstractController
{
    #[Route('/event/{id}', name: '_scan')]
    public function index(Event $event): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        /** @var User $user */
        $user = $this->getUser();

        if ($user->getEmail() !== $event->getEmail()) {
            throw new Exception('The actual user doesn\'t own this event.');
        }

        return $this->render('event/scan.html.twig', [
            'event' => $event
        ]);
    }
}
