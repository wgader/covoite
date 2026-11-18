<?php
namespace App\Enum;

enum LuggageSize: string
{
    case None = 'none';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Xl = 'xl';
}