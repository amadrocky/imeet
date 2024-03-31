<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class GlobalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function persistAndFlush($object): void
    {
        $this->entityManager->persist($object);
        $this->entityManager->flush();
    }

}