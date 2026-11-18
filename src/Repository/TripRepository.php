<?php

namespace App\Repository;

use App\Entity\Trip;
use App\Enum\TripStatus;
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

    /**
     * Recherche de trajets publics : ouverts, a venir, avec assez de places.
     *
     * @param string $sort 'depart' (le plus tot d'abord) ou 'prix' (le moins cher d'abord)
     *
     * @return Trip[]
     */
    public function search(string $from = '', string $to = '', string $date = '', int $seats = 1, string $sort = 'depart'): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('d', 'v')   // charge conducteur + vehicule dans la meme requete
            ->join('t.driver', 'd')
            ->leftJoin('t.vehicle', 'v')
            ->where('t.status = :status')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.departureAt > :now')
            ->andWhere('t.availableSeats >= :seats')
            ->setParameter('status', TripStatus::Active)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('seats', $seats);

        // LIKE + collation MySQL "unicode_ci" : "angouleme" trouve aussi "Angoulême"
        if ($from !== '') {
            $qb->andWhere('t.departureCity LIKE :from')->setParameter('from', '%' . $from . '%');
        }
        if ($to !== '') {
            $qb->andWhere('t.arrivalCity LIKE :to')->setParameter('to', '%' . $to . '%');
        }
        if ($date !== '') {
            try {
                $start = new \DateTimeImmutable($date . ' 00:00:00');
                $qb->andWhere('t.departureAt >= :start')
                    ->andWhere('t.departureAt < :end')
                    ->setParameter('start', $start)
                    ->setParameter('end', $start->modify('+1 day'));
            } catch (\Exception) {
                // date invalide dans l'URL : on l'ignore
            }
        }

        if ($sort === 'prix') {
            $qb->orderBy('t.price', 'ASC')->addOrderBy('t.departureAt', 'ASC');
        } else {
            $qb->orderBy('t.departureAt', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
