<?php

namespace App\MessageHandler;

use App\Message\Tickets;
use App\Service\EventService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TicketsHandler
{
    public function __construct(
        private readonly EventService $eventService
    ) {
    }

    public function __invoke(Tickets $tickets)
    {
        $this->eventService->exportTickets($tickets->getContent());
    }
}