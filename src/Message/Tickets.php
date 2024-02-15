<?php

namespace App\Message;

use App\Entity\Event;

class Tickets
{
    public function __construct(
        private Event $event,
    ) {
    }

    public function getContent(): Event
    {
        return $this->event;
    }
}