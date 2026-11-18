<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\TripRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account')]
    public function index(
        BookingRepository $bookingRepo,
        TripRepository    $tripRepo,
        ReviewRepository  $reviewRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        $bookings = $bookingRepo->findBy(
            ['passenger' => $user],
            ['createdAt' => 'DESC']
        );

        $trips = $tripRepo->findBy(
            ['driver' => $user],
            ['departureAt' => 'DESC']
        );

        // IDs des réservations déjà évaluées par l'utilisateur (en tant que passager)
        $myReviews = $reviewRepo->findBy(['author' => $user]);
        $reviewedBookingIds = array_map(
            fn($r) => $r->getBooking()->getId(),
            $myReviews
        );

        return $this->render('account/index.html.twig', [
            'bookings'           => $bookings,
            'trips'              => $trips,
            'reviewedBookingIds' => $reviewedBookingIds,
        ]);
    }
}