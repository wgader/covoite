<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\TripStatus;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(TripRepository $tripRepo, EntityManagerInterface $em): Response
    {
        $now = new \DateTimeImmutable();

        // Seulement les trajets a venir, encore ouverts, avec au moins une place
        $trips = $tripRepo->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.departureAt > :now')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.availableSeats > 0')
            ->setParameter('status', TripStatus::Active)
            ->setParameter('now', $now)
            ->orderBy('t.departureAt', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $tripCount = (int) $tripRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->andWhere('t.departureAt > :now')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('status', TripStatus::Active)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('home/index.html.twig', [
            'trips'        => $trips,
            'tripCount'    => $tripCount,
            'userCount'    => $em->getRepository(User::class)->count([]),
            'bookingCount' => $em->getRepository(Booking::class)->count([]),
        ]);
    }
}
