<?php

namespace App\MessageHandler;

use App\Message\Tickets;
use App\Repository\EventRepository;
use App\Service\EventService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TicketsHandler
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventService $eventService
    ) {
    }

    public function __invoke(Tickets $tickets)
    {
        $event = $this->eventRepository->find($tickets->getEventId());

        $this->eventService->exportTickets($event);

        if ($event->hasETickets()) {
            $this->eventService->deleteQrCodes($event);
        }
    }
}