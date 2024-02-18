<?php

namespace App\Message;

class Tickets
{
    public function __construct(
        private int $eventId,
    ) {
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }
}