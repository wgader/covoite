<?php

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }
    public function search(string $from = '', string $to = '', string $date = '', int $seats = 1): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.driver', 'd')
            ->leftJoin('t.vehicle', 'v')
            ->where('t.status = :status')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('status', \App\Enum\TripStatus::Active);

        if ($from) {
            $qb->andWhere('t.departureCity LIKE :from')
                ->setParameter('from', '%' . $from . '%');
        }
        if ($to) {
            $qb->andWhere('t.arrivalCity LIKE :to')
                ->setParameter('to', '%' . $to . '%');
        }
        if ($date) {
            try {
                $start = new \DateTimeImmutable($date);
                $end = $start->modify('+1 day');
                $qb->andWhere('t.departureAt >= :start')
                    ->andWhere('t.departureAt < :end')
                    ->setParameter('start', $start)
                    ->setParameter('end', $end);
            } catch (\Exception) {
            }
        }

        $qb->andWhere('t.availableSeats >= :seats')
            ->setParameter('seats', $seats);

        return $qb->orderBy('t.departureAt', 'ASC')->getQuery()->getResult();
    }

    //    /**
//     * @return Trip[] Returns an array of Trip objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Trip
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
