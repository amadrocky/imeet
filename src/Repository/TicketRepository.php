<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Ticket;
use App\Helpers\Constants;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 *
 * @method Ticket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ticket|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ticket[]    findAll()
 * @method Ticket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function getScannedTickets(Event $event): int
    {
        $qb = $this->createQueryBuilder('o')
           ->andWhere('o.event = :event')
           ->andWhere('o.state = :scannedState')
           ->setParameters([
                'event' => $event,
                'scannedState' => Constants::TICKET_STATE_SCANNED
            ])
       ;

       $qb->select('count(o.id)');

       return $qb->getQuery()->getSingleScalarResult();
    }
}
