<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\NotificationType;
use App\Enum\TripStatus;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Suppression de compte (droit a l'effacement, RGPD).
 *
 * On ne supprime pas la ligne en base : les autres membres gardent leur historique
 * (trajets passes, avis, messages). On ANONYMISE a la place : nom, e-mail, telephone, photo,
 * bio et mot de passe sont effaces ou remplaces. Le compte ne peut plus se connecter
 * (UserChecker) et son profil renvoie une page 404.
 *
 * Avant ca, on annule proprement tout ce qui est a venir et on previent les personnes concernees.
 */
final class AccountDeleter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TripRepository $trips,
        private readonly BookingRepository $bookings,
        private readonly NotificationService $notifier,
        private readonly AvatarUploader $avatars,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function delete(User $user): void
    {
        $now  = new \DateTimeImmutable();
        $name = $user->getFirstName();

        // 1) Mes trajets a venir (conducteur) : annules, passagers prevenus
        foreach ($this->trips->findBy(['driver' => $user, 'deletedAt' => null]) as $trip) {
            if (!in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true) || $trip->getDepartureAt() <= $now) {
                continue;
            }
            $trip->setStatus(TripStatus::Cancelled);
            foreach ($trip->getBookings() as $booking) {
                if (in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                    $booking->setStatus(BookingStatus::Cancelled)->setCancelledBy(CancelledBy::Driver)->setCancelledAt($now);
                    $this->notifier->notify($booking->getPassenger(), NotificationType::TripCancelled,
                        $name . ' a quitte Covoite : le trajet ' . $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity() . ' est annule.',
                        'app_ride_passenger', ['id' => $booking->getId()]);
                }
            }
        }

        // 2) Mes reservations a venir (passager) : annulees, places rendues, conducteurs prevenus
        foreach ($this->bookings->findBy(['passenger' => $user]) as $booking) {
            $trip = $booking->getTrip();
            if (!in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true) || $trip->getDepartureAt() <= $now) {
                continue;
            }
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getSeats());
                if ($trip->getStatus() === TripStatus::Full) {
                    $trip->setStatus(TripStatus::Active);
                }
            }
            $booking->setStatus(BookingStatus::Cancelled)->setCancelledBy(CancelledBy::Passenger)->setCancelledAt($now);
            $this->notifier->notify($trip->getDriver(), NotificationType::BookingCancelled,
                $name . ' a quitte Covoite : sa reservation pour ' . $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity() . ' est annulee.',
                'app_ride_driver', ['id' => $trip->getId()]);
        }

        // 3) Anonymisation
        $this->avatars->remove($user->getAvatar());
        $user->setEmail('supprime-' . $user->getId() . '-' . bin2hex(random_bytes(4)) . '@covoite.invalid')
            ->setFirstName('Membre')
            ->setLastName('supprime')
            ->setPhone(null)
            ->setBio(null)
            ->setAvatar(null)
            ->setRoles([])
            ->setDeletedAt($now)
            ->setDeletedReason('Supprime par l\'utilisateur');
        // Mot de passe remplace par un hash aleatoire que personne ne connait
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->em->flush();
    }
}
