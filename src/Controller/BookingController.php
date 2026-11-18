<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\TripStatus;
use App\Form\BookingFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    // ── CREER UNE RESERVATION ──────────────────────────────────────────────
    #[Route('/trajets/{id}/reserver', name: 'app_booking_create', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $back = $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);

        if ($trip->getDriver() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas reserver votre propre trajet.');

            return $back;
        }

        if ($trip->getStatus() !== TripStatus::Active
            || $trip->getDeletedAt() !== null
            || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce trajet n\'est plus disponible a la reservation.');

            return $back;
        }

        if ($trip->getAvailableSeats() < 1) {
            $this->addFlash('error', 'Il n\'y a plus de place sur ce trajet.');

            return $back;
        }

        // Une seule reservation active par passager et par trajet
        foreach ($trip->getBookings() as $existing) {
            if ($existing->getPassenger() === $user
                && in_array($existing->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                $this->addFlash('info', 'Vous avez deja une reservation sur ce trajet.');

                return $this->redirectToRoute('app_ride_passenger', ['id' => $existing->getId()]);
            }
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

            $this->addFlash('success', 'Demande envoyee. Le conducteur va vous repondre.');

            return $this->redirectToRoute('app_ride_passenger', ['id' => $booking->getId()]);
        }

        return $this->render('booking/new.html.twig', [
            'trip'        => $trip,
            'bookingForm' => $form,
        ]);
    }

    // ── ANCIENNE PAGE DETAIL -> redirige vers "Mes trajets" ────────────────
    #[Route('/reservations/{id}', name: 'app_booking_show', requirements: ['id' => '\d+'])]
    public function show(Booking $booking): Response
    {
        return $this->redirectToRoute('app_ride_passenger', ['id' => $booking->getId()]);
    }

    // ── ANNULER MA RESERVATION (passager) ──────────────────────────────────
    #[Route('/reservations/{id}/annuler', name: 'app_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($booking->getPassenger() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $back = $this->redirectToRoute('app_ride_passenger', ['id' => $booking->getId()]);

        if (!$this->isCsrfTokenValid('cancel-booking-' . $booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        $trip = $booking->getTrip();

        if (!in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)
            || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
            $this->addFlash('error', 'Cette reservation ne peut plus etre annulee.');

            return $back;
        }

        $this->releaseSeats($booking);

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledBy(CancelledBy::Passenger);
        $booking->setCancelledAt(new \DateTimeImmutable());
        $booking->setCancelledReason($request->request->getString('reason') ?: null);

        $em->flush();

        $this->addFlash('success', 'Votre reservation est annulee.');

        return $this->redirectToRoute('app_rides', ['onglet' => 'annules']);
    }

    // ── ACCEPTER UNE DEMANDE (conducteur) ──────────────────────────────────
    #[Route('/reservations/{id}/confirmer', name: 'app_booking_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirm(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $trip = $booking->getTrip();

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $back = $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);

        if (!$this->isCsrfTokenValid('confirm-booking-' . $booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        if ($booking->getStatus() !== BookingStatus::Pending || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
            $this->addFlash('error', 'Cette demande ne peut plus etre acceptee.');

            return $back;
        }

        if ($booking->getSeats() > $trip->getAvailableSeats()) {
            $this->addFlash('error', 'Il ne reste pas assez de places pour accepter cette demande.');

            return $back;
        }

        $booking->setStatus(BookingStatus::Confirmed);
        $trip->setAvailableSeats($trip->getAvailableSeats() - $booking->getSeats());

        if ($trip->getAvailableSeats() === 0) {
            $trip->setStatus(TripStatus::Full);
        }

        $em->flush();

        $this->addFlash('success', $booking->getPassenger()->getFirstName() . ' fait maintenant partie du trajet.');

        return $back;
    }

    // ── REFUSER UNE DEMANDE (conducteur) ───────────────────────────────────
    #[Route('/reservations/{id}/refuser', name: 'app_booking_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuse(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $trip = $booking->getTrip();

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $back = $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);

        if (!$this->isCsrfTokenValid('refuse-booking-' . $booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        if ($booking->getStatus() !== BookingStatus::Pending) {
            $this->addFlash('error', 'Cette demande a deja ete traitee.');

            return $back;
        }

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledBy(CancelledBy::Driver);
        $booking->setCancelledAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Demande refusee.');

        return $back;
    }

    // ── RETIRER UN PASSAGER (conducteur, avant le depart) ──────────────────
    #[Route('/reservations/{id}/retirer-passager', name: 'app_booking_cancel_driver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelByDriver(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $trip = $booking->getTrip();

        if ($trip->getDriver() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $back = $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);

        if (!$this->isCsrfTokenValid('cancel-booking-driver-' . $booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        if ($booking->getStatus() !== BookingStatus::Confirmed || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce passager ne peut plus etre retire.');

            return $back;
        }

        $this->releaseSeats($booking);

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledBy(CancelledBy::Driver);
        $booking->setCancelledAt(new \DateTimeImmutable());
        $booking->setCancelledReason($request->request->getString('reason') ?: null);

        $em->flush();

        $this->addFlash('success', $booking->getPassenger()->getFirstName() . ' a ete retire du trajet. Ses places sont de nouveau disponibles.');

        return $back;
    }

    /** Rend les places d'une reservation confirmee et rouvre le trajet s'il etait complet. */
    private function releaseSeats(Booking $booking): void
    {
        if ($booking->getStatus() !== BookingStatus::Confirmed) {
            return;
        }

        $trip = $booking->getTrip();
        $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getSeats());

        if ($trip->getStatus() === TripStatus::Full) {
            $trip->setStatus(TripStatus::Active);
        }
    }
}
