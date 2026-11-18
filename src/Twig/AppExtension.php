<?php

namespace App\Twig;

use App\Enum\LuggageSize;
use App\Enum\MusicGenre;
use App\Repository\MessageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    private const DAYS_SHORT   = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    private const DAYS_LONG    = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const MONTHS_SHORT = ['janv.', 'fevr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'aout', 'sept.', 'oct.', 'nov.', 'dec.'];
    private const MONTHS_LONG  = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

    public function __construct(
        private readonly MessageRepository $messageRepo,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // {{ trip.departureAt|date_fr }}          -> "lun. 19 oct."
            // {{ trip.departureAt|date_fr('long') }}  -> "lundi 19 octobre"
            new TwigFilter('date_fr', [$this, 'formatDateFr']),
            // {{ trip.luggageAllowed|luggage_label }} -> "Bagage moyen"
            new TwigFilter('luggage_label', [$this, 'luggageLabel']),
            // {{ user.musicGenre|music_label }}       -> "Rap"
            new TwigFilter('music_label', [$this, 'musicLabel']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return 0;
        }

        return $this->messageRepo->countUnreadFor($user);
    }

    public function formatDateFr(?\DateTimeInterface $date, string $format = 'short'): string
    {
        if ($date === null) {
            return '';
        }

        $dayIndex   = (int) $date->format('w');      // 0 = dimanche
        $monthIndex = (int) $date->format('n') - 1;  // 0 = janvier
        $day        = $date->format('j');

        if ($format === 'long') {
            return self::DAYS_LONG[$dayIndex] . ' ' . $day . ' ' . self::MONTHS_LONG[$monthIndex];
        }

        if ($format === 'long_year') {
            return self::DAYS_LONG[$dayIndex] . ' ' . $day . ' ' . self::MONTHS_LONG[$monthIndex] . ' ' . $date->format('Y');
        }

        return self::DAYS_SHORT[$dayIndex] . ' ' . $day . ' ' . self::MONTHS_SHORT[$monthIndex];
    }

    public function luggageLabel(?LuggageSize $size): string
    {
        return match ($size) {
            LuggageSize::None   => 'Pas de bagage',
            LuggageSize::Small  => 'Petit bagage',
            LuggageSize::Medium => 'Bagage moyen',
            LuggageSize::Large  => 'Grand bagage',
            LuggageSize::Xl     => 'Tres grand bagage',
            null                => 'Non precise',
        };
    }

    public function musicLabel(?MusicGenre $genre): string
    {
        return match ($genre) {
            MusicGenre::Rap       => 'Rap',
            MusicGenre::Rnb       => 'RnB',
            MusicGenre::Rock      => 'Rock',
            MusicGenre::Pop       => 'Pop',
            MusicGenre::Electro   => 'Electro',
            MusicGenre::Classique => 'Classique',
            MusicGenre::Jazz      => 'Jazz',
            MusicGenre::Variete   => 'Variete',
            MusicGenre::Silence   => 'Plutot le silence',
            MusicGenre::Any       => 'Tout me va',
            null                  => 'Pas de preference',
        };
    }
}
