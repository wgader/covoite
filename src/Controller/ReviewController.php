<?php

namespace App\Controller;

use App\Entity\Review;
use App\Enum\BookingStatus;
use App\Enum\ReviewType;
use App\Form\ReviewFormType;
use App\Entity\Booking;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReviewController extends AbstractController
{
    #[Route('/reservations/{id}/avis', name: 'app_review_create', requirements: ['id' => '\d+'])]
    public function create(
        Booking                $booking,
        Request                $request,
        EntityManagerInterface $em,
        ReviewRepository       $reviewRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $trip = $booking->getTrip();

        $isPassenger = $booking->getPassenger() === $user;
        $isDriver    = $trip->getDriver() === $user;

        if (!$isPassenger && !$isDriver) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        // Retour vers la page du trajet dans "Mes trajets" (vue passager ou vue conducteur)
        $backUrl = $isPassenger
            ? $this->generateUrl('app_ride_passenger', ['id' => $booking->getId()])
            : $this->generateUrl('app_ride_driver', ['id' => $trip->getId()]);

        if ($booking->getStatus() !== BookingStatus::Completed) {
            $this->addFlash('error', 'Vous ne pouvez laisser un avis que sur un trajet termine.');
            return $this->redirect($backUrl);
        }

        $existing = $reviewRepo->findOneBy(['booking' => $booking, 'author' => $user]);
        if ($existing) {
            $this->addFlash('error', 'Vous avez deja laisse un avis pour ce trajet.');
            return $this->redirect($backUrl);
        }

        if ($isPassenger) {
            $type   = ReviewType::Driver;
            $target = $trip->getDriver();
        } else {
            $type   = ReviewType::Passenger;
            $target = $booking->getPassenger();
        }

        $form = $this->createForm(ReviewFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review = new Review();
            $review->setRating($form->get('rating')->getData());
            $review->setComment($form->get('comment')->getData());
            $review->setType($type);
            $review->setBooking($booking);
            $review->setAuthor($user);
            $review->setTarget($target);
            $review->setIsAutomatic(false);
            $review->setCreatedAt(new \DateTimeImmutable());

            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Votre avis a bien ete enregistre. Merci !');
            return $this->redirect($backUrl);
        }

        return $this->render('review/create.html.twig', [
            'booking'    => $booking,
            'trip'       => $trip,
            'target'     => $target,
            'type'       => $type,
            'reviewForm' => $form,
            'backUrl'    => $backUrl,
        ]);
    }
}