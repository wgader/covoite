<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prevenir un utilisateur : notification dans l'app (+ e-mail + evenement en direct).
 *
 *   $notifier->notify($driver, NotificationType::BookingRequested,
 *       'Jean demande 2 places pour Angouleme → Limoges.', 'app_ride_driver', ['id' => $trip->getId()]);
 *   $em->flush();
 *
 * La notification est enregistree au flush() du controleur. L'e-mail et l'evenement en direct
 * ne partent QU'APRES un flush reussi (ecouteur Doctrine postFlush) : on ne previent jamais
 * quelqu'un d'une action qui n'a finalement pas ete enregistree.
 */
#[AsDoctrineListener(event: Events::postFlush)]
final class NotificationService
{
    /** @var list<array{notification: Notification, email: bool}> en attente du flush */
    private array $pending = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly NotificationRepository $repo,
        private readonly LivePublisher $live,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    /**
     * @param string|null $route  route de la page concernee (le lien de la notification)
     * @param bool        $email  envoyer aussi un e-mail
     */
    public function notify(User $to, NotificationType $type, string $message, ?string $route = null, array $params = [], bool $email = true): void
    {
        // Compte supprime ou suspendu : personne a prevenir
        if ($to->getDeletedAt() !== null || $to->getSuspendedAt() !== null) {
            return;
        }

        $notification = (new Notification())
            ->setUser($to)
            ->setType($type)
            ->setContent($message)
            ->setLink($route !== null ? $this->urls->generate($route, $params) : null)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($notification);
        $this->pending[] = ['notification' => $notification, 'email' => $email];
    }

    /** Appele par Doctrine apres chaque flush() reussi */
    public function postFlush(): void
    {
        if ($this->pending === []) {
            return;
        }
        $pending       = $this->pending;
        $this->pending = [];

        foreach ($pending as ['notification' => $n, 'email' => $email]) {
            $user = $n->getUser();

            $this->live->publish($user, 'notification', [
                'unread' => $this->repo->countUnread($user),
                'title'  => $n->getType()->title(),
                'text'   => $n->getContent(),
            ]);

            if ($email) {
                $this->sendEmail($n);
            }
        }
    }

    private function sendEmail(Notification $n): void
    {
        $user = $n->getUser();
        $link = $n->getLink() !== null
            ? $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . ltrim($n->getLink(), '/')
            : null;

        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(Address::create($this->from))
                ->to(new Address((string) $user->getEmail(), (string) $user->getFirstName()))
                ->subject($n->getType()->title() . ' — Covoite')
                ->htmlTemplate('email/notification.html.twig')
                ->textTemplate('email/notification.txt.twig')
                ->context([
                    'firstName' => $user->getFirstName(),
                    'title'     => $n->getType()->title(),
                    'message'   => $n->getContent(),
                    'link'      => $link,
                ]));
        } catch (\Throwable $e) {
            // Serveur mail indisponible : la notification reste visible dans l'app
            $this->logger->error('E-mail de notification non envoye a {email} ({message})', [
                'email' => $user->getEmail(), 'message' => $e->getMessage(),
            ]);
        }
    }
}
