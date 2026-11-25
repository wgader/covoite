<?php

namespace App\Service;

use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\NotificationType;
use App\Enum\TripStatus;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Termine automatiquement les trajets dont le depart est passe depuis plus de 6 heures
 * (le conducteur peut toujours cliquer "Terminer" avant).
 *
 * Utilise par :
 *   - la commande  bin/console app:close-past-trips
 *   - l'ecouteur CloseTripsOnTerminate, qui la lance tout seul toutes les 15 min
 *     apres une requete (pas besoin de tache cron sur le serveur)
 */
final class PastTripsCloser
{
    private const AFTER_HOURS = 6;

    public function __construct(
        private readonly TripRepository $trips,
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifier,
    ) {
    }

    /** @return int nombre de trajets termines */
    public function closeAll(): int
    {
        $limit = (new \DateTimeImmutable())->modify('-' . self::AFTER_HOURS . ' hours');

        /** @var Trip[] $candidates */
        $candidates = $this->trips->createQueryBuilder('t')
            ->where('t.status IN (:open)')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.departureAt < :limit')
            ->setParameter('open', [TripStatus::Active->value, TripStatus::Full->value])
            ->setParameter('limit', $limit)
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($candidates as $trip) {
            if ($this->close($trip)) {
                $count++;
            }
        }
        $this->em->flush();

        return $count;
    }

    /**
     * Termine UN trajet (aussi utilise par le bouton "Terminer" du conducteur).
     * Le changement de statut se fait par un UPDATE conditionnel : si deux requetes essaient
     * en meme temps, une seule "gagne" (1 ligne modifiee) et le compteur n'est pas compte deux fois.
     * Le flush() est a faire par l'appelant.
     */
    public function close(Trip $trip): bool
    {
        $changed = $this->em->createQueryBuilder()
            ->update(Trip::class, 't')
            ->set('t.status', ':done')
            ->where('t.id = :id')
            ->andWhere('t.status IN (:open)')
            ->setParameter('done', TripStatus::Completed->value)
            ->setParameter('id', $trip->getId())
            ->setParameter('open', [TripStatus::Active->value, TripStatus::Full->value])
            ->getQuery()
            ->execute();

        if ($changed !== 1) {
            return false; // deja termine (ou annule) entre-temps
        }

        $this->em->refresh($trip);
        $now    = new \DateTimeImmutable();
        $driver = $trip->getDriver();
        $route  = $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity();
        $hadPassengers = false;

        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $booking->setStatus(BookingStatus::Completed);
                $hadPassengers = true;
                $this->notifier->notify($booking->getPassenger(), NotificationType::TripCompleted,
                    'Votre trajet ' . $route . ' avec ' . $driver->getFirstName() . ' est termine. Laissez-lui un avis !',
                    'app_ride_passenger', ['id' => $booking->getId()]);
            } elseif ($booking->getStatus() === BookingStatus::Pending) {
                // Une demande jamais acceptee n'a plus de sens apres le depart
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledBy(CancelledBy::Driver);
                $booking->setCancelledAt($now);
            }
        }

        $driver->setTripsCompleted(($driver->getTripsCompleted() ?? 0) + 1);

        if ($hadPassengers) {
            $this->notifier->notify($driver, NotificationType::TripCompleted,
                'Votre trajet ' . $route . ' est termine. Pensez a noter vos passagers.',
                'app_ride_driver', ['id' => $trip->getId()]);
        }

        return true;
    }
}
