<?php

namespace App\Repository;

use App\Entity\Trip;
use App\Entity\User;
use App\Enum\TripStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Depart / arrivee : si on connait le point GPS choisi ($fromPoint = [lat, lng]), on cherche les
     * trajets qui partent DANS UN RAYON autour de ce point (comme BlaBlaCar). Sinon on compare le nom de ville.
     *
     * @param string     $sort      'depart' (le plus tot d'abord) ou 'prix' (le moins cher d'abord)
     * @param float[]|null $fromPoint [latitude, longitude] du depart choisi, ou null
     * @param float[]|null $toPoint   [latitude, longitude] de l'arrivee choisie, ou null
     *
     * @return Trip[]
     */
    public function search(
        string $from = '',
        string $to = '',
        string $date = '',
        int $seats = 1,
        string $sort = 'depart',
        ?array $fromPoint = null,
        ?array $toPoint = null,
        float $radiusKm = 20,
    ): array {
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
        // On echappe % et _ : sinon taper "%" dans la recherche renverrait TOUS les trajets
        if ($fromPoint !== null) {
            $this->nearPoint($qb, 'departure', $fromPoint, $radiusKm, $from);
        } elseif ($from !== '') {
            $qb->andWhere('t.departureCity LIKE :from')->setParameter('from', '%' . $this->escapeLike($from) . '%');
        }
        if ($toPoint !== null) {
            $this->nearPoint($qb, 'arrival', $toPoint, $radiusKm, $to);
        } elseif ($to !== '') {
            $qb->andWhere('t.arrivalCity LIKE :to')->setParameter('to', '%' . $this->escapeLike($to) . '%');
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

    /**
     * Trajets ouverts du meme conducteur qui partent a moins de $hours heures de $departureAt.
     * Sert a refuser une annonce en double (double clic, ou la meme annonce republiee)
     * et deux trajets qui se chevauchent : on ne peut pas conduire deux voitures a la fois.
     *
     * @return Trip[]
     */
    public function findOverlapping(User $driver, \DateTimeImmutable $departureAt, ?int $excludeId = null, int $hours = 2): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.driver = :driver')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.status IN (:open)')
            ->andWhere('t.departureAt BETWEEN :start AND :end')
            ->setParameter('driver', $driver)
            ->setParameter('open', [TripStatus::Active->value, TripStatus::Full->value])
            ->setParameter('start', $departureAt->modify('-' . $hours . ' hours'))
            ->setParameter('end', $departureAt->modify('+' . $hours . ' hours'))
            ->orderBy('t.departureAt', 'ASC');

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Garde les trajets dont le point ($side = 'departure' ou 'arrival') est dans un CARRE de
     * $km autour de $point. C'est un pre-filtre rapide en SQL (pas de trigonometrie en DQL) :
     * la vraie distance (cercle) est ensuite calculee en PHP par le controleur.
     *   1 degre de latitude  = 111 km partout
     *   1 degre de longitude = 111 km x cos(latitude) (les meridiens se rapprochent vers les poles)
     * Les vieux trajets sans coordonnees GPS sont gardes si le nom de ville correspond.
     */
    private function nearPoint(QueryBuilder $qb, string $side, array $point, float $km, string $city): void
    {
        [$lat, $lng] = $point;
        $dLat = $km / 111.0;
        $dLng = $km / (111.32 * max(0.1, cos(deg2rad($lat))));

        $inBox = sprintf('(t.%1$sLat BETWEEN :%1$sLatMin AND :%1$sLatMax AND t.%1$sLng BETWEEN :%1$sLngMin AND :%1$sLngMax)', $side);
        $qb->setParameter($side . 'LatMin', $lat - $dLat)
            ->setParameter($side . 'LatMax', $lat + $dLat)
            ->setParameter($side . 'LngMin', $lng - $dLng)
            ->setParameter($side . 'LngMax', $lng + $dLng);

        if ($city === '') {
            $qb->andWhere($inBox);

            return;
        }

        // Parentheses exterieures obligatoires : sinon le OR "deborde" sur les autres conditions du WHERE
        $qb->andWhere(sprintf('(%2$s OR (t.%1$sLat IS NULL AND t.%1$sCity LIKE :%1$sCity))', $side, $inBox))
            ->setParameter($side . 'City', '%' . $this->escapeLike($city) . '%');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
