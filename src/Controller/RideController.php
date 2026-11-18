<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\TripRepository;
use App\Service\TripMapFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Mes trajets" : tout ce que l'utilisateur fait en tant que conducteur ET passager,
 * range dans 3 onglets (a venir / passes / annules), comme sur BlaBlaCar.
 */
#[Route('/mes-trajets')]
final class RideController extends AbstractController
{
    private const TABS = ['a-venir', 'passes', 'annules'];

    // ── LISTE ──────────────────────────────────────────────────────────────
    #[Route('', name: 'app_rides', methods: ['GET'])]
    public function index(
        Request $request,
        TripRepository $tripRepo,
        BookingRepository $bookingRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $now  = new \DateTimeImmutable();

        $tab = $request->query->getString('onglet', 'a-venir');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'a-venir';
        }

        $items = [];

        // 1) Les trajets que je conduis
        foreach ($tripRepo->findBy(['driver' => $user, 'deletedAt' => null]) as $trip) {
            $passengers = [];
            $pending    = 0;
            foreach ($trip->getBookings() as $b) {
                if (in_array($b->getStatus(), [BookingStatus::Confirmed, BookingStatus::Completed], true)) {
                    $passengers[] = $b->getPassenger();
                }
                if ($b->getStatus() === BookingStatus::Pending) {
                    $pending++;
                }
            }

            $items[] = [
                'role'       => 'driver',
                'trip'       => $trip,
                'booking'    => null,
                'bucket'     => $this->bucketForTrip($trip, $now),
                'passengers' => $passengers,
                'pending'    => $pending,
            ];
        }

        // 2) Les trajets ou je suis passager
        foreach ($bookingRepo->findBy(['passenger' => $user]) as $booking) {
            $trip = $booking->getTrip();
            if ($trip->getDeletedAt() !== null) {
                continue;
            }

            $items[] = [
                'role'       => 'passenger',
                'trip'       => $trip,
                'booking'    => $booking,
                'bucket'     => $this->bucketForBooking($booking, $now),
                'passengers' => [],
                'pending'    => 0,
            ];
        }

        // Compteurs pour les badges des onglets
        $counts = ['a-venir' => 0, 'passes' => 0, 'annules' => 0];
        foreach ($items as $item) {
            $counts[$item['bucket']]++;
        }

        // Statistiques du haut de page
        $completedCount = $counts['passes'];
        $earnings       = 0.0;
        foreach ($items as $item) {
            if ($item['role'] !== 'driver') {
                continue;
            }
            foreach ($item['trip']->getBookings() as $b) {
                if ($b->getStatus() === BookingStatus::Completed) {
                    $earnings += (float) $b->getDriverEarns();
                }
            }
        }

        // Filtrer l'onglet courant + trier
        $visible = array_values(array_filter($items, fn (array $i) => $i['bucket'] === $tab));
        usort($visible, function (array $a, array $b) use ($tab): int {
            $cmp = $a['trip']->getDepartureAt() <=> $b['trip']->getDepartureAt();
            // A venir : le plus proche d'abord. Passes / annules : le plus recent d'abord.
            return $tab === 'a-venir' ? $cmp : -$cmp;
        });

        return $this->render('ride/index.html.twig', [
            'tab'            => $tab,
            'items'          => $visible,
            'counts'         => $counts,
            'completedCount' => $completedCount,
            'earnings'       => $earnings,
        ]);
    }

    // ── DETAIL CONDUCTEUR ("Plan de route") ────────────────────────────────
    #[Route('/conducteur/{id}', name: 'app_ride_driver', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function driver(Trip $trip, ReviewRepository $reviewRepo, TripMapFactory $mapFactory): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if ($trip->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }
        if ($trip->getDriver()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $now          = new \DateTimeImmutable();
        $isOpen       = in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true);
        $isUpcoming   = $isOpen && $trip->getDepartureAt() > $now;
        $needsClosing = $isOpen && $trip->getDepartureAt() <= $now;

        $confirmed = [];
        $pending   = [];
        $cancelled = [];
        $total     = 0.0;
        $earnings  = 0.0;

        foreach ($trip->getBookings() as $b) {
            switch ($b->getStatus()) {
                case BookingStatus::Confirmed:
                case BookingStatus::Completed:
                    $confirmed[] = $b;
                    $total      += (float) $b->getAmount();
                    $earnings   += (float) $b->getDriverEarns();
                    break;
                case BookingStatus::Pending:
                    $pending[] = $b;
                    break;
                case BookingStatus::Cancelled:
                    $cancelled[] = $b;
                    break;
            }
        }

        // Reservations deja notees par moi (pour ne pas proposer 2 fois "Laisser un avis")
        $reviewedBookingIds = array_map(
            fn ($r) => $r->getBooking()->getId(),
            $reviewRepo->findBy(['author' => $user])
        );

        return $this->render('ride/driver.html.twig', [
            'trip'               => $trip,
            'bucket'             => $this->bucketForTrip($trip, $now),
            'isUpcoming'         => $isUpcoming,
            'needsClosing'       => $needsClosing,
            'confirmed'          => $confirmed,
            'pending'            => $pending,
            'cancelled'          => $cancelled,
            'total'              => $total,
            'earnings'           => $earnings,
            'reviewedBookingIds' => $reviewedBookingIds,
            'tripMap'            => $mapFactory->create($trip),
        ]);
    }

    // ── DETAIL PASSAGER ────────────────────────────────────────────────────
    #[Route('/reservation/{id}', name: 'app_ride_passenger', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function passenger(Booking $booking, ReviewRepository $reviewRepo, TripMapFactory $mapFactory): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if ($booking->getPassenger()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $trip   = $booking->getTrip();
        $now    = new \DateTimeImmutable();
        $bucket = $this->bucketForBooking($booking, $now);

        $hasReviewed = $reviewRepo->findOneBy(['booking' => $booking, 'author' => $user]) !== null;

        // Note moyenne du conducteur
        $reviews = $trip->getDriver()->getReviewsReceived()->filter(
            fn ($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );
        $avgRating = count($reviews) > 0
            ? round(array_sum(array_map(fn ($r) => $r->getRating(), $reviews->toArray())) / count($reviews), 1)
            : null;

        return $this->render('ride/passenger.html.twig', [
            'booking'     => $booking,
            'trip'        => $trip,
            'bucket'      => $bucket,
            'isUpcoming'  => $bucket === 'a-venir',
            'hasReviewed' => $hasReviewed,
            'avgRating'   => $avgRating,
            'reviewCount' => count($reviews),
            'tripMap'     => $mapFactory->create($trip),
        ]);
    }

    // ── HELPERS ────────────────────────────────────────────────────────────
    private function bucketForTrip(Trip $trip, \DateTimeImmutable $now): string
    {
        if ($trip->getStatus() === TripStatus::Cancelled) {
            return 'annules';
        }
        if ($trip->getStatus() === TripStatus::Completed || $trip->getDepartureAt() <= $now) {
            return 'passes';
        }

        return 'a-venir';
    }

    private function bucketForBooking(Booking $booking, \DateTimeImmutable $now): string
    {
        $trip = $booking->getTrip();

        if ($booking->getStatus() === BookingStatus::Cancelled || $trip->getStatus() === TripStatus::Cancelled) {
            return 'annules';
        }
        if ($booking->getStatus() === BookingStatus::Completed || $trip->getDepartureAt() <= $now) {
            return 'passes';
        }

        return 'a-venir';
    }
}
