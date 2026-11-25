<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    // ── LISTE ──────────────────────────────────────────────────────────────
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $repo): Response
    {
        /** @var User $me */
        $me = $this->getUser();

        // On recupere la liste AVANT de tout marquer comme lu, pour pouvoir surligner les nouvelles
        $notifications = $repo->findLatestFor($me);
        $response = $this->render('notification/index.html.twig', ['notifications' => $notifications]);
        $repo->markAllRead($me);

        return $response;
    }

    // ── OUVRIR UNE NOTIFICATION (la marque comme lue puis va sur la page concernee) ──
    #[Route('/{id}', name: 'app_notification_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(Notification $notification, EntityManagerInterface $em): Response
    {
        // On ne peut ouvrir QUE ses propres notifications (sinon 403)
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true)->setReadAt(new \DateTimeImmutable());
            $em->flush();
        }

        // Le lien est toujours un chemin interne ("/..."). On refuse "//site.com" ou "https://..."
        // pour qu'une notification ne puisse jamais envoyer vers un autre site (redirection ouverte).
        $link = $notification->getLink();
        if ($link === null || !str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return $this->redirectToRoute('app_notifications');
        }

        return $this->redirect($link);
    }
}
