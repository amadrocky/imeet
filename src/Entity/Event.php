<?php

namespace App\Entity;

use App\Repository\EventRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    use TimestampableEntity;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\OneToOne(mappedBy: 'event', cascade: ['persist', 'remove'])]
    private ?Order $bill = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Ticket::class)]
    private Collection $tickets;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getBill(): ?Order
    {
        return $this->bill;
    }

    public function setBill(?Order $bill): static
    {
        // unset the owning side of the relation if necessary
        if ($bill === null && $this->bill !== null) {
            $this->bill->setEvent(null);
        }

        // set the owning side of the relation if necessary
        if ($bill !== null && $bill->getEvent() !== $this) {
            $bill->setEvent($this);
        }

        $this->bill = $bill;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setEvent($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function hasETickets(): bool
    {
        $tickets = $this->getTickets()->toArray();
        
        return array_shift($tickets)->getBill()->getProduct()->hasETickets();
    }

    public function hasReports(): bool
    {
        $tickets = $this->getTickets()->toArray();
        
        return array_shift($tickets)->getBill()->getProduct()->hasReports();
    }

    public function isOutdated(): bool
    {
        return date_modify($this->getStartDate(), '+ 7 days') < new DateTime();
    }
}
