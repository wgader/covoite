<?php

namespace App\EventListener;

use App\Service\PastTripsCloser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * "Cron du pauvre" : APRES l'envoi d'une reponse (le visiteur n'attend pas), on termine
 * les trajets passes. Le cache garantit qu'on ne le fait qu'une fois toutes les 15 minutes.
 * En production, on peut a la place lancer  bin/console app:close-past-trips  avec un vrai cron.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class CloseTripsOnTerminate
{
    public function __construct(
        private readonly PastTripsCloser $closer,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        try {
            // Le calcul n'est execute que si l'element de cache a expire (toutes les 15 min)
            $this->cache->get('close_past_trips', function (ItemInterface $item): int {
                $item->expiresAfter(900);

                return $this->closer->closeAll();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Fermeture automatique des trajets : {message}', ['message' => $e->getMessage()]);
        }
    }
}
