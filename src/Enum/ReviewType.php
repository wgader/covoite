<?php
namespace App\Enum;

enum ReviewType: string
{
    case Driver = 'driver';
    case Passenger = 'passenger';
}