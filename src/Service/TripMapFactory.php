<?php

namespace App\Service;

use App\Entity\Trip;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

/**
 * Construit la carte (Symfony UX Map + Leaflet) d'un trajet : un repere au depart,
 * un repere a l'arrivee. Le trace de la route est ajoute ensuite en JavaScript
 * (voir templates/trip/_route_map.html.twig).
 *
 * Renvoie null si le trajet n'a pas de coordonnees GPS (anciens trajets saisis a la main).
 */
final class TripMapFactory
{
    // CARTO "Voyager" : le plus joli, mais une cle (gratuite) est obligatoire depuis septembre 2026.
    // Cle a demander sur https://carto.com/basemaps/apikey/ puis a mettre dans .env.local
    private const CARTO_URL         = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key=%s';
    private const CARTO_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>';

    // Sans cle : fond Esri gris clair, rapide et gratuit (les noms de lieux sont une 2e couche ajoutee en JS)
    private const ESRI_URL          = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}';
    private const ESRI_LABELS_URL   = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}';
    private const ESRI_ATTRIBUTION  = 'Tiles &copy; Esri &mdash; Esri, HERE, Garmin, &copy; OpenStreetMap';

    public function __construct(
        // Variable d'environnement CARTO_BASEMAPS_KEY (vide = on utilise Esri)
        #[Autowire(env: 'CARTO_BASEMAPS_KEY')]
        private readonly string $cartoKey = '',
    ) {
    }

    public function create(Trip $trip): ?Map
    {
        $from = $this->point($trip->getDepartureLat(), $trip->getDepartureLng());
        $to   = $this->point($trip->getArrivalLat(), $trip->getArrivalLng());

        if ($from === null || $to === null) {
            return null;
        }

        $map = (new Map())
            // centre + zoom obligatoires, mais fitBoundsToMarkers() recadre sur les 2 reperes
            ->center(new Point(($from->getLatitude() + $to->getLatitude()) / 2, ($from->getLongitude() + $to->getLongitude()) / 2))
            ->zoom(8)
            ->fitBoundsToMarkers()
            ->options(new LeafletOptions(tileLayer: $this->tileLayer()))
            // Lu par le JavaScript pour tracer la route
            ->extra([
                'route' => [
                    'from' => [$from->getLatitude(), $from->getLongitude()],
                    'to'   => [$to->getLatitude(), $to->getLongitude()],
                ],
                // Couche des noms de lieux, seulement pour le fond Esri
                'labels' => $this->cartoKey === '' ? self::ESRI_LABELS_URL : null,
            ]);

        $map->addMarker(new Marker(
            position: $from,
            title: 'Depart : ' . $trip->getDepartureCity(),
            infoWindow: new InfoWindow(
                headerContent: '<strong>Depart · ' . $trip->getDepartureAt()->format('H:i') . '</strong>',
                content: htmlspecialchars($trip->getDepartureCity() . ' — ' . $trip->getDepartureAddress()),
            ),
            id: 'departure',
            icon: Icon::svg($this->pin(filled: false)), // taille definie dans le <svg>
        ));

        $map->addMarker(new Marker(
            position: $to,
            title: 'Arrivee : ' . $trip->getArrivalCity(),
            infoWindow: new InfoWindow(
                headerContent: '<strong>Arrivee</strong>',
                content: htmlspecialchars($trip->getArrivalCity() . ' — ' . $trip->getArrivalAddress()),
            ),
            id: 'arrival',
            icon: Icon::svg($this->pin(filled: true)),
        ));

        return $map;
    }

    private function tileLayer(): TileLayer
    {
        if ($this->cartoKey !== '') {
            return new TileLayer(
                url: sprintf(self::CARTO_URL, rawurlencode($this->cartoKey)),
                attribution: self::CARTO_ATTRIBUTION,
                options: ['maxZoom' => 19, 'subdomains' => 'abcd'],
            );
        }

        return new TileLayer(
            url: self::ESRI_URL,
            attribution: self::ESRI_ATTRIBUTION,
            options: ['maxZoom' => 16], // Esri gris clair s'arrete au zoom 16
        );
    }

    private function point(?string $lat, ?string $lng): ?Point
    {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }

        return new Point((float) $lat, (float) $lng);
    }

    /**
     * Repere rond aux couleurs de Covoite (24 x 24 px).
     * UX Map centre les icones SVG sur le point GPS : un rond tombe donc pile au bon endroit.
     * Depart = rond blanc cercle de vert fonce, arrivee = rond vert fonce avec coeur vert lime.
     */
    private function pin(bool $filled): string
    {
        $inner = $filled
            ? '<circle cx="12" cy="12" r="9" fill="#0F2D24"/><circle cx="12" cy="12" r="4" fill="#8DC63F"/>'
            : '<circle cx="12" cy="12" r="8" fill="#fff" stroke="#0F2D24" stroke-width="4"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">'
            . '<circle cx="12" cy="12" r="11.5" fill="#fff" opacity=".9"/>'
            . $inner
            . '</svg>';
    }
}
