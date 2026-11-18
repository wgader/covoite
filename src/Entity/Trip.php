<?php

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TripStatus;
use App\Enum\LuggageSize;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $departureAddress = null;

    #[ORM\Column(length: 100)]
    private ?string $departureCity = null;

    #[ORM\Column(length: 10)]
    private ?string $departurePostalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $departureLat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $departureLng = null;

    #[ORM\Column(length: 255)]
    private ?string $arrivalAddress = null;

    #[ORM\Column(length: 100)]
    private ?string $arrivalCity = null;

    #[ORM\Column(length: 10)]
    private ?string $arrivalPostalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $arrivalLat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $arrivalLng = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $availableSeats = null;

    #[ORM\Column(enumType: TripStatus::class)]
    private TripStatus $status = TripStatus::Active;

    #[ORM\Column(enumType: LuggageSize::class, nullable: true)]
    private ?LuggageSize $luggageAllowed = null;

    #[ORM\Column]
    private bool $avoidHighway = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $routeHint = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $delayMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $suspendedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $suspendedReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $deletedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deletedReason = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    private ?Vehicle $vehicle = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'trip', orphanRemoval: true)]
    private Collection $bookings;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'trip')]
    private Collection $conversations;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->conversations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepartureAddress(): ?string
    {
        return $this->departureAddress;
    }

    public function setDepartureAddress(string $departureAddress): static
    {
        $this->departureAddress = $departureAddress;

        return $this;
    }

    public function getDepartureCity(): ?string
    {
        return $this->departureCity;
    }

    public function setDepartureCity(string $departureCity): static
    {
        $this->departureCity = $departureCity;

        return $this;
    }

    public function getDeparturePostalCode(): ?string
    {
        return $this->departurePostalCode;
    }

    public function setDeparturePostalCode(string $departurePostalCode): static
    {
        $this->departurePostalCode = $departurePostalCode;

        return $this;
    }

    public function getDepartureLat(): ?string
    {
        return $this->departureLat;
    }

    public function setDepartureLat(?string $departureLat): static
    {
        $this->departureLat = $departureLat;

        return $this;
    }

    public function getDepartureLng(): ?string
    {
        return $this->departureLng;
    }

    public function setDepartureLng(?string $departureLng): static
    {
        $this->departureLng = $departureLng;

        return $this;
    }

    public function getArrivalAddress(): ?string
    {
        return $this->arrivalAddress;
    }

    public function setArrivalAddress(string $arrivalAddress): static
    {
        $this->arrivalAddress = $arrivalAddress;

        return $this;
    }

    public function getArrivalCity(): ?string
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(string $arrivalCity): static
    {
        $this->arrivalCity = $arrivalCity;

        return $this;
    }

    public function getArrivalPostalCode(): ?string
    {
        return $this->arrivalPostalCode;
    }

    public function setArrivalPostalCode(string $arrivalPostalCode): static
    {
        $this->arrivalPostalCode = $arrivalPostalCode;

        return $this;
    }

    public function getArrivalLat(): ?string
    {
        return $this->arrivalLat;
    }

    public function setArrivalLat(?string $arrivalLat): static
    {
        $this->arrivalLat = $arrivalLat;

        return $this;
    }

    public function getArrivalLng(): ?string
    {
        return $this->arrivalLng;
    }

    public function setArrivalLng(?string $arrivalLng): static
    {
        $this->arrivalLng = $arrivalLng;

        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(\DateTimeImmutable $departureAt): static
    {
        $this->departureAt = $departureAt;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getAvailableSeats(): ?int
    {
        return $this->availableSeats;
    }

    public function setAvailableSeats(int $availableSeats): static
    {
        $this->availableSeats = $availableSeats;

        return $this;
    }

    public function getStatus(): TripStatus
    {
        return $this->status;
    }

    public function setStatus(TripStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getLuggageAllowed(): ?LuggageSize
    {
        return $this->luggageAllowed;
    }

    public function setLuggageAllowed(?LuggageSize $luggageAllowed): static
    {
        $this->luggageAllowed = $luggageAllowed;
        return $this;
    }

    public function isAvoidHighway(): ?bool
    {
        return $this->avoidHighway;
    }

    public function setAvoidHighway(bool $avoidHighway): static
    {
        $this->avoidHighway = $avoidHighway;

        return $this;
    }

    public function getRouteHint(): ?string
    {
        return $this->routeHint;
    }

    public function setRouteHint(?string $routeHint): static
    {
        $this->routeHint = $routeHint;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getDelayMinutes(): ?int
    {
        return $this->delayMinutes;
    }

    public function setDelayMinutes(?int $delayMinutes): static
    {
        $this->delayMinutes = $delayMinutes;

        return $this;
    }

    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function setSuspendedAt(?\DateTimeImmutable $suspendedAt): static
    {
        $this->suspendedAt = $suspendedAt;

        return $this;
    }

    public function getSuspendedBy(): ?int
    {
        return $this->suspendedBy;
    }

    public function setSuspendedBy(?int $suspendedBy): static
    {
        $this->suspendedBy = $suspendedBy;

        return $this;
    }

    public function getSuspendedReason(): ?string
    {
        return $this->suspendedReason;
    }

    public function setSuspendedReason(?string $suspendedReason): static
    {
        $this->suspendedReason = $suspendedReason;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getDeletedBy(): ?int
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?int $deletedBy): static
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    public function getDeletedReason(): ?string
    {
        return $this->deletedReason;
    }

    public function setDeletedReason(?string $deletedReason): static
    {
        $this->deletedReason = $deletedReason;

        return $this;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setTrip($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getTrip() === $this) {
                $booking->setTrip(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setTrip($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation)) {
            // set the owning side to null (unless already changed)
            if ($conversation->getTrip() === $this) {
                $conversation->setTrip(null);
            }
        }

        return $this;
    }
}
