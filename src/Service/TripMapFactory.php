<?php

namespace App\Service;

use App\Entity\Trip;
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
    // Fond de carte CARTO "Voyager" : plus doux et lisible que le fond OpenStreetMap par defaut
    private const TILES_URL         = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
    private const TILES_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>';

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
            ->options(new LeafletOptions(
                tileLayer: new TileLayer(
                    url: self::TILES_URL,
                    attribution: self::TILES_ATTRIBUTION,
                    options: ['maxZoom' => 19, 'subdomains' => 'abcd'],
                ),
            ))
            // Lu par le JavaScript pour tracer la route
            ->extra([
                'route' => [
                    'from' => [$from->getLatitude(), $from->getLongitude()],
                    'to'   => [$to->getLatitude(), $to->getLongitude()],
                ],
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
