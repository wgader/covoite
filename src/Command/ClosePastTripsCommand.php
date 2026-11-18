<?php

namespace App\Command;

use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:close-past-trips',
    description: 'Marque automatiquement les trajets passés comme terminés',
)]
class ClosePastTripsCommand extends Command
{
    public function __construct(
        private TripRepository $tripRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        // Tous les trajets actifs dont la date de départ est passée
        $trips = $this->tripRepository->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.departureAt < :now')
            ->setParameter('status', TripStatus::Active)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;

        foreach ($trips as $trip) {
            $trip->setStatus(TripStatus::Completed);

            foreach ($trip->getBookings() as $booking) {
                if ($booking->getStatus() === BookingStatus::Confirmed) {
                    $booking->setStatus(BookingStatus::Completed);
                }
                if ($booking->getStatus() === BookingStatus::Pending) {
                    $booking->setStatus(BookingStatus::Cancelled);
                    $booking->setCancelledAt($now);
                }
            }

            $driver = $trip->getDriver();
            $driver->setTripsCompleted(($driver->getTripsCompleted() ?? 0) + 1);

            $count++;
        }

        $this->em->flush();

        $io->success("{$count} trajet(s) marqué(s) comme terminé(s).");

        return Command::SUCCESS;
    }
}