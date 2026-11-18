<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Enum\MusicGenre;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateOfBirth = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $vehicles;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'driver', orphanRemoval: true)]
    private Collection $trips;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'passenger')]
    private Collection $bookings;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $balancePending = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $balanceAvailable = '0.00';

    /**
     * @var Collection<int, ConversationParticipant>
     */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'user')]
    private Collection $conversationParticipants;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messages;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'author')]
    private Collection $reviewsGiven;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'target')]
    private Collection $reviewsReceived;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, AdminLog>
     */
    #[ORM\OneToMany(targetEntity: AdminLog::class, mappedBy: 'adminUser')]
    private Collection $adminLogs;

    #[ORM\Column]
    private bool $smokingAllowed = false;


    #[ORM\Column]
    private bool $petsAllowed = false;

    #[ORM\Column(enumType: MusicGenre::class, nullable: true)]
    private ?MusicGenre $musicGenre = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $tripsCompleted = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $tripsCancelled = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cguAcceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $suspendedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $suspendedReason = null;

    #[ORM\Column(nullable: true)]
    private ?int $deletedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deletedReason = null;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->vehicles = new ArrayCollection();
        $this->trips = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->conversationParticipants = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->reviewsGiven = new ArrayCollection();
        $this->reviewsReceived = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->adminLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

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

    public function getDateOfBirth(): ?\DateTime
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(\DateTime $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): static
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->setOwner($this);
        }

        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): static
    {
        if ($this->vehicles->removeElement($vehicle)) {
            // set the owning side to null (unless already changed)
            if ($vehicle->getOwner() === $this) {
                $vehicle->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setDriver($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip)) {
            // set the owning side to null (unless already changed)
            if ($trip->getDriver() === $this) {
                $trip->setDriver(null);
            }
        }

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
            $booking->setPassenger($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getPassenger() === $this) {
                $booking->setPassenger(null);
            }
        }

        return $this;
    }

    public function getBalancePending(): string
    {
        return $this->balancePending;
    }

    public function setBalancePending(string $balancePending): static
    {
        $this->balancePending = $balancePending;
        return $this;
    }

    public function getBalanceAvailable(): string
    {
        return $this->balanceAvailable;
    }

    public function setBalanceAvailable(string $balanceAvailable): static
    {
        $this->balanceAvailable = $balanceAvailable;
        return $this;
    }

    /**
     * @return Collection<int, ConversationParticipant>
     */
    public function getConversationParticipants(): Collection
    {
        return $this->conversationParticipants;
    }

    public function addConversationParticipant(ConversationParticipant $conversationParticipant): static
    {
        if (!$this->conversationParticipants->contains($conversationParticipant)) {
            $this->conversationParticipants->add($conversationParticipant);
            $conversationParticipant->setUser($this);
        }

        return $this;
    }

    public function removeConversationParticipant(ConversationParticipant $conversationParticipant): static
    {
        if ($this->conversationParticipants->removeElement($conversationParticipant)) {
            // set the owning side to null (unless already changed)
            if ($conversationParticipant->getUser() === $this) {
                $conversationParticipant->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSender($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviewsGiven(): Collection
    {
        return $this->reviewsGiven;
    }

    public function addReviewsGiven(Review $reviewsGiven): static
    {
        if (!$this->reviewsGiven->contains($reviewsGiven)) {
            $this->reviewsGiven->add($reviewsGiven);
            $reviewsGiven->setAuthor($this);
        }

        return $this;
    }

    public function removeReviewsGiven(Review $reviewsGiven): static
    {
        if ($this->reviewsGiven->removeElement($reviewsGiven)) {
            // set the owning side to null (unless already changed)
            if ($reviewsGiven->getAuthor() === $this) {
                $reviewsGiven->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviewsReceived(): Collection
    {
        return $this->reviewsReceived;
    }

    public function addReviewsReceived(Review $reviewsReceived): static
    {
        if (!$this->reviewsReceived->contains($reviewsReceived)) {
            $this->reviewsReceived->add($reviewsReceived);
            $reviewsReceived->setTarget($this);
        }

        return $this;
    }

    public function removeReviewsReceived(Review $reviewsReceived): static
    {
        if ($this->reviewsReceived->removeElement($reviewsReceived)) {
            // set the owning side to null (unless already changed)
            if ($reviewsReceived->getTarget() === $this) {
                $reviewsReceived->setTarget(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdminLog>
     */
    public function getAdminLogs(): Collection
    {
        return $this->adminLogs;
    }

    public function addAdminLog(AdminLog $adminLog): static
    {
        if (!$this->adminLogs->contains($adminLog)) {
            $this->adminLogs->add($adminLog);
            $adminLog->setAdminUser($this);
        }

        return $this;
    }

    public function removeAdminLog(AdminLog $adminLog): static
    {
        if ($this->adminLogs->removeElement($adminLog)) {
            // set the owning side to null (unless already changed)
            if ($adminLog->getAdminUser() === $this) {
                $adminLog->setAdminUser(null);
            }
        }

        return $this;
    }

    public function isSmokingAllowed(): ?bool
    {
        return $this->smokingAllowed;
    }

    public function setSmokingAllowed(bool $smokingAllowed): static
    {
        $this->smokingAllowed = $smokingAllowed;

        return $this;
    }

    public function isPetsAllowed(): ?bool
    {
        return $this->petsAllowed;
    }

    public function setPetsAllowed(bool $petsAllowed): static
    {
        $this->petsAllowed = $petsAllowed;

        return $this;
    }

    public function getMusicGenre(): ?MusicGenre
    {
        return $this->musicGenre;
    }

    public function setMusicGenre(?MusicGenre $musicGenre): static
    {
        $this->musicGenre = $musicGenre;
        return $this;
    }

    public function getTripsCompleted(): ?int
    {
        return $this->tripsCompleted;
    }

    public function setTripsCompleted(int $tripsCompleted): static
    {
        $this->tripsCompleted = $tripsCompleted;

        return $this;
    }
    public function getTripsCancelled(): ?int
    {
        return $this->tripsCancelled;
    }

    public function setTripsCancelled(int $tripsCancelled): static
    {
        $this->tripsCancelled = $tripsCancelled;

        return $this;
    }

    public function getCguAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->cguAcceptedAt;
    }

    public function setCguAcceptedAt(?\DateTimeImmutable $cguAcceptedAt): static
    {
        $this->cguAcceptedAt = $cguAcceptedAt;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

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

}
