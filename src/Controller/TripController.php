<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Enum\BookingStatus;
use App\Enum\CancelledBy;
use App\Enum\TripStatus;
use App\Form\TripFormType;
use App\Repository\TripRepository;
use App\Service\PlaceSearch;
use App\Service\TripMapFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    /** Rayons proposes sur la page de resultats (km) */
    private const RADII = [5, 10, 20, 30, 50];

    // ── RECHERCHE ──────────────────────────────────────────────────────────
    #[Route('/trajets', name: 'app_search')]
    public function search(
        TripRepository $repo,
        #[MapQueryParameter] string $from = '',
        #[MapQueryParameter] string $to = '',
        #[MapQueryParameter] string $date = '',
        #[MapQueryParameter] string $seats = '1',
        #[MapQueryParameter] string $tri = 'depart',
        // Remplis quand l'utilisateur choisit un lieu dans les suggestions (adresse, magasin, gare...)
        #[MapQueryParameter] string $fromCity = '',
        #[MapQueryParameter] string $fromLat = '',
        #[MapQueryParameter] string $fromLng = '',
        #[MapQueryParameter] string $toCity = '',
        #[MapQueryParameter] string $toLat = '',
        #[MapQueryParameter] string $toLng = '',
        // Rayon autour du depart / de l'arrivee choisis (en km)
        #[MapQueryParameter] string $rayon = '20',
    ): Response {
        // Tout ce qui vient de l'URL peut avoir ete modifie a la main : on nettoie et on borne
        $sort  = in_array($tri, ['depart', 'prix', 'proche'], true) ? $tri : 'depart';
        $seats = min(8, max(1, (int) $seats));
        [$from, $to, $fromCity, $toCity] = array_map(PlaceSearch::clean(...), [$from, $to, $fromCity, $toCity]);
        [$fromLat, $fromLng, $toLat, $toLng] = array_map(fn (string $c) => is_numeric($c) && abs((float) $c) <= 180 ? $c : '', [$fromLat, $fromLng, $toLat, $toLng]);
        $date  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';

        // Lieu choisi dans la liste -> on cherche sur sa ville ("Leclerc Corgnac" -> "Limoges").
        // Saisie libre -> on cherche sur le texte tape, comme avant.
        $fromSearch = trim($fromCity) !== '' ? trim($fromCity) : trim($from);
        $toSearch   = trim($toCity) !== '' ? trim($toCity) : trim($to);

        // Points GPS choisis dans les suggestions -> recherche "autour de" (rayon en km)
        $fromPoint = $fromLat !== '' && $fromLng !== '' ? [(float) $fromLat, (float) $fromLng] : null;
        $toPoint   = $toLat !== '' && $toLng !== '' ? [(float) $toLat, (float) $toLng] : null;
        $radius    = in_array((int) $rayon, self::RADII, true) ? (int) $rayon : 20;
        $hasPoint  = $fromPoint !== null || $toPoint !== null;
        if ($sort === 'proche' && !$hasPoint) {
            $sort = 'depart';
        }

        $trips = $repo->search($fromSearch, $toSearch, trim($date), $seats, $sort === 'prix' ? 'prix' : 'depart', $fromPoint, $toPoint, $radius);

        // Le SQL a garde un CARRE autour des points : on calcule la vraie distance et on garde le CERCLE
        $distances = [];
        $trips = array_values(array_filter($trips, function (Trip $t) use ($fromPoint, $toPoint, $radius, &$distances): bool {
            $d = [
                'from' => $this->kmFrom($fromPoint, $t->getDepartureLat(), $t->getDepartureLng()),
                'to'   => $this->kmFrom($toPoint, $t->getArrivalLat(), $t->getArrivalLng()),
            ];
            if (($d['from'] ?? 0) > $radius || ($d['to'] ?? 0) > $radius) {
                return false;
            }
            $distances[$t->getId()] = $d;

            return true;
        }));

        // "Le plus proche" : depart + arrivee les plus proches de ce que je cherche, puis le plus tot
        if ($sort === 'proche') {
            usort($trips, fn (Trip $a, Trip $b) => [($distances[$a->getId()]['from'] ?? 0) + ($distances[$a->getId()]['to'] ?? 0), $a->getDepartureAt()]
                <=> [($distances[$b->getId()]['from'] ?? 0) + ($distances[$b->getId()]['to'] ?? 0), $b->getDepartureAt()]);
        }

        // Note moyenne de chaque conducteur (affichee sur les cartes)
        $ratings = [];
        foreach ($trips as $trip) {
            $driver = $trip->getDriver();
            if (isset($ratings[$driver->getId()])) {
                continue;
            }
            $notes = [];
            foreach ($driver->getReviewsReceived() as $r) {
                if ($r->getDeletedAt() === null && $r->getSuspendedAt() === null) {
                    $notes[] = $r->getRating();
                }
            }
            $ratings[$driver->getId()] = $notes ? ['avg' => round(array_sum($notes) / count($notes), 1), 'count' => count($notes)] : null;
        }

        return $this->render('trip/search.html.twig', [
            'trips'   => $trips,
            'ratings' => $ratings,
            'from'    => $from,
            'to'      => $to,
            'date'    => $date,
            'seats'   => $seats,
            'sort'    => $sort,
            'total'   => count($trips),
            // Le lieu choisi est garde pour les liens de tri et la barre de recherche (sans les valeurs vides)
            'place'   => array_filter(compact('fromCity', 'fromLat', 'fromLng', 'toCity', 'toLat', 'toLng'), fn ($v) => $v !== ''),
            'hasPoint'  => $hasPoint,
            'radius'    => $radius,
            'radii'     => self::RADII,
            'distances' => $distances,
        ]);
    }

    // ── PUBLIER UN TRAJET ──────────────────────────────────────────────────
    #[Route('/trajets/nouveau', name: 'app_trip_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, TripRepository $tripRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $trip = new Trip();

        // Valeurs de depart pour l'assistant : vehicule par defaut + 3 places
        $trip->setAvailableSeats(3);
        foreach ($this->getUser()->getVehicles() as $vehicle) {
            if ($vehicle->isDefault()) {
                $trip->setVehicle($vehicle);
                $trip->setAvailableSeats(min(3, max(1, $vehicle->getSeats() - 1)));
                break;
            }
        }

        $form = $this->createForm(TripFormType::class, $trip, ['driver' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $problem = $this->checkTrip($trip, $tripRepo);
            if ($problem !== null) {
                $this->addFlash('error', $problem);

                return $this->render('trip/new.html.twig', ['tripForm' => $form], new Response(null, 422));
            }

            $trip->setDriver($this->getUser());
            $trip->setStatus(TripStatus::Active);
            $this->capSeatsToVehicle($trip);

            $em->persist($trip);
            $em->flush();

            $this->addFlash('success', 'Votre trajet est publie.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        return $this->render('trip/new.html.twig', ['tripForm' => $form], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    // ── MODIFIER (uniquement un trajet a venir) ────────────────────────────
    #[Route('/trajets/{id}/modifier', name: 'app_trip_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Trip $trip, Request $request, EntityManagerInterface $em, TripRepository $tripRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        if (!$this->isUpcoming($trip)) {
            $this->addFlash('error', 'Ce trajet est passe ou annule : il ne peut plus etre modifie.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        // Places deja prises par des passagers confirmes
        $confirmedSeats = 0;
        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $confirmedSeats += $booking->getSeats();
            }
        }

        $form = $this->createForm(TripFormType::class, $trip, ['driver' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $problem = $this->checkTrip($trip, $tripRepo);
            if ($problem !== null) {
                $this->addFlash('error', $problem);

                // On reaffiche le formulaire avec ce que l'utilisateur a saisi (rien n'est enregistre : pas de flush)
                return $this->render('trip/edit.html.twig', [
                    'trip'           => $trip,
                    'tripForm'       => $form,
                    'confirmedSeats' => $confirmedSeats,
                ], new Response(null, 422));
            }

            $this->capSeatsToVehicle($trip);

            // availableSeats = places restantes. On ne touche pas aux places deja confirmees.
            $trip->setStatus($trip->getAvailableSeats() > 0 ? TripStatus::Active : TripStatus::Full);

            $em->flush();

            $this->addFlash('success', 'Trajet mis a jour.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        return $this->render('trip/edit.html.twig', [
            'trip'           => $trip,
            'tripForm'       => $form,
            'confirmedSeats' => $confirmedSeats,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    // ── MARQUER COMME TERMINE (apres l'heure de depart) ────────────────────
    #[Route('/trajets/{id}/terminer', name: 'app_trip_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        $back = $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);

        if (!$this->isCsrfTokenValid('complete-trip-' . $trip->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $back;
        }

        if (!in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true)) {
            $this->addFlash('error', 'Ce trajet ne peut pas etre marque comme termine.');

            return $back;
        }

        if ($trip->getDepartureAt() > new \DateTimeImmutable()) {
            $this->addFlash('error', 'Vous pourrez terminer ce trajet apres l\'heure de depart.');

            return $back;
        }

        $trip->setStatus(TripStatus::Completed);

        foreach ($trip->getBookings() as $booking) {
            if ($booking->getStatus() === BookingStatus::Confirmed) {
                $booking->setStatus(BookingStatus::Completed);
            } elseif ($booking->getStatus() === BookingStatus::Pending) {
                // Une demande jamais acceptee n'a plus de sens apres le depart
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledBy(CancelledBy::Driver);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        $driver = $trip->getDriver();
        $driver->setTripsCompleted(($driver->getTripsCompleted() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Trajet termine. Vous pouvez maintenant noter vos passagers.');

        return $back;
    }

    // ── PAGE PUBLIQUE D'UN TRAJET ──────────────────────────────────────────
    #[Route('/trajets/{id}', name: 'app_trip_show', requirements: ['id' => '\d+'])]
    public function show(Trip $trip, TripMapFactory $mapFactory): Response
    {
        if ($trip->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Ce trajet n\'existe pas.');
        }

        $reviews = $trip->getDriver()->getReviewsReceived()->filter(
            fn ($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );

        $avgRating = null;
        if (count($reviews) > 0) {
            $avgRating = round(
                array_sum(array_map(fn ($r) => $r->getRating(), $reviews->toArray())) / count($reviews),
                1
            );
        }

        // Si je suis passager de ce trajet, on retrouve ma reservation active
        $myBooking = null;
        $user      = $this->getUser();
        if ($user !== null) {
            foreach ($trip->getBookings() as $b) {
                if ($b->getPassenger() === $user && $b->getStatus() !== BookingStatus::Cancelled) {
                    $myBooking = $b;
                    break;
                }
            }
        }

        return $this->render('trip/show.html.twig', [
            'trip'        => $trip,
            'avgRating'   => $avgRating,
            'reviewCount' => count($reviews),
            'reviews'     => array_slice($reviews->toArray(), 0, 3),
            'isUpcoming'  => $this->isUpcoming($trip),
            'myBooking'   => $myBooking,
            'tripMap'     => $mapFactory->create($trip),
        ]);
    }

    // ── ANNULER LE TRAJET (conducteur) ─────────────────────────────────────
    #[Route('/trajets/{id}/annuler', name: 'app_trip_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->assertDriver($trip);

        if (!$this->isCsrfTokenValid('cancel-trip-' . $trip->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token invalide. Reessayez.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        if (!$this->isUpcoming($trip)) {
            $this->addFlash('error', 'Seul un trajet a venir peut etre annule.');

            return $this->redirectToRoute('app_ride_driver', ['id' => $trip->getId()]);
        }

        $trip->setStatus(TripStatus::Cancelled);

        foreach ($trip->getBookings() as $booking) {
            if (in_array($booking->getStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                $booking->setStatus(BookingStatus::Cancelled);
                $booking->setCancelledBy(CancelledBy::Driver);
                $booking->setCancelledAt(new \DateTimeImmutable());
            }
        }

        $driver = $trip->getDriver();
        $driver->setTripsCancelled(($driver->getTripsCancelled() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Trajet annule. Vos passagers ont ete prevenus.');

        return $this->redirectToRoute('app_rides', ['onglet' => 'annules']);
    }

    // ── HELPERS ────────────────────────────────────────────────────────────

    /**
     * Regles metier d'un trajet, verifiees cote serveur (le JavaScript de l'assistant peut etre
     * contourne). Renvoie le message d'erreur a afficher, ou null si tout va bien.
     */
    private function checkTrip(Trip $trip, TripRepository $tripRepo): ?string
    {
        $now = new \DateTimeImmutable();
        $at  = $trip->getDepartureAt();

        if ($at <= $now->modify('+15 minutes')) {
            return 'Le depart doit etre au moins 15 minutes dans le futur.';
        }
        if ($at > $now->modify('+1 year')) {
            return 'Vous ne pouvez pas publier un trajet a plus d\'un an.';
        }

        // Depart et arrivee au meme endroit
        $sameCity = mb_strtolower(trim($trip->getDepartureCity())) === mb_strtolower(trim($trip->getArrivalCity()));
        if ($sameCity && mb_strtolower(trim($trip->getDepartureAddress())) === mb_strtolower(trim($trip->getArrivalAddress()))) {
            return 'Le point de depart et le point d\'arrivee sont identiques.';
        }
        if ($trip->getDepartureLat() !== null && $trip->getArrivalLat() !== null && $trip->getDepartureLng() !== null && $trip->getArrivalLng() !== null) {
            $km = $this->distanceKm((float) $trip->getDepartureLat(), (float) $trip->getDepartureLng(), (float) $trip->getArrivalLat(), (float) $trip->getArrivalLng());
            if ($km < 1) {
                return 'Le depart et l\'arrivee sont a moins d\'1 km : ce n\'est pas un covoiturage.';
            }
        }

        // Annonce en double ou trajets qui se chevauchent (meme conducteur, a moins de 2 h d'ecart)
        $overlap = $tripRepo->findOverlapping($trip->getDriver() ?? $this->getUser(), $at, $trip->getId());
        if ($overlap !== []) {
            $other = $overlap[0];
            $same  = mb_strtolower($other->getDepartureCity()) === mb_strtolower($trip->getDepartureCity())
                && mb_strtolower($other->getArrivalCity()) === mb_strtolower($trip->getArrivalCity());

            return ($same ? 'Vous avez deja publie ce trajet' : 'Vous avez deja un trajet a cette heure-la')
                . ' (' . $other->getDepartureCity() . ' → ' . $other->getArrivalCity()
                . ', le ' . $other->getDepartureAt()->format('d/m') . ' a ' . $other->getDepartureAt()->format('H\hi') . ').'
                . ' Laissez au moins 2 h entre deux departs, ou modifiez l\'annonce existante.';
        }

        return null;
    }

    /** Distance (km) entre le point cherche et un point du trajet, ou null si l'un des deux est inconnu. */
    private function kmFrom(?array $point, ?string $lat, ?string $lng): ?float
    {
        if ($point === null || $lat === null || $lng === null) {
            return null;
        }

        return round($this->distanceKm($point[0], $point[1], (float) $lat, (float) $lng), 1);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function assertDriver(Trip $trip): void
    {
        $user = $this->getUser();
        if ($user === null || $trip->getDriver()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }

    /** Un trajet "a venir" = ouvert (actif ou complet) ET pas encore parti. */
    private function isUpcoming(Trip $trip): bool
    {
        return in_array($trip->getStatus(), [TripStatus::Active, TripStatus::Full], true)
            && $trip->getDepartureAt() > new \DateTimeImmutable();
    }

    private function capSeatsToVehicle(Trip $trip): void
    {
        if ($trip->getVehicle() === null) {
            return;
        }

        $maxSeats = $trip->getVehicle()->getSeats() - 1;
        if ($trip->getAvailableSeats() > $maxSeats) {
            $trip->setAvailableSeats($maxSeats);
        }
    }
}
