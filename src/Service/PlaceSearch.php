<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Recherche de lieux pour l'autocompletion : adresses, villes, codes postaux, gares, magasins...
 *
 * Deux sources interrogees EN MEME TEMPS (HttpClient envoie les requetes en parallele) :
 *   - IGN (Geoplateforme)  : adresses, villes et codes postaux francais. Tres precis, sans cle.
 *   - Lieux connus (POI)   : gares, magasins, aeroports, universites, parkings...
 *       -> Geoapify si GEOAPIFY_KEY est rempli dans .env.local
 *       -> sinon Photon (komoot), gratuit et sans cle, base sur OpenStreetMap
 *
 * Les resultats sont fusionnes, dedoublonnes puis tries (voir rank()) et renvoyes au meme format :
 *   ['id', 'kind', 'label', 'detail', 'city', 'postcode', 'lat', 'lng', 'distance']
 * Ils sont gardes en cache 1 jour : retaper "Limo" ne refait aucune requete.
 */
final class PlaceSearch
{
    public const MODE_ALL  = 'tout';  // adresses, lieux connus, villes
    public const MODE_CITY = 'ville'; // villes et codes postaux uniquement

    public const MIN_LENGTH = 3;   // l'IGN refuse les recherches de moins de 3 caracteres
    public const MAX_LENGTH = 100;

    private const IGN_SEARCH       = 'https://data.geopf.fr/geocodage/search';
    private const IGN_REVERSE      = 'https://data.geopf.fr/geocodage/reverse';
    private const PHOTON_SEARCH    = 'https://photon.komoot.io/api/';
    private const GEOAPIFY_SEARCH  = 'https://api.geoapify.com/v1/geocode/autocomplete';
    private const GEOAPIFY_IP      = 'https://api.geoapify.com/v1/ipinfo';

    // Zone de recherche des lieux connus : France metropolitaine + Belgique, Suisse, Luxembourg
    private const BBOX = '-5.8,41.0,10.6,51.6';

    // Types OpenStreetMap gardes pour Photon (on laisse les rues et adresses a l'IGN)
    private const PHOTON_TAGS = ['shop', 'amenity', 'railway:station', 'railway:halt', 'public_transport', 'aeroway', 'tourism', 'leisure', 'office'];

