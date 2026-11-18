<?php

namespace App\Command;

use App\Entity\AdminLog;
use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime les comptes de test (emails en @test.fr par defaut) et TOUT ce qui leur est lie :
 * trajets, reservations, avis, conversations, messages, vehicules, notifications.
 *
 * Usage : php bin/console app:purge-test-data
 *         php bin/console app:purge-test-data --domain=exemple.fr
 */
#[AsCommand(
    name: 'app:purge-test-data',
    description: 'Supprime les comptes de test et toutes leurs donnees',
)]
class PurgeTestDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domaine des emails de test', 'test.fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $domain = ltrim((string) $input->getOption('domain'), '@');

        // 1) Les comptes concernes
        $users = $this->em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email LIKE :d')->setParameter('d', '%@' . $domain)
            ->getQuery()->getResult();

        if ($users === []) {
            $io->success('Aucun compte en @' . $domain . ' : rien a supprimer.');

            return Command::SUCCESS;
        }

        $io->section('Comptes qui vont etre supprimes');
        $io->listing(array_map(fn (User $u) => $u->getFirstName() . ' ' . $u->getLastName() . ' <' . $u->getEmail() . '>', $users));

        if (!$io->confirm('Supprimer ces comptes et toutes leurs donnees ? (irreversible)', false)) {
            $io->warning('Annule, rien n\'a ete supprime.');

            return Command::SUCCESS;
        }

        $userIds = array_map(fn (User $u) => $u->getId(), $users);

        // 2) On recupere les identifiants de tout ce qui est lie, puis on supprime
        //    dans l'ordre inverse des dependances (les "enfants" avant les "parents").
        $tripIds = $this->ids(Trip::class, 't', 't.driver IN (:u)', ['u' => $userIds]);

        $bookingIds = $this->ids(Booking::class, 'b', 'b.passenger IN (:u)' . ($tripIds ? ' OR b.trip IN (:t)' : ''),
            ['u' => $userIds] + ($tripIds ? ['t' => $tripIds] : []));

        $conversationIds = array_values(array_unique(array_merge(
            $this->ids(ConversationParticipant::class, 'cp', 'cp.user IN (:u)', ['u' => $userIds], 'IDENTITY(cp.conversation)'),
            $tripIds ? $this->ids(Conversation::class, 'c', 'c.trip IN (:t)', ['t' => $tripIds]) : [],
        )));

        $this->em->wrapInTransaction(function () use ($userIds, $tripIds, $bookingIds, $conversationIds, $io) {
            $count = [];

            $count['avis'] = $this->delete(Review::class, 'r',
                'r.author IN (:u) OR r.target IN (:u)' . ($bookingIds ? ' OR r.booking IN (:b)' : ''),
                ['u' => $userIds] + ($bookingIds ? ['b' => $bookingIds] : []));

            $count['reservations'] = $bookingIds ? $this->delete(Booking::class, 'b', 'b.id IN (:b)', ['b' => $bookingIds]) : 0;

            $count['messages'] = $this->delete(Message::class, 'm',
                'm.sender IN (:u)' . ($conversationIds ? ' OR m.conversation IN (:c)' : ''),
                ['u' => $userIds] + ($conversationIds ? ['c' => $conversationIds] : []));

            if ($conversationIds) {
                $this->delete(ConversationParticipant::class, 'cp', 'cp.conversation IN (:c)', ['c' => $conversationIds]);
                $count['conversations'] = $this->delete(Conversation::class, 'c', 'c.id IN (:c)', ['c' => $conversationIds]);
            }

            $count['trajets']       = $tripIds ? $this->delete(Trip::class, 't', 't.id IN (:t)', ['t' => $tripIds]) : 0;
            $count['vehicules']     = $this->delete(Vehicle::class, 'v', 'v.owner IN (:u)', ['u' => $userIds]);
            $count['notifications'] = $this->delete(Notification::class, 'n', 'n.user IN (:u)', ['u' => $userIds]);
            $this->delete(AdminLog::class, 'a', 'a.adminUser IN (:u)', ['u' => $userIds]);
            $count['comptes']       = $this->delete(User::class, 'u', 'u.id IN (:u)', ['u' => $userIds]);

            $io->table(['Element', 'Supprimes'], array_map(fn ($k, $v) => [$k, $v], array_keys($count), $count));
        });

        $io->success('Donnees de test supprimees.');

        return Command::SUCCESS;
    }

    /** @return int[] */
    private function ids(string $class, string $alias, string $where, array $params, ?string $select = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select($select ?? $alias . '.id')
            ->from($class, $alias)
            ->where($where);
        foreach ($params as $k => $v) {
            $qb->setParameter($k, $v);
        }

        // Chaque ligne est un tableau avec une seule colonne : on prend sa valeur
        return array_map(fn (array $row) => (int) reset($row), $qb->getQuery()->getScalarResult());
    }

    private function delete(string $class, string $alias, string $where, array $params): int
    {
        $qb = $this->em->createQueryBuilder()->delete($class, $alias)->where($where);
        foreach ($params as $k => $v) {
            $qb->setParameter($k, $v);
        }

        return (int) $qb->getQuery()->execute();
    }
}
