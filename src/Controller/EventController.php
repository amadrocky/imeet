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
        $this->eventService->isOwner($this->getUser(), $event);

        return $this->render('event/scan.html.twig', [
            'event' => $event
        ]);
    }

    #[Route('/event/{id}/tickets', name: '_tickets')]
    public function downloadTickets(Event $event): Response
    {
        $this->eventService->isOwner($this->getUser(), $event);
        
        return $this->eventService->getTicketsFile($event);
    }
}
