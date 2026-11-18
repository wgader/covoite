<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Form\TripFormType;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    #[Route('/trajets', name: 'app_search')]
    public function search(
        TripRepository $repo,
        #[MapQueryParameter] string $from = '',
        #[MapQueryParameter] string $to = '',
        #[MapQueryParameter] string $date = '',
        #[MapQueryParameter] int $seats = 1,
    ): Response {
        $trips = $repo->search(
            trim($from),
            trim($to),
            trim($date),
            max(1, $seats)
        );

        return $this->render('trip/search.html.twig', [
            'trips' => $trips,
            'from'  => $from,
            'to'    => $to,
            'date'  => $date,
            'seats' => max(1, $seats),
            'total' => count($trips),
        ]);
    }

    #[Route('/trajets/nouveau', name: 'app_trip_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $trip = new Trip();
        $form = $this->createForm(TripFormType::class, $trip, [
            'driver' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->setDriver($this->getUser());
            $trip->setStatus(TripStatus::Active);

            if ($trip->getVehicle() !== null) {
                $maxSeats = $trip->getVehicle()->getSeats() - 1;
                if ($trip->getAvailableSeats() > $maxSeats) {
                    $trip->setAvailableSeats($maxSeats);
                }
            }

            $em->persist($trip);
            $em->flush();

            $this->addFlash('success', 'Votre trajet a été publié avec succès !');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        return $this->render('trip/new.html.twig', [
            'tripForm' => $form,
        ]);
    }

    #[Route('/trajets/{id}/modifier', name: 'app_trip_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($trip->getStatus() === TripStatus::Cancelled) {
            $this->addFlash('error', 'Impossible de modifier un trajet annulé.');
            return $this->redirectToRoute('app_account');
        }

        $confirmedSeats = 0;
        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $confirmedSeats += $booking->getSeats();
            }
        }

        $form = $this->createForm(TripFormType::class, $trip, [
            'driver' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trip->getVehicle() !== null) {
                $maxSeats = $trip->getVehicle()->getSeats() - 1;
                if ($trip->getAvailableSeats() > $maxSeats) {
                    $trip->setAvailableSeats($maxSeats);
                }
            }

            if ($trip->getAvailableSeats() < $confirmedSeats) {
                $trip->setAvailableSeats($confirmedSeats);
                $this->addFlash('info', 'Les places disponibles ont été ajustées : vous avez déjà ' . $confirmedSeats . ' passager(s) confirmé(s).');
            }

            $em->flush();

            $this->addFlash('success', 'Votre trajet a été modifié avec succès.');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        return $this->render('trip/edit.html.twig', [
            'trip'           => $trip,
            'tripForm'       => $form,
            'confirmedSeats' => $confirmedSeats,
        ]);
    }

    // ── MARQUER UN TRAJET COMME TERMINÉ ───────────────────────────────────
    #[Route('/trajets/{id}/terminer', name: 'app_trip_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('complete-trip-' . $trip->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_account');
        }

        if ($trip->getStatus() !== TripStatus::Active) {
            $this->addFlash('error', 'Ce trajet ne peut pas être marqué comme terminé.');
            return $this->redirectToRoute('app_account');
        }

        if ($trip->getDepartureAt() > new \DateTimeImmutable()) {
            $this->addFlash('error', 'Vous ne pouvez pas terminer un trajet avant sa date de départ.');
            return $this->redirectToRoute('app_account');
        }

        $trip->setStatus(TripStatus::Completed);

        // Mettre les réservations confirmées en "Completed"
        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $booking->setStatus(BookingStatus::Completed);
            }
            // Les demandes encore en attente → annulées
            if ($booking->getStatus() === BookingStatus::Pending) {
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        // Incrémenter le compteur de trajets du conducteur
        $driver = $trip->getDriver();
        $driver->setTripsCompleted(($driver->getTripsCompleted() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Trajet marqué comme terminé ! Vos passagers peuvent maintenant vous laisser un avis.');
        return $this->redirectToRoute('app_account');
    }

    #[Route('/trajets/{id}', name: 'app_trip_show', requirements: ['id' => '\d+'])]
    public function show(Trip $trip): Response
    {
        if ($trip->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Ce trajet n\'existe pas.');
        }

        $reviews = $trip->getDriver()->getReviewsReceived()->filter(
            fn($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );

        $avgRating = null;
        if (count($reviews) > 0) {
            $avgRating = round(
                array_sum(array_map(fn($r) => $r->getRating(), $reviews->toArray())) / count($reviews),
                1
            );
        }

        return $this->render('trip/show.html.twig', [
            'trip'      => $trip,
            'avgRating' => $avgRating,
            'reviews'   => array_slice($reviews->toArray(), 0, 3),
        ]);
    }

    #[Route('/trajets/{id}/annuler', name: 'app_trip_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('cancel-trip-' . $trip->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if ($trip->getStatus() === TripStatus::Cancelled) {
            $this->addFlash('error', 'Ce trajet est déjà annulé.');
            return $this->redirectToRoute('app_account');
        }

        $trip->setStatus(TripStatus::Cancelled);

        foreach ($trip->getBookings() as $booking) {
            if (in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed])) {
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        $em->flush();

        $this->addFlash('success', 'Votre trajet a été annulé. Les réservations en cours ont été annulées.');
        return $this->redirectToRoute('app_account');
    }
}