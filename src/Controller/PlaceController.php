<?php

namespace App\Controller;

use App\Service\PlaceSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Petite API JSON utilisee par l'autocompletion des champs "depart" / "arrivee" (assets/places.js).
 *
 * Le navigateur appelle NOTRE serveur, et c'est PHP qui appelle Geoapify :
 * la cle API reste secrete et les reponses sont mises en cache.
 */
#[Route('/api/lieux')]
final class PlaceController extends AbstractController
{
    // GET /api/lieux?q=123 avenue de la rep&lat=45.83&lng=1.26
    #[Route('', name: 'api_places', methods: ['GET'])]
    public function search(
        PlaceSearch $places,
        Request $request,
        // 60 recherches par minute et par adresse IP : protege les API gratuites (et notre quota)
        #[Autowire(service: 'limiter.places')]
        RateLimiterFactoryInterface $placesLimiter,
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] string $mode = PlaceSearch::MODE_ALL,
        #[MapQueryParameter] string $lat = '',
        #[MapQueryParameter] string $lng = '',
    ): JsonResponse {
        if (!$placesLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de recherches. Patientez quelques secondes.'], 429);
        }

        $mode  = $mode === PlaceSearch::MODE_CITY ? PlaceSearch::MODE_CITY : PlaceSearch::MODE_ALL;
        [$la, $lo] = $this->coordinates($lat, $lng);

        $found = $places->search($q, $mode, $la, $lo); // le texte est nettoye dans PlaceSearch::clean()

        return $this->cached(new JsonResponse($found), 300);
    }

    // GET /api/lieux/inverse?lat=45.83&lng=1.26  (bouton "Utiliser ma position")
    #[Route('/inverse', name: 'api_places_reverse', methods: ['GET'])]
    public function reverse(
        PlaceSearch $places,
        #[MapQueryParameter] string $lat = '',
        #[MapQueryParameter] string $lng = '',
    ): JsonResponse {
        [$la, $lo] = $this->coordinates($lat, $lng);
        if ($la === null) {
            return new JsonResponse(['error' => 'Coordonnees invalides.'], 400);
        }

        $place = $places->reverse($la, $lo);

        return $place === null
            ? new JsonResponse(['error' => 'Aucun lieu trouve ici.'], 404)
            : $this->cached(new JsonResponse(['result' => $place]), 3600);
    }

    // GET /api/lieux/position  (position approximative d'apres l'IP, sans demander l'autorisation GPS)
    #[Route('/position', name: 'api_places_position', methods: ['GET'])]
    public function position(PlaceSearch $places, Request $request): JsonResponse
    {
        return $this->cached(new JsonResponse($places->locateIp($request->getClientIp()) ?? new \stdClass()), 3600);
    }

    /** "45.83" + "1.26" -> [45.83, 1.26], ou [null, null] si absent / hors limites. */
    private function coordinates(string $lat, string $lng): array
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return [null, null];
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        return abs($lat) <= 90 && abs($lng) <= 180 ? [$lat, $lng] : [null, null];
    }

    /** Le navigateur peut garder la reponse quelques minutes (private = pas dans un cache partage). */
    private function cached(JsonResponse $response, int $seconds): JsonResponse
    {
        $response->setPrivate();
        $response->setMaxAge($seconds);

        return $response;
    }
}
