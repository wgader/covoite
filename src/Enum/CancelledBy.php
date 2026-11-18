<?php
namespace App\Enum;

enum CancelledBy: string
{
    case Driver = 'driver';
    case Passenger = 'passenger';

    case Admin = 'admin';
}