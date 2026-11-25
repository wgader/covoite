<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Verifie le compte AVANT d'accepter la connexion (branche dans config/packages/security.yaml).
 * Un compte supprime ou suspendu ne peut plus se connecter, meme avec le bon mot de passe.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getDeletedAt() !== null) {
            // Meme message qu'un mauvais mot de passe : on ne revele pas qu'un compte a existe
            throw new CustomUserMessageAccountStatusException('Identifiants invalides.');
        }

        if ($user->getSuspendedAt() !== null) {
            throw new CustomUserMessageAccountStatusException('Ce compte est suspendu. Contactez l\'equipe Covoite pour en savoir plus.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void
    {
    }
}
