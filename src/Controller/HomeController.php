<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Booking;
use App\Enum\TripStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $trips = $em->getRepository(Trip::class)->findBy(
            ['status' => TripStatus::Active],
            ['departureAt' => 'ASC'],
            6
        );

        $tripCount    = $em->getRepository(Trip::class)->count(['status' => TripStatus::Active]);
        $userCount    = $em->getRepository(User::class)->count([]);
        $bookingCount = $em->getRepository(Booking::class)->count([]);

        return $this->render('home/index.html.twig', [
            'trips'        => $trips,
            'tripCount'    => $tripCount,
            'userCount'    => $userCount,
            'bookingCount' => $bookingCount,
        ]);
    }
}