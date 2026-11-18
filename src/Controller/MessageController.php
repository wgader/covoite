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
    // ── BOITE DE RECEPTION ────────────────────────────────────────────────
    #[Route('', name: 'app_messages', methods: ['GET'])]
    public function inbox(MessageRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('message/inbox.html.twig', [
            'conversations' => $repo->findConversationsFor($this->getUser()),
        ]);
    }

    // ── THREAD AVEC UN UTILISATEUR ─────────────────────────────────────────
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

        // Trouver ou creer la conversation entre les deux utilisateurs
        $conversation = $repo->findConversationBetween($me, $partner);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setType(ConversationType::Direct);
            $conversation->setCreatedAt(new \DateTimeImmutable());

            $cpMe = new ConversationParticipant();
            $cpMe->setUser($me);
            $cpMe->setConversation($conversation);
            $cpMe->setJoinedAt(new \DateTimeImmutable());

            $cpPartner = new ConversationParticipant();
            $cpPartner->setUser($partner);
            $cpPartner->setConversation($conversation);
            $cpPartner->setJoinedAt(new \DateTimeImmutable());

            $em->persist($conversation);
            $em->persist($cpMe);
            $em->persist($cpPartner);
            $em->flush();
        }

        // Marquer la conversation comme lue pour moi
        $myCp = null;
        $partnerCp = null;
        foreach ($conversation->getParticipants() as $cp) {
            if ($cp->getUser()->getId() === $me->getId()) {
                $myCp = $cp;
            } else {
                $partnerCp = $cp;
            }
        }

        if ($myCp) {
            $myCp->setLastReadAt(new \DateTimeImmutable());
            $em->flush();
        }

        // Envoi d'un message
        if ($request->isMethod('POST')) {
            $content = trim($request->request->get('body', ''));
            $token   = $request->request->get('_token');

            if (!$this->isCsrfTokenValid('send-message', $token)) {
                $this->addFlash('error', 'Token invalide. Reessayez.');
                return $this->redirectToRoute('app_message_thread', ['id' => $partner->getId()]);
            }

            if (strlen($content) < 5) {
                $this->addFlash('error', 'Le message est trop court (5 caracteres minimum).');
                return $this->redirectToRoute('app_message_thread', ['id' => $partner->getId()]);
            }

            // Rattacher le trajet a la conversation si c'est le premier message avec contexte
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

            // Mettre a jour mon lastReadAt apres envoi
            if ($myCp) {
                $myCp->setLastReadAt(new \DateTimeImmutable());
            }

            $em->flush();

            return $this->redirectToRoute('app_message_thread', ['id' => $partner->getId()]);
        }

        // Contexte trajet (depuis la page d'un trajet : ?trip=X)
        $tripId = $request->query->getInt('trip');
        $trip   = ($tripId > 0 && $conversation->getTrip() === null)
            ? $em->find(Trip::class, $tripId)
            : $conversation->getTrip();

        // lastReadAt du partenaire pour savoir si mes messages ont ete lus
        $partnerLastRead = $partnerCp ? $partnerCp->getLastReadAt() : null;

        return $this->render('message/thread.html.twig', [
            'partner'         => $partner,
            'thread'          => $repo->findMessagesInConversation($conversation),
            'trip'            => $trip,
            'partnerLastRead' => $partnerLastRead,
        ]);
    }

    // ── SUPPRIMER UN MESSAGE (soft-delete) ────────────────────────────────
    #[Route('/{id}/supprimer', name: 'app_message_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('delete-message-' . $message->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_messages');
        }

        // Seul l'expediteur peut supprimer son message
        if ($message->getSender()->getId() !== $me->getId()) {
            throw $this->createAccessDeniedException();
        }

        $message->setDeletedAt(new \DateTimeImmutable());
        $message->setDeletedBy($me->getId());
        $em->flush();

        $this->addFlash('success', 'Message supprime.');
        return $this->redirectToRoute('app_messages');
    }
}