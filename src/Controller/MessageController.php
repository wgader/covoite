<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\ConversationType;
use App\Repository\MessageRepository;
use App\Service\LivePublisher;
use App\Validator\TextRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/messages')]
final class MessageController extends AbstractController
{
    private const MAX_LENGTH = 2000;

    // ── BOITE DE RECEPTION ─────────────────────────────────────────────────
    #[Route('', name: 'app_messages', methods: ['GET'])]
    public function inbox(MessageRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('message/inbox.html.twig', [
            'conversations' => $repo->findConversationsFor($this->getUser()),
            'activePartnerId' => null,
        ]);
    }

    // ── DISCUSSION AVEC UN UTILISATEUR ─────────────────────────────────────
    #[Route('/avec/{id}', name: 'app_message_thread', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function thread(
        User $partner,
        Request $request,
        EntityManagerInterface $em,
        MessageRepository $repo,
        // 20 messages par minute maximum et par utilisateur (anti-spam), voir config/packages/rate_limiter.yaml
        #[Autowire(service: 'limiter.messages')]
        RateLimiterFactoryInterface $messagesLimiter,
        LivePublisher $live,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        if ($partner->getId() === $me->getId()) {
            return $this->redirectToRoute('app_messages');
        }
        // Compte supprime ou suspendu : on ne peut plus lui ecrire
        if ($partner->getDeletedAt() !== null || $partner->getSuspendedAt() !== null) {
            $this->addFlash('error', 'Ce membre n\'est plus disponible.');

            return $this->redirectToRoute('app_messages');
        }

        // La conversation n'est creee qu'au PREMIER message envoye (pas en ouvrant la page)
        $conversation = $repo->findConversationBetween($me, $partner);

        // ── Envoi d'un message ──
        if ($request->isMethod('POST')) {
            $back    = $this->redirectToRoute('app_message_thread', ['id' => $partner->getId()]);
            $content = trim($request->request->getString('body'));

            if (!$this->isCsrfTokenValid('send-message', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Session expiree, reessayez.');

                return $back;
            }
            if ($content === '') {
                $this->addFlash('error', 'Le message est vide.');

                return $back;
            }
            $error = TextRules::check($content, self::MAX_LENGTH);
            if ($error !== null) {
                $this->addFlash('error', $error);

                return $back;
            }
            if (!$messagesLimiter->create('user-' . $me->getId())->consume()->isAccepted()) {
                $this->addFlash('error', 'Vous envoyez trop de messages. Patientez une minute.');

                return $back;
            }

            $conversation ??= $this->createConversation($em, $me, $partner);

            // Si on ecrit depuis un trajet, on le rattache... seulement si c'est le trajet de l'un de nous deux
            $trip = $this->sharedTrip($em, $request->request->getInt('trip_id'), $me, $partner);
            if ($trip !== null && $conversation->getTrip() === null) {
                $conversation->setTrip($trip);
            }

            $msg = new Message();
            $msg->setSender($me);
            $msg->setConversation($conversation);
            $msg->setContent($content);
            $msg->setSentAt(new \DateTimeImmutable());
            $em->persist($msg);

            $this->participant($conversation, $me)?->setLastReadAt(new \DateTimeImmutable());
            $em->flush();

            // En direct : le destinataire voit le message arriver (et son compteur), sans recharger.
            // On ne transmet PAS le contenu du message : la page le recharge elle-meme depuis le serveur.
            $live->publish($partner, 'message', ['from' => $me->getId(), 'unread' => $repo->countUnreadFor($partner)]);
            $live->publish($me, 'message', ['from' => $me->getId(), 'unread' => $repo->countUnreadFor($me)]);

            return $back;
        }

        // ── Affichage : j'ouvre la conversation = je l'ai lue ──
        $myCp = $conversation !== null ? $this->participant($conversation, $me) : null;
        if ($myCp) {
            $myCp->setLastReadAt(new \DateTimeImmutable());
            $em->flush();
        }

        // Contexte trajet : celui de la conversation, sinon celui passe dans l'URL (?trip=9)
        $trip = $conversation?->getTrip() ?? $this->sharedTrip($em, $request->query->getInt('trip'), $me, $partner);

        return $this->render('message/thread.html.twig', [
            'conversations'   => $repo->findConversationsFor($me),
            'activePartnerId' => $partner->getId(),
            'partner'         => $partner,
            'thread'          => $conversation !== null ? $repo->findMessagesInConversation($conversation) : [],
            'trip'            => $trip,
            'partnerLastRead' => $conversation !== null ? $this->participant($conversation, $partner)?->getLastReadAt() : null,
            'maxLength'       => self::MAX_LENGTH,
        ]);
    }

    private function createConversation(EntityManagerInterface $em, User $me, User $partner): Conversation
    {
        $now          = new \DateTimeImmutable();
        $conversation = new Conversation();
        $conversation->setType(ConversationType::Direct);
        $conversation->setCreatedAt($now);

        foreach ([$me, $partner] as $user) {
            $cp = new ConversationParticipant();
            $cp->setUser($user);
            $cp->setConversation($conversation);
            $cp->setJoinedAt($now);
            $conversation->addParticipant($cp);
            $em->persist($cp);
        }
        $em->persist($conversation);

        return $conversation;
    }

    private function participant(Conversation $conversation, User $user): ?ConversationParticipant
    {
        foreach ($conversation->getParticipants() as $cp) {
            if ($cp->getUser()->getId() === $user->getId()) {
                return $cp;
            }
        }

        return null;
    }

    /** Un trajet (non supprime) dont l'un de nous deux est le conducteur, sinon null. */
    private function sharedTrip(EntityManagerInterface $em, int $tripId, User $me, User $partner): ?Trip
    {
        if ($tripId <= 0) {
            return null;
        }
        $trip = $em->find(Trip::class, $tripId);
        if ($trip === null || $trip->getDeletedAt() !== null) {
            return null;
        }

        return in_array($trip->getDriver()->getId(), [$me->getId(), $partner->getId()], true) ? $trip : null;
    }

    // ── SUPPRIMER UN DE MES MESSAGES (suppression "douce") ─────────────────
    #[Route('/{id}/supprimer', name: 'app_message_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        if ($message->getSender()->getId() !== $me->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Revenir dans la meme discussion apres suppression
        $partnerId = null;
        foreach ($message->getConversation()->getParticipants() as $cp) {
            if ($cp->getUser()->getId() !== $me->getId()) {
                $partnerId = $cp->getUser()->getId();
            }
        }
        $back = $partnerId
            ? $this->redirectToRoute('app_message_thread', ['id' => $partnerId])
            : $this->redirectToRoute('app_messages');

        if (!$this->isCsrfTokenValid('delete-message-' . $message->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expiree, reessayez.');

            return $back;
        }

        $message->setDeletedAt(new \DateTimeImmutable());
        $message->setDeletedBy($me->getId());
        $em->flush();

        return $back;
    }
}
