<?php

namespace App\Enum;

/**
 * Types de notifications. Les valeurs (chaines) sont stockees en base :
 * on peut ajouter des cas, mais ne jamais renommer une valeur existante.
 */
enum NotificationType: string
{
    case Delay             = 'delay';
    case BookingRequested  = 'booking_requested';  // conducteur : nouvelle demande
    case BookingConfirmed  = 'booking_confirmed';  // passager : demande acceptee
    case BookingRefused    = 'booking_refused';    // passager : demande refusee
    case BookingCancelled  = 'booking_cancelled';  // conducteur : un passager a annule
    case PassengerRemoved  = 'passenger_removed';  // passager : retire par le conducteur
    case TripCancelled     = 'trip_cancelled';     // passager : le conducteur a annule le trajet
    case TripCompleted     = 'trip_completed';     // tous : trajet termine, laissez un avis
    case NewMessage        = 'new_message';
    case NewReview         = 'new_review';         // on a recu un avis

    /** Icone affichee dans la liste (voir templates/notification/_icon.html.twig) */
    public function icon(): string
    {
        return match ($this) {
            self::BookingRequested => 'inbox',
            self::BookingConfirmed => 'check',
            self::BookingRefused, self::BookingCancelled, self::PassengerRemoved, self::TripCancelled => 'x',
            self::TripCompleted, self::NewReview => 'star',
            self::NewMessage => 'message',
            self::Delay => 'clock',
        };
    }

    /** Titre court (objet de l'e-mail, titre dans la liste) */
    public function title(): string
    {
        return match ($this) {
            self::BookingRequested => 'Nouvelle demande de reservation',
            self::BookingConfirmed => 'Reservation acceptee',
            self::BookingRefused   => 'Reservation refusee',
            self::BookingCancelled => 'Reservation annulee',
            self::PassengerRemoved => 'Vous avez ete retire d\'un trajet',
            self::TripCancelled    => 'Trajet annule',
            self::TripCompleted    => 'Trajet termine',
            self::NewMessage       => 'Nouveau message',
            self::NewReview        => 'Nouvel avis',
            self::Delay            => 'Retard annonce',
        };
    }
}
