<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\ConversationType;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        if ($partner->getId() === $me->getId()) {
            return $this->redirectToRoute('app_messages');
        }

        // Trouver la conversation entre nous deux, ou la creer
        $conversation = $repo->findConversationBetween($me, $partner);

        if (!$conversation) {
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
            $em->flush();
        }

        $myCp      = null;
        $partnerCp = null;
        foreach ($conversation->getParticipants() as $cp) {
            if ($cp->getUser()->getId() === $me->getId()) {
                $myCp = $cp;
            } else {
                $partnerCp = $cp;
            }
        }

        // ── Envoi d'un message ──
        if ($request->isMethod('POST')) {
            $back    = $this->redirectToRoute('app_message_thread', ['id' => $partner->getId()]);
            $content = trim($request->request->getString('body'));

            if (!$this->isCsrfTokenValid('send-message', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Session expiree, reessayez.');

                return $back;
            }

            if ($content === '') {
                return $back;
            }

            if (mb_strlen($content) > self::MAX_LENGTH) {
                $this->addFlash('error', 'Message trop long (' . self::MAX_LENGTH . ' caracteres maximum).');

                return $back;
            }

            // Si on ecrit depuis un trajet, on rattache ce trajet a la conversation
            $tripId = $request->request->getInt('trip_id');
            if ($tripId > 0 && $conversation->getTrip() === null) {
                $trip = $em->find(Trip::class, $tripId);
                if ($trip) {
                    $conversation->setTrip($trip);
                }
            }

            $msg = new Message();
            $msg->setSender($me);
            $msg->setConversation($conversation);
            $msg->setContent($content);
            $msg->setSentAt(new \DateTimeImmutable());
            $em->persist($msg);

            $myCp?->setLastReadAt(new \DateTimeImmutable());
            $em->flush();

            return $back;
        }

        // ── Affichage : j'ouvre la conversation = je l'ai lue ──
        if ($myCp) {
            $myCp->setLastReadAt(new \DateTimeImmutable());
            $em->flush();
        }

        // Contexte trajet : celui de la conversation, sinon celui passe dans l'URL (?trip=9)
        $trip = $conversation->getTrip();
        if ($trip === null && $request->query->getInt('trip') > 0) {
            $trip = $em->find(Trip::class, $request->query->getInt('trip'));
        }

        return $this->render('message/thread.html.twig', [
            'conversations'   => $repo->findConversationsFor($me),
            'activePartnerId' => $partner->getId(),
            'partner'         => $partner,
            'thread'          => $repo->findMessagesInConversation($conversation),
            'trip'            => $trip,
            'partnerLastRead' => $partnerCp?->getLastReadAt(),
            'maxLength'       => self::MAX_LENGTH,
        ]);
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
