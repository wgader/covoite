<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\ConversationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Nombre de messages non lus pour l'utilisateur.
     *
     * Principe : messages dans les conversations de l'user,
     * envoyés par quelqu'un d'autre, après son dernier lastReadAt,
     * et non supprimés.
     */
    public function countUnreadFor(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'conv')
            ->join('conv.participants', 'cp_me')
            ->where('cp_me.user = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('cp_me.lastReadAt IS NULL OR m.sentAt > cp_me.lastReadAt')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne la liste des conversations de l'utilisateur pour l'inbox.
     * Chaque entrée : ['conversation', 'partner', 'lastMessage', 'unreadCount', 'trip']
     * Triées par date du dernier message DESC.
     */
    public function findConversationsFor(User $user): array
    {
        $em = $this->getEntityManager();

        /** @var ConversationParticipant[] $participations */
        $participations = $em->createQueryBuilder()
            ->select('cp', 'conv', 'trip')
            ->from(ConversationParticipant::class, 'cp')
            ->join('cp.conversation', 'conv')
            ->leftJoin('conv.trip', 'trip')
            ->where('cp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($participations as $myCp) {
            $conv = $myCp->getConversation();

            // Trouver l'interlocuteur
            $partner = null;
            foreach ($conv->getParticipants() as $cp) {
                if ($cp->getUser()->getId() !== $user->getId()) {
                    $partner = $cp->getUser();
                    break;
                }
            }

            // Dernier message non supprimé
            $lastMessage = $this->createQueryBuilder('m')
                ->where('m.conversation = :conv')
                ->andWhere('m.deletedAt IS NULL')
                ->setParameter('conv', $conv)
                ->orderBy('m.sentAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Nombre de messages non lus
            $myLastRead = $myCp->getLastReadAt();
            $unreadQb = $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.conversation = :conv')
                ->andWhere('u.sender != :me')
                ->andWhere('u.deletedAt IS NULL')
                ->setParameter('conv', $conv)
                ->setParameter('me', $user);

            if ($myLastRead) {
                $unreadQb->andWhere('u.sentAt > :lastRead')
                         ->setParameter('lastRead', $myLastRead);
            }

            $unreadCount = (int) $unreadQb->getQuery()->getSingleScalarResult();

            $result[] = [
                'conversation' => $conv,
                'partner'      => $partner,
                'lastMessage'  => $lastMessage,
                'unreadCount'  => $unreadCount,
                'trip'         => $conv->getTrip(),
            ];
        }

        // Tri : conversation avec le message le plus recent en premier
        usort($result, static function (array $a, array $b): int {
            $aTime = $a['lastMessage'] ? $a['lastMessage']->getSentAt()->getTimestamp() : 0;
            $bTime = $b['lastMessage'] ? $b['lastMessage']->getSentAt()->getTimestamp() : 0;
            return $bTime - $aTime;
        });

        return $result;
    }

    /**
     * Trouve la Conversation directe entre deux utilisateurs, ou null.
     */
    public function findConversationBetween(User $a, User $b): ?Conversation
    {
        // Cherche une conversation de type Direct dont les participants sont a ET b
        $results = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('conv')
            ->from(Conversation::class, 'conv')
            ->join('conv.participants', 'cp1')
            ->join('conv.participants', 'cp2')
            ->where('conv.type = :type')
            ->andWhere('cp1.user = :a')
            ->andWhere('cp2.user = :b')
            ->setParameter('type', ConversationType::Direct)
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getResult();

        return $results[0] ?? null;
    }

    /**
     * Tous les messages visibles d'une conversation, ordre chronologique.
     *
     * @return Message[]
     */
    public function findMessagesInConversation(Conversation $conv): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->where('m.conversation = :conv')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('conv', $conv)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}