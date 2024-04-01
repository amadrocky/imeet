<?php

namespace App\Controller;

use App\Entity\Event;
use App\Service\EventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/event/{id}/scan', name: '_scan_ticket', methods: [Request::METHOD_POST])]
    public function checkTicket(Request $request, Event $event): JsonResponse
    {
        $url = $request->getPayload()->all()['url'];

        return $this->eventService->checkTicket($event, $url);
    }

    #[Route('/event/{id}/reports', name: '_reports')]
    public function reports(Event $event): Response
    {
        $this->eventService->isOwner($this->getUser(), $event);

        return $this->render('event/reports.html.twig', [
            'event' => $event,
            'scannedTickets' => $this->eventService->getScannedTickets($event),
            'percentage' => $this->eventService->getPercentageOfTicketsScanned($event),
            'scannedTicketsChart' => $this->eventService->getScannedTicketsChart($event),
            'scannedTicketsByHoursChart' => $this->eventService->getScannedTicketsByHoursChart($event),
            'rushHour' => $this->eventService->getRushHour($event)
        ]);
    }
}
