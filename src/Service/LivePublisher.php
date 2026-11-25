<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Envoie un evenement "en direct" au navigateur d'un utilisateur (nouveau message,
 * nouvelle notification...), via le hub Mercure integre a FrankenPHP.
 *
 * Chaque utilisateur ecoute UN sujet prive : "https://covoite.app/users/{id}".
 * Prive = le hub n'envoie l'evenement qu'aux navigateurs qui ont un cookie d'autorisation
 * pour ce sujet (pose par base.html.twig avec la fonction mercure()). Personne ne peut
 * donc ecouter les messages d'un autre utilisateur.
 *
 * Si le hub ne repond pas, on ignore l'erreur : le site marche quand meme
 * (la page se rafraichit alors toute seule de temps en temps).
 */
final class LivePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function topicFor(User|int $user): string
    {
        return 'https://covoite.app/users/' . ($user instanceof User ? $user->getId() : $user);
    }

    /** @param array<string, mixed> $data */
    public function publish(User $to, string $type, array $data = []): void
    {
        try {
            $this->hub->publish(new Update(
                self::topicFor($to),
                json_encode(['type' => $type] + $data, JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure : evenement non envoye ({message})', ['message' => $e->getMessage()]);
        }
    }
}
