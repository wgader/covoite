<?php
namespace App\Enum;

enum VehicleType: string
{
    case Voiture = 'voiture';
    case Moto = 'moto';
    case Camion = 'camion';
    case Van = 'van';
    case Utilitaire = 'utilitaire';
}