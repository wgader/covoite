<?php
namespace App\Enum;

enum TripStatus: string
{
    case Active = 'active';
    case Full = 'full';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}