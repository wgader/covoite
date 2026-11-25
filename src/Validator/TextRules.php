<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Regles de validation reutilisees dans tous les formulaires.
 *
 * Pourquoi ? Twig echappe deja tout ce qu'il affiche (pas d'injection HTML possible) et Doctrine
 * utilise des requetes preparees (pas d'injection SQL). Mais on veut en plus refuser :
 *   - les caracteres invisibles ou de controle (retour chariot dans un nom, caractere nul...)
 *   - les textes vides, trop courts ou trop longs (sinon MySQL plante si on depasse la colonne)
 *   - les formats impossibles (un prenom "<<<>>>", un code postal "abc"...)
 *
 * Utilisation : 'constraints' => TextRules::name('prenom', required: true)
 */
final class TextRules
{
    // Une seule ligne : lettres, chiffres, ponctuation et symboles courants, emojis... mais aucun
    // caractere de controle/invisible (\p{C}) ni chevrons < > (inutiles et souvent signe d'un test d'injection)
    private const SINGLE_LINE = '/^[^\p{C}<>]*$/u';

    // Plusieurs lignes (messages, bio...) : retours a la ligne et tabulations autorises, et < > aussi
    // (on doit pouvoir ecrire "<3" ou "prix < 10 euros" : Twig les affichera comme du texte, jamais comme du HTML).
    // \x{200D} = "liaison" invisible utilisee par certains emojis (famille, drapeaux...)
    private const MULTI_LINE = '/^(?:[^\p{C}]|[\r\n\t\x{200D}])*$/u';

    // Prenom / nom : commence par une lettre, puis lettres, espaces, tirets, apostrophes, points
    private const NAME = '/^\p{L}[\p{L}\p{M}\' ’\-.]*$/u';

    // Ville : lettres, chiffres (arrondissements), espaces, tirets, apostrophes, parentheses
    private const CITY = '/^[\p{L}\p{N}][\p{L}\p{M}\p{N}\' ’\-().]*$/u';

    /** @return Constraint[] Prenom, nom... */
    public static function name(string $what, bool $required = true, int $min = 2, int $max = 50): array
    {
        return self::base($what, $required, $min, $max, [
            new Regex(pattern: self::NAME, message: ucfirst($what) . ' : uniquement des lettres, espaces, tirets et apostrophes.', normalizer: 'trim'),
        ]);
    }

    /** @return Constraint[] Ville */
    public static function city(string $what = 'la ville', bool $required = true): array
    {
        return self::base($what, $required, 1, 100, [
            new Regex(pattern: self::CITY, message: 'Nom de ville invalide. Choisissez une adresse dans la liste.'),
        ]);
    }

    /** @return Constraint[] Code postal francais, belge, suisse ou luxembourgeois (4 ou 5 chiffres) */
    public static function postalCode(bool $required = true): array
    {
        return self::base('le code postal', $required, 4, 5, [
            new Regex(pattern: '/^\d{4,5}$/', message: 'Le code postal doit contenir 4 ou 5 chiffres.', normalizer: 'trim'),
        ]);
    }

    /** @return Constraint[] Texte sur une ligne (adresse, marque, modele, couleur...) */
    public static function line(string $what, bool $required = true, int $min = 1, int $max = 255): array
    {
        return self::base($what, $required, $min, $max, [
            new Regex(pattern: self::SINGLE_LINE, message: ucfirst($what) . ' contient des caracteres non autorises.'),
        ]);
    }

    /** @return Constraint[] Texte libre sur plusieurs lignes (bio, message, commentaire...) */
    public static function text(string $what, bool $required = false, int $max = 1000, int $min = 0): array
    {
        return self::base($what, $required, $min, $max, [
            new Regex(pattern: self::MULTI_LINE, message: ucfirst($what) . ' contient des caracteres non autorises.'),
        ]);
    }

    /** @return Constraint[] Latitude ou longitude envoyee par l'autocompletion (champ cache) */
    public static function coordinate(int $limit): array
    {
        return [
            new Range(
                min: -$limit,
                max: $limit,
                notInRangeMessage: 'Position GPS invalide. Choisissez une adresse dans la liste.',
                invalidMessage: 'Position GPS invalide. Choisissez une adresse dans la liste.',
            ),
        ];
    }

    /**
     * @return Constraint[] Nouveau mot de passe : 8 caracteres minimum, majuscule, minuscule, chiffre, caractere special,
     *                      et refuse s'il apparait dans une fuite de donnees connue (haveibeenpwned).
     */
    public static function password(): array
    {
        return [
            new NotBlank(message: 'Choisissez un mot de passe.'),
            new Length(min: 8, max: 4096, minMessage: 'Au moins {{ limit }} caracteres.'),
            new Regex(
                pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])/',
                message: 'Il faut une majuscule, une minuscule, un chiffre et un caractere special.',
            ),
            // Seuls les 5 premiers caracteres du hash SHA-1 sont envoyes au service : le mot de passe ne quitte jamais le serveur.
            // Service injoignable -> on laisse passer (skipOnError).
            new NotCompromisedPassword(message: 'Ce mot de passe a deja fuite sur Internet. Choisissez-en un autre.', skipOnError: true),
        ];
    }

    /** Verifie un texte hors formulaire (motif d'annulation, message...). Renvoie un message d'erreur ou null. */
    public static function check(string $value, int $max, bool $multiline = true): ?string
    {
        if (mb_strlen($value) > $max) {
            return 'Texte trop long (' . $max . ' caracteres maximum).';
        }
        if (!preg_match($multiline ? self::MULTI_LINE : self::SINGLE_LINE, $value)) {
            return 'Le texte contient des caracteres invisibles ou non autorises.';
        }

        return null;
    }

    /** @return Constraint[] */
    private static function base(string $what, bool $required, int $min, int $max, array $extra): array
    {
        $rules = [];
        if ($required) {
            $rules[] = new NotBlank(message: 'Indiquez ' . $what . '.', normalizer: 'trim');
        }
        $rules[] = new Length(
            min: $min > 0 ? $min : null,
            max: $max,
            minMessage: ucfirst($what) . ' : {{ limit }} caracteres minimum.',
            maxMessage: ucfirst($what) . ' : {{ limit }} caracteres maximum.',
            normalizer: 'trim',
        );

        return [...$rules, ...$extra];
    }
}
