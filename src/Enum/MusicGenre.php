<?php
namespace App\Enum;

enum MusicGenre: string
{
    case Rap = 'rap';
    case Rnb = 'rnb';
    case Rock = 'rock';
    case Pop = 'pop';
    case Electro = 'electro';
    case Classique = 'classique';
    case Jazz = 'jazz';
    case Variete = 'variete';
    case Silence = 'silence';
    case Any = 'any';
}