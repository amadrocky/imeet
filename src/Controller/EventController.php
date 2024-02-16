<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Service\EventService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app', name: 'app_event')]
class EventController extends AbstractController
{
    public function __construct(
        private EventService $eventService
    ) {
    }

    #[Route('/event/{id}', name: '_scan')]
    public function index(Event $event): Response
    {
        $this->isOwner($this->getUser(), $event);

        return $this->render('event/scan.html.twig', [
            'event' => $event
        ]);
    }

    #[Route('/event/{id}/tickets', name: '_tickets')]
    public function downloadTickets(Event $event): Response
    {
        return $this->eventService->getTicketsFile($event);
    }

    public function isOwner(User $user, Event $event): void
    {
        if ($user->getEmail() !== $event->getEmail()) {
            throw new Exception('The actual user doesn\'t own this event.');
        }
    }
}
