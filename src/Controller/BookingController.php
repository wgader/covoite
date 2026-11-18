<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Form\BookingFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    // ── CRÉER UNE RÉSERVATION ──────────────────────────────────────────────
    #[Route('/trajets/{id}/reserver', name: 'app_booking_create', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if ($trip->getDriver() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre trajet.');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if (!in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full]) || $trip->getDeletedAt() !== null) {
            $this->addFlash('error', 'Ce trajet n\'est plus disponible.');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if ($trip->getAvailableSeats() < 1) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles sur ce trajet.');
            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        $form = $this->createForm(BookingFormType::class, null, [
            'max_seats' => $trip->getAvailableSeats(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $seats  = $form->get('seats')->getData();
            $amount = round((float) $trip->getPrice() * $seats, 2);
            $fee    = round($amount * 0.10, 2);

            $booking = new Booking();
            $booking->setTrip($trip);
            $booking->setPassenger($user);
            $booking->setSeats($seats);
            $booking->setAmount((string) $amount);
            $booking->setPlatformFee((string) $fee);
            $booking->setDriverEarns((string) round($amount - $fee, 2));
            $booking->setStatus(BookingStatus::Pending);
            $booking->setMessage($form->get('message')->getData());
            $booking->setCreatedAt(new \DateTimeImmutable());

            $em->persist($booking);
            $em->flush();

            $this->addFlash('success', 'Votre demande de réservation a bien été envoyée ! Le conducteur va confirmer votre place.');
            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        return $this->render('booking/new.html.twig', [
            'trip'        => $trip,
            'bookingForm' => $form,
        ]);
    }

    // ── DÉTAIL D'UNE RÉSERVATION ───────────────────────────────────────────
    #[Route('/reservations/{id}', name: 'app_booking_show', requirements: ['id' => '\d+'])]
    public function show(Booking $booking): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getPassenger() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        return $this->render('booking/show.html.twig', ['booking' => $booking]);
    }

    // ── ANNULER UNE RÉSERVATION (passager) ────────────────────────────────
    #[Route('/reservations/{id}/annuler', name: 'app_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getPassenger() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('cancel-booking-' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        if (!in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed])) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()]);
        }

        $trip = $booking->getTrip();

        // Si confirmée : restituer les places + remettre le trajet en Active si Full
        if ($booking->getStatus() === BookingStatus::Confirmed) {
            $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getSeats());

            if ($trip->getStatus() === TripStatus::Full) {
                $trip->setStatus(TripStatus::Active);
            }
        }

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Votre réservation a été annulée.');
        return $this->redirectToRoute('app_account');
    }

    // ── CONFIRMER UNE RÉSERVATION (conducteur) ────────────────────────────
    #[Route('/reservations/{id}/confirmer', name: 'app_booking_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirm(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getTrip()->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('confirm-booking-' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_account');
        }

        if ($booking->getStatus() !== BookingStatus::Pending) {
            $this->addFlash('error', 'Cette réservation ne peut plus être confirmée.');
            return $this->redirectToRoute('app_account');
        }

        $trip = $booking->getTrip();

        $booking->setStatus(BookingStatus::Confirmed);
        $newAvailable = $trip->getAvailableSeats() - $booking->getSeats();
        $trip->setAvailableSeats(max(0, $newAvailable));

        // Si plus aucune place disponible → trajet complet
        if ($trip->getAvailableSeats() === 0) {
            $trip->setStatus(TripStatus::Full);
        }

        $em->flush();

        $this->addFlash('success', 'Réservation confirmée. ' . $booking->getPassenger()->getFirstName() . ' est maintenant passager.');
        return $this->redirectToRoute('app_account');
    }

    // ── REFUSER UNE RÉSERVATION (conducteur) ──────────────────────────────
    #[Route('/reservations/{id}/refuser', name: 'app_booking_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuse(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getTrip()->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('refuse-booking-' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_account');
        }

        if ($booking->getStatus() !== BookingStatus::Pending) {
            $this->addFlash('error', 'Cette réservation a déjà été traitée.');
            return $this->redirectToRoute('app_account');
        }

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Réservation refusée.');
        return $this->redirectToRoute('app_account');
    }

    // ── RETIRER UN PASSAGER (conducteur) ──────────────────────────────────
    #[Route('/reservations/{id}/retirer-passager', name: 'app_booking_cancel_driver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelByDriver(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getTrip()->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('cancel-booking-driver-' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide. Réessayez.');
            return $this->redirectToRoute('app_trip_show', ['id' => $booking->getTrip()->getId()]);
        }

        if (!in_array($booking->getStatus(), [BookingStatus::Confirmed, BookingStatus::Pending])) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_trip_show', ['id' => $booking->getTrip()->getId()]);
        }

        $wasConfirmed  = $booking->getStatus() === BookingStatus::Confirmed;
        $passengerName = $booking->getPassenger()->getFirstName();
        $trip          = $booking->getTrip();

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledAt(new \DateTimeImmutable());

        // Si confirmée : restituer les places + remettre en Active si Full
        if ($wasConfirmed) {
            $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getSeats());

            if ($trip->getStatus() === TripStatus::Full) {
                $trip->setStatus(TripStatus::Active);
            }
        }

        $em->flush();

        $this->addFlash('success', $passengerName . ' a été retiré du trajet. Les places ont été remises à disposition.');
        return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
    }
}