<?php
namespace App\Enum;

enum NotificationType: string
{
    case Delay = 'delay';
    case BookingConfirmed = 'booking_confirmed';
    case BookingCancelled = 'booking_cancelled';
    case TripCancelled = 'trip_cancelled';
    case NewMessage = 'new_message';
    case NewReview = 'new_review';
}