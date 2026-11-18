<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $seats = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $platformFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $driverEarns = null;

    #[ORM\Column(enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(enumType: CancelledBy::class, nullable: true)]
    private ?CancelledBy $cancelledBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelledReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidToDriverAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $passenger = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'booking')]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPlatformFee(): ?string
    {
        return $this->platformFee;
    }

    public function setPlatformFee(string $platformFee): static
    {
        $this->platformFee = $platformFee;

        return $this;
    }

    public function getDriverEarns(): ?string
    {
        return $this->driverEarns;
    }

    public function setDriverEarns(string $driverEarns): static
    {
        $this->driverEarns = $driverEarns;

        return $this;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;
        return $this;
    }


    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCancelledBy(): ?CancelledBy
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?CancelledBy $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCancelledReason(): ?string
    {
        return $this->cancelledReason;
    }

    public function setCancelledReason(?string $cancelledReason): static
    {
        $this->cancelledReason = $cancelledReason;

        return $this;
    }

    public function getPaidToDriverAt(): ?\DateTimeImmutable
    {
        return $this->paidToDriverAt;
    }

    public function setPaidToDriverAt(?\DateTimeImmutable $paidToDriverAt): static
    {
        $this->paidToDriverAt = $paidToDriverAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getPassenger(): ?User
    {
        return $this->passenger;
    }

    public function setPassenger(?User $passenger): static
    {
        $this->passenger = $passenger;

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setBooking($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getBooking() === $this) {
                $review->setBooking(null);
            }
        }

        return $this;
    }
}