    private const MAX_RESULTS = 8;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'GEOAPIFY_KEY')]
        private readonly string $geoapifyKey = '',
    ) {
    }

    /**
     * Nettoie le texte tape : supprime les caracteres invisibles ou speciaux (seuls lettres,
     * chiffres, espaces et  ' - . , /  sont gardes), regroupe les espaces, coupe a 100 caracteres.
     */
    public static function clean(string $query): string
    {
        $query = (string) preg_replace('/[^\p{L}\p{M}\p{N}\s\'’\-.,\/]/u', ' ', $query);
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        return mb_substr($query, 0, self::MAX_LENGTH);
    }

    // ═══════════════════════════════ RECHERCHE ═══════════════════════════════

    /** @return array{source: string, results: list<array<string, mixed>>} */
    public function search(string $query, string $mode = self::MODE_ALL, ?float $lat = null, ?float $lng = null): array
    {
        $query = self::clean($query);
        if (mb_strlen($query) < self::MIN_LENGTH || !preg_match('/[\p{L}\p{N}]/u', $query)) {
            return ['source' => 'none', 'results' => []];
        }

        // Position arrondie a ~1 km dans la cle de cache : les voisins partagent les memes reponses
        $key = 'places2_' . md5(implode('|', [
            mb_strtolower($query), $mode,
            $lat !== null ? round($lat, 2) : '-', $lng !== null ? round($lng, 2) : '-',
        ]));

        $found = $this->cache->get($key, function (ItemInterface $item) use ($query, $mode, $lat, $lng): array {
            $item->expiresAfter(86400);

            // 1) On lance toutes les requetes d'un coup (elles partent en parallele)...
            $ign = $this->request(self::IGN_SEARCH, $this->ignParams($query, $mode, $lat, $lng));
            $poi = null;
            $poiSource = 'photon';
            if ($mode === self::MODE_ALL) {
                if ($this->geoapifyKey !== '') {
                    $poiSource = 'geoapify';
                    $poi = $this->request(self::GEOAPIFY_SEARCH, $this->geoapifyParams($query, $lat, $lng));
                } else {
                    $poi = $this->request(self::PHOTON_SEARCH, $this->photonParams($query, $lat, $lng));
                }
            }

            // 2) ...puis on lit les reponses
            $ignData = $this->read($ign);
            $poiData = $poi !== null ? $this->read($poi) : [];

            if ($ignData === null || $poiData === null) {
                $item->expiresAfter(600); // une source ne repond pas : on reessaiera dans 10 min
            }

            $results = array_map($this->fromIgn(...), $ignData['features'] ?? []);
            if ($poiSource === 'geoapify') {
                $results = [...$results, ...array_map($this->fromGeoapify(...), $poiData['results'] ?? [])];
            } else {
                $results = [...$results, ...array_map($this->fromPhoton(...), $poiData['features'] ?? [])];
            }

            return [
                'source'  => $mode === self::MODE_ALL ? $poiSource : 'ign',
                'results' => array_values(array_filter($results)),
            ];
        });

        $found['results'] = $this->rank($found['results'], $query, $lat, $lng);

        return $found;
    }

    /** Adresse la plus proche d'un point GPS (bouton "Utiliser ma position"). */
    public function reverse(float $lat, float $lng): ?array
    {
        $key = 'places_rev2_' . md5(round($lat, 4) . '|' . round($lng, 4));

        return $this->cache->get($key, function (ItemInterface $item) use ($lat, $lng): ?array {
            $item->expiresAfter(86400);

            $data  = $this->read($this->request(self::IGN_REVERSE, ['lat' => $lat, 'lon' => $lng, 'limit' => 1]));
            $place = isset($data['features'][0]) ? $this->fromIgn($data['features'][0]) : null;
            if ($place === null) {
                $item->expiresAfter(60);
            }

            return $place;
        });
    }

    /**
     * Position approximative (a l'echelle de la ville) d'apres l'adresse IP. Necessite GEOAPIFY_KEY.
     *
     * @return array{lat: float, lng: float, city: string}|null
     */
    public function locateIp(?string $ip): ?array
    {
        if ($this->geoapifyKey === '') {
            return null;
        }

        // En local (Docker), l'IP vue par PHP est privee : on laisse Geoapify utiliser l'IP publique
        $isPrivate = $ip === null || IpUtils::isPrivateIp($ip);

        return $this->cache->get('places_ip_' . md5($ip ?? 'none'), function (ItemInterface $item) use ($ip, $isPrivate): ?array {
            $item->expiresAfter(86400);

            $params = ['apiKey' => $this->geoapifyKey];
            if (!$isPrivate) {
                $params['ip'] = $ip;
            }
            $data = $this->read($this->request(self::GEOAPIFY_IP, $params));

            if (!isset($data['location']['latitude'], $data['location']['longitude'])) {
                $item->expiresAfter(300);

                return null;
            }

            return [
                'lat'  => (float) $data['location']['latitude'],
                'lng'  => (float) $data['location']['longitude'],
                'city' => (string) ($data['city']['name'] ?? ''),
            ];
        });
    }

    // ═══════════════════════════════ IGN ═══════════════════════════════

    private function ignParams(string $query, string $mode, ?float $lat, ?float $lng): array
    {
        $params = ['q' => $query, 'autocomplete' => 1, 'limit' => 8];
        if ($mode === self::MODE_CITY) {
            $params['type'] = 'municipality';
        }
        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lon'] = $lng;
        }

        return $params;
    }

    private function fromIgn(array $f): ?array
    {
        $p = $f['properties'] ?? [];
        $c = $f['geometry']['coordinates'] ?? null;
        if (!is_array($c) || count($c) < 2) {
            return null;
        }

        $isCity   = ($p['type'] ?? '') === 'municipality';
        $city     = (string) ($p['city'] ?? $p['name'] ?? '');
        $postcode = (string) ($p['postcode'] ?? '');
        // context = "16, Charente, Nouvelle-Aquitaine" -> on garde le departement
        $department = trim(explode(',', (string) ($p['context'] ?? ''))[1] ?? '');

        return $this->place(
            id: 'ign-' . ($p['id'] ?? md5(json_encode($c))),
            kind: $isCity ? 'city' : 'address',
            label: $isCity ? $city : (string) ($p['name'] ?? ''),
            detail: $isCity ? $this->join([$postcode, $department]) : trim($postcode . ' ' . $city),
            city: $city,
            postcode: $postcode,
            lat: (float) $c[1],
            lng: (float) $c[0],
        );
    }

    // ═══════════════════════════════ PHOTON (lieux connus, sans cle) ═══════════════════════════════

    private function photonParams(string $query, ?float $lat, ?float $lng): array
    {
        $params = ['q' => $query, 'lang' => 'fr', 'limit' => 15, 'bbox' => self::BBOX, 'osm_tag' => self::PHOTON_TAGS];
        if ($lat !== null && $lng !== null) {
            $params += ['lat' => $lat, 'lon' => $lng, 'zoom' => 12, 'location_bias_scale' => 0.1];
        }

        return $params;
    }

    private function fromPhoton(array $f): ?array
    {
        $p = $f['properties'] ?? [];
        $c = $f['geometry']['coordinates'] ?? null;
        $name = trim((string) ($p['name'] ?? ''));
        if (!is_array($c) || count($c) < 2 || $name === '') {
            return null;
        }

        $key   = (string) ($p['osm_key'] ?? '');
        $value = (string) ($p['osm_value'] ?? '');
        $kind = match (true) {
            $key === 'railway' || in_array($value, ['station', 'bus_station', 'halt'], true) => 'station',
            $key === 'aeroway' => 'airport',
            $value === 'parking' => 'parking',
            $key === 'shop' || in_array($value, ['fuel', 'marketplace', 'fast_food', 'restaurant', 'cafe', 'bar'], true) => 'shop',
            default => 'place',
        };

        // Dans OpenStreetMap, une gare s'appelle souvent juste "Angouleme" : on precise "Gare d'Angouleme"
        if ($key === 'railway' && !preg_match('/\bgare\b/iu', $name)) {
            $name = 'Gare ' . (preg_match('/^[aeiouyhâàéèêîôûAEIOUYHÂÀÉÈÊÎÔÛ]/u', $name) ? 'd\'' : 'de ') . $name;
        }

        $city     = (string) ($p['city'] ?? $p['town'] ?? $p['village'] ?? $p['county'] ?? '');
        $postcode = (string) ($p['postcode'] ?? '');
        $street   = trim(($p['housenumber'] ?? '') . ' ' . ($p['street'] ?? ''));
        $country  = ($p['countrycode'] ?? 'FR') !== 'FR' ? ($p['country'] ?? null) : null;

        return $this->place(
            id: 'osm-' . ($p['osm_type'] ?? '') . ($p['osm_id'] ?? md5(json_encode($c))),
            kind: $kind,
            label: $name,
            detail: $this->join([$street, trim($postcode . ' ' . $city), $country], ', '),
            city: $city,
            postcode: $postcode,
            lat: (float) $c[1],
            lng: (float) $c[0],
        );
    }

    // ═══════════════════════════════ GEOAPIFY (si cle) ═══════════════════════════════

    private function geoapifyParams(string $query, ?float $lat, ?float $lng): array
    {
        $params = [
            'text' => $query, 'lang' => 'fr', 'limit' => 10, 'format' => 'json', 'type' => 'amenity',
            'filter' => 'countrycode:fr,be,ch,lu', 'apiKey' => $this->geoapifyKey,
        ];
        if ($lat !== null && $lng !== null) {
            $params['bias'] = sprintf('proximity:%F,%F', $lng, $lat); // longitude PUIS latitude
        }

        return $params;
    }

    private function fromGeoapify(array $r): ?array
    {
        if (!isset($r['lat'], $r['lon'])) {
            return null;
        }
        $category = (string) ($r['category'] ?? '');
        $kind = match (true) {
            str_contains($category, 'train') || str_contains($category, 'railway') => 'station',
            str_contains($category, 'airport') => 'airport',
            str_contains($category, 'parking') => 'parking',
            str_starts_with($category, 'commercial') || str_starts_with($category, 'catering') => 'shop',
            default => 'place',
        };

        return $this->place(
            id: 'geo-' . ($r['place_id'] ?? md5($r['lat'] . $r['lon'])),
            kind: $kind,
            label: (string) ($r['address_line1'] ?? $r['name'] ?? ''),
            detail: (string) preg_replace('/,\s*France$/u', '', (string) ($r['address_line2'] ?? '')),
            city: (string) ($r['city'] ?? ''),
            postcode: (string) ($r['postcode'] ?? ''),
            lat: (float) $r['lat'],
            lng: (float) $r['lon'],
        );
    }

    // ═══════════════════════════════ TRI ═══════════════════════════════

    /**
     * Ordre final des suggestions :
     *   1. ceux qui contiennent TOUS les mots tapes ("gare d'an" -> "Gare d'Angouleme")
     *   2. ceux dont le nom commence par le texte tape
     *   3. les villes, puis les gares / aeroports (ce qu'on cherche le plus en covoiturage)
     *   4. du plus proche au plus loin de l'utilisateur
     */
    private function rank(array $places, string $query, ?float $lat, ?float $lng): array
    {
        // Mots de 2 lettres ou plus : "gare d'an" -> ["gare", "an"]
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $this->fold($query)) ?: [],
            fn (string $w) => mb_strlen($w) >= 2,
        ));
        $q = $this->fold($query);
        $kindOrder = ['city' => 0, 'station' => 1, 'airport' => 1];

        $seen = [];
        $out  = [];
        foreach ($places as $p) {
            if ($p['label'] === '') {
                continue;
            }
            $dedupe = $this->fold($p['label'] . '|' . $p['city']);
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            // Chaque mot tape doit etre le DEBUT d'un mot du lieu ("an" trouve "Angouleme")
            $haystack = ' ' . implode(' ', preg_split('/[^\p{L}\p{N}]+/u', $this->fold($p['label'] . ' ' . $p['detail'] . ' ' . $p['city'])) ?: []);
            $matched  = 0;
            foreach ($words as $w) {
                if (str_contains($haystack, ' ' . $w)) {
                    $matched++;
                }
            }

            if ($lat !== null && $lng !== null) {
                $p['distance'] = (int) round($this->distance($lat, $lng, $p['lat'], $p['lng']));
            }
            $p['_sort'] = [
                $words === [] || $matched === count($words) ? 0 : 1,
                str_starts_with($this->fold($p['label']), $q) ? 0 : 1,
                $kindOrder[$p['kind']] ?? 2,
                $p['distance'] ?? 0,
            ];
            $out[] = $p;
        }

        usort($out, fn (array $a, array $b) => $a['_sort'] <=> $b['_sort']);

        return array_map(function (array $p) {
            unset($p['_sort']);

            return $p;
        }, array_slice($out, 0, self::MAX_RESULTS));
    }

    // ═══════════════════════════════ OUTILS ═══════════════════════════════

    private function place(string $id, string $kind, string $label, string $detail, string $city, string $postcode, float $lat, float $lng): array
    {
        return compact('id', 'kind', 'label', 'detail', 'city', 'postcode', 'lat', 'lng') + ['distance' => null];
    }

    /** Distance a vol d'oiseau en metres (formule de Haversine). */
    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** "Angoulême" -> "angouleme" pour comparer sans accents ni majuscules. */
    private function fold(string $s): string
    {
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;

        return mb_strtolower((string) preg_replace('/\p{Mn}/u', '', $s));
    }

    private function join(array $parts, string $glue = ' · '): string
    {
        return implode($glue, array_filter(array_map(fn ($p) => trim((string) $p), $parts)));
    }

    /** Prepare la requete HTTP (elle part en arriere-plan, sans attendre). */
    private function request(string $url, array $query): ?ResponseInterface
    {
        try {
            // Photon attend "osm_tag=shop&osm_tag=amenity" (cle repetee), pas "osm_tag[0]=..."
            $qs = preg_replace('/%5B\d+%5D=/', '=', http_build_query($query));

            return $this->http->request('GET', $url . '?' . $qs, [
                'timeout' => 4,
                'headers' => ['User-Agent' => 'Covoite/1.0 (projet etudiant BUT MMI)'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Lieux : {url} injoignable ({message})', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /** Lit la reponse JSON. null = l'API n'a pas repondu correctement (timeout, quota, erreur...). */
    private function read(?ResponseInterface $response): ?array
    {
        if ($response === null) {
            return null;
        }
        try {
            $code = $response->getStatusCode();
            if ($code === 400) {
                return []; // recherche refusee par l'API (texte trop court...) : simplement aucun resultat
            }
            if ($code !== 200) {
                $this->logger->warning('Lieux : {url} a repondu {code}', ['url' => $response->getInfo('url'), 'code' => $code]);

                return null;
            }

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Lieux : reponse illisible ({message})', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
