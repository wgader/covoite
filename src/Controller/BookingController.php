<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\TripStatus;
use App\Form\BookingFormType;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use App\Validator\TextRules;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    // ── CREER UNE RESERVATION ──────────────────────────────────────────────
    #[Route('/trajets/{id}/reserver', name: 'app_booking_create', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Trip $trip, Request $request, EntityManagerInterface $em, NotificationService $notifier): Response
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
            $seats  = (int) $form->get('seats')->getData();

            // Le trajet a pu se remplir pendant que la page etait ouverte
            $em->refresh($trip);
            if ($seats > $trip->getAvailableSeats() || $trip->getStatus() !== TripStatus::Active) {
                $this->addFlash('error', 'Il ne reste plus assez de places sur ce trajet.');

                return $back;
            }
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

            $notifier->notify($trip->getDriver(), NotificationType::BookingRequested,
                $user->getFirstName() . ' demande ' . $seats . ' place' . ($seats > 1 ? 's' : '') . ' pour votre trajet '
                . $this->routeLabel($trip) . '. Acceptez ou refusez sa demande.',
                'app_ride_driver', ['id' => $trip->getId()]);

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
    public function cancel(Booking $booking, Request $request, EntityManagerInterface $em, NotificationService $notifier): Response
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

        $reason = $this->reason($request);
        if ($reason === false) {
            return $back;
        }

        $this->releaseSeats($booking);

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledBy(CancelledBy::Passenger);
        $booking->setCancelledAt(new \DateTimeImmutable());
        $booking->setCancelledReason($reason);

        $notifier->notify($trip->getDriver(), NotificationType::BookingCancelled,
            $booking->getPassenger()->getFirstName() . ' a annule sa reservation pour ' . $this->routeLabel($trip) . '.'
            . ($reason ? ' Motif : « ' . $reason . ' »' : ''),
            'app_ride_driver', ['id' => $trip->getId()]);

        $em->flush();

        $this->addFlash('success', 'Votre reservation est annulee.');

        return $this->redirectToRoute('app_rides', ['onglet' => 'annules']);
    }

    // ── ACCEPTER UNE DEMANDE (conducteur) ──────────────────────────────────
    #[Route('/reservations/{id}/confirmer', name: 'app_booking_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirm(Booking $booking, Request $request, EntityManagerInterface $em, NotificationService $notifier): Response
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

        // Transaction + verrou sur le trajet : si deux demandes sont acceptees au meme moment
        // (deux onglets, double clic), la 2e attend la 1re et relit le VRAI nombre de places.
        // Sans ca, on pourrait accepter plus de passagers qu'il n'y a de places.
        $accepted = $em->wrapInTransaction(function () use ($em, $trip, $booking): bool {
            $em->refresh($trip, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($booking);

            if ($booking->getStatus() !== BookingStatus::Pending || $booking->getSeats() > $trip->getAvailableSeats()) {
                return false;
            }

            $booking->setStatus(BookingStatus::Confirmed);
            $trip->setAvailableSeats($trip->getAvailableSeats() - $booking->getSeats());
            if ($trip->getAvailableSeats() === 0) {
                $trip->setStatus(TripStatus::Full);
            }

            return true; // wrapInTransaction() fait le flush et le commit
        });

        if (!$accepted) {
            $this->addFlash('error', 'Il ne reste pas assez de places pour accepter cette demande.');

            return $back;
        }

        $notifier->notify($booking->getPassenger(), NotificationType::BookingConfirmed,
            $trip->getDriver()->getFirstName() . ' a accepte votre demande pour ' . $this->routeLabel($trip) . '. Bon voyage !',
            'app_ride_passenger', ['id' => $booking->getId()]);
        $em->flush();

        $this->addFlash('success', $booking->getPassenger()->getFirstName() . ' fait maintenant partie du trajet.');

        return $back;
    }

    // ── REFUSER UNE DEMANDE (conducteur) ───────────────────────────────────
    #[Route('/reservations/{id}/refuser', name: 'app_booking_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuse(Booking $booking, Request $request, EntityManagerInterface $em, NotificationService $notifier): Response
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

        $notifier->notify($booking->getPassenger(), NotificationType::BookingRefused,
            $trip->getDriver()->getFirstName() . ' ne peut pas vous prendre pour ' . $this->routeLabel($trip) . '. D\'autres trajets vous attendent sur Covoite.',
            'app_ride_passenger', ['id' => $booking->getId()]);

        $em->flush();

        $this->addFlash('success', 'Demande refusee.');

        return $back;
    }

    // ── RETIRER UN PASSAGER (conducteur, avant le depart) ──────────────────
    #[Route('/reservations/{id}/retirer-passager', name: 'app_booking_cancel_driver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelByDriver(Booking $booking, Request $request, EntityManagerInterface $em, NotificationService $notifier): Response
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

        $reason = $this->reason($request);
        if ($reason === false) {
            return $back;
        }

        $this->releaseSeats($booking);

        $booking->setStatus(BookingStatus::Cancelled);
        $booking->setCancelledBy(CancelledBy::Driver);
        $booking->setCancelledAt(new \DateTimeImmutable());
        $booking->setCancelledReason($reason);

        $notifier->notify($booking->getPassenger(), NotificationType::PassengerRemoved,
            $trip->getDriver()->getFirstName() . ' vous a retire du trajet ' . $this->routeLabel($trip) . '.'
            . ($reason ? ' Motif : « ' . $reason . ' »' : ''),
            'app_ride_passenger', ['id' => $booking->getId()]);

        $em->flush();

        $this->addFlash('success', $booking->getPassenger()->getFirstName() . ' a ete retire du trajet. Ses places sont de nouveau disponibles.');

        return $back;
    }

    /** "Angouleme → Limoges (ven. 28/11 a 08h00)" */
    private function routeLabel(Trip $trip): string
    {
        return $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity() . ' (' . $trip->getDepartureAt()->format('d/m \a H\hi') . ')';
    }

    /** Motif d'annulation (optionnel). false = texte refuse (un message d'erreur est deja affiche). */
    private function reason(Request $request): string|false|null
    {
        $reason = trim($request->request->getString('reason'));
        if ($reason === '') {
            return null;
        }
        $error = TextRules::check($reason, 255);
        if ($error !== null) {
            $this->addFlash('error', 'Motif : ' . $error);

            return false;
        }

        return $reason;
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
