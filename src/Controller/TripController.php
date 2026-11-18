<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\TripStatus;
use App\Form\TripFormType;
use App\Repository\TripRepository;
use App\Service\TripMapFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    // ── RECHERCHE ──────────────────────────────────────────────────────────
    #[Route('/trajets', name: 'app_search')]
    public function search(
        TripRepository $repo,
        #[MapQueryParameter] string $from = '',
        #[MapQueryParameter] string $to = '',
        #[MapQueryParameter] string $date = '',
        #[MapQueryParameter] int $seats = 1,
        #[MapQueryParameter] string $tri = 'depart',
    ): Response {
        $sort  = in_array($tri, ['depart', 'prix'], true) ? $tri : 'depart';
        $seats = max(1, $seats);
        $trips = $repo->search(trim($from), trim($to), trim($date), $seats, $sort);

        // Note moyenne de chaque conducteur (affichee sur les cartes)
        $ratings = [];
        foreach ($trips as $trip) {
            $driver = $trip->getDriver();
            if (isset($ratings[$driver->getId()])) {
                continue;
            }
            $notes = [];
            foreach ($driver->getReviewsReceived() as $r) {
                if ($r->getDeletedAt() === null && $r->getSuspendedAt() === null) {
                    $notes[] = $r->getRating();
                }
            }
            $ratings[$driver->getId()] = $notes ? ['avg' => round(array_sum($notes) / count($notes), 1), 'count' => count($notes)] : null;
        }

        return $this->render('trip/search.html.twig', [
            'trips'   => $trips,
            'ratings' => $ratings,
            'from'    => $from,
            'to'      => $to,
            'date'    => $date,
            'seats'   => $seats,
            'sort'    => $sort,
            'total'   => count($trips),
        ]);
    }

    // ── PUBLIER UN TRAJET ──────────────────────────────────────────────────
    #[Route('/trajets/nouveau', name: 'app_trip_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $trip = new Trip();

        // Valeurs de depart pour l'assistant : vehicule par defaut + 3 places
        $trip->setAvailableSeats(3);
        foreach ($this->getUser()->getVehicles() as $vehicle) {
            if ($vehicle->isDefault()) {
                $trip->setVehicle($vehicle);
                $trip->setAvailableSeats(min(3, max(1, $vehicle->getSeats() - 1)));
                break;
            }
        }

        $form = $this->createForm(TripFormType::class, $trip, ['driver' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trip->getDepartureAt() <= new \DateTimeImmutable()) {
                $this->addFlash('error', 'La date de depart doit etre dans le futur.');

                return $this->render('trip/new.html.twig', ['tripForm' => $form]);
            }

            $trip->setDriver($this->getUser());
            $trip->setStatus(TripStatus::Active);
            $this->capSeatsToVehicle($trip);

            $em->persist($trip);
            $em->flush();

            $this->addFlash('success', 'Votre trajet est publie.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        return $this->render('trip/new.html.twig', ['tripForm' => $form]);
    }

    // ── MODIFIER (uniquement un trajet a venir) ────────────────────────────
    #[Route('/trajets/{id}/modifier', name: 'app_trip_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        if (!$this->isUpcoming($trip)) {
            $this->addFlash('error', 'Ce trajet est passe ou annule : il ne peut plus etre modifie.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        // Places deja prises par des passagers confirmes
        $confirmedSeats = 0;
        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $confirmedSeats += $booking->getSeats();
            }
        }

        $form = $this->createForm(TripFormType::class, $trip, ['driver' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trip->getDepartureAt() <= new \DateTimeImmutable()) {
                $this->addFlash('error', 'La nouvelle date de depart doit etre dans le futur.');

                return $this->render('trip/edit.html.twig', [
                    'trip'           => $trip,
                    'tripForm'       => $form,
                    'confirmedSeats' => $confirmedSeats,
                ]);
            }

            $this->capSeatsToVehicle($trip);

            // availableSeats = places restantes. On ne touche pas aux places deja confirmees.
            $trip->setStatus($trip->getAvailableSeats() > 0 ? TripStatus::Active : TripStatus::Full);

            $em->flush();

            $this->addFlash('success', 'Trajet mis a jour.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        return $this->render('trip/edit.html.twig', [
            'trip'           => $trip,
            'tripForm'       => $form,
            'confirmedSeats' => $confirmedSeats,
        ]);
    }

    // ── MARQUER COMME TERMINE (apres l'heure de depart) ────────────────────
    #[Route('/trajets/{id}/terminer', name: 'app_trip_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        $back = $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);

        if (!$this->isCsrfTokenValid('complete-trip-' . $trip->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        if (!in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true)) {
            $this->addFlash('error', 'Ce trajet ne peut pas etre marque comme termine.');

            return $back;
        }

        if ($trip->getDepartureAt() > new \DateTimeImmutable()) {
            $this->addFlash('error', 'Vous pourrez terminer ce trajet apres l\'heure de depart.');

            return $back;
        }

        $trip->setStatus(TripStatus::Completed);

        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $booking->setStatus(BookingStatus::Completed);
            } elseif ($booking->getStatus() === BookingStatus::Pending) {
                // Une demande jamais acceptee n'a plus de sens apres le depart
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledBy(CancelledBy::Driver);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        $driver = $trip->getDriver();
        $driver->setTripsCompleted(($driver->getTripsCompleted() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Trajet termine. Vous pouvez maintenant noter vos passagers.');

        return $back;
    }

    // ── PAGE PUBLIQUE D'UN TRAJET ──────────────────────────────────────────
    #[Route('/trajets/{id}', name: 'app_trip_show', requirements: ['id' => '\d+'])]
    public function show(Trip $trip, TripMapFactory $mapFactory): Response
    {
        if ($trip->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Ce trajet n\'existe pas.');
        }

        $reviews = $trip->getDriver()->getReviewsReceived()->filter(
            fn ($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );

        $avgRating = null;
        if (count($reviews) > 0) {
            $avgRating = round(
                array_sum(array_map(fn ($r) => $r->getRating(), $reviews->toArray())) / count($reviews),
                1
            );
        }

        // Si je suis passager de ce trajet, on retrouve ma reservation active
        $myBooking = null;
        $user      = $this->getUser();
        if ($user !== null) {
            foreach ($trip->getBookings() as $b) {
                if ($b->getPassenger() === $user && $b->getStatus() !== BookingStatus::Cancelled) {
                    $myBooking = $b;
                    break;
                }
            }
        }

        return $this->render('trip/show.html.twig', [
            'trip'        => $trip,
            'avgRating'   => $avgRating,
            'reviewCount' => count($reviews),
            'reviews'     => array_slice($reviews->toArray(), 0, 3),
            'isUpcoming'  => $this->isUpcoming($trip),
            'myBooking'   => $myBooking,
            'tripMap'     => $mapFactory->create($trip),
        ]);
    }

    // ── ANNULER LE TRAJET (conducteur) ─────────────────────────────────────
    #[Route('/trajets/{id}/annuler', name: 'app_trip_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        if (!$this->isCsrfTokenValid('cancel-trip-' . $trip->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        if (!$this->isUpcoming($trip)) {
            $this->addFlash('error', 'Seul un trajet a venir peut etre annule.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        $trip->setStatus(TripStatus::Cancelled);

        foreach ($trip->getBookings() as $booking) {
            if (in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledBy(CancelledBy::Driver);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        $driver = $trip->getDriver();
        $driver->setTripsCancelled(($driver->getTripsCancelled() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Trajet annule. Vos passagers ont ete prevenus.');

        return $this->redirectToRoute('app_rides', ['onglet' => 'annules']);
    }

    // ── HELPERS ────────────────────────────────────────────────────────────
    private function assertDriver(Trip $trip): void
    {
        $user = $this->getUser();
        if ($user === null || $trip->getDriver()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }

    /** Un trajet "a venir" = ouvert (actif ou complet) ET pas encore parti. */
    private function isUpcoming(Trip $trip): bool
    {
        return in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true)
            && $trip->getDepartureAt() > new \DateTimeImmutable();
    }

    private function capSeatsToVehicle(Trip $trip): void
    {
        if ($trip->getVehicle() === null) {
            return;
        }

        $maxSeats = $trip->getVehicle()->getSeats() - 1;
        if ($trip->getAvailableSeats() > $maxSeats) {
            $trip->setAvailableSeats($maxSeats);
        }
    }
}
