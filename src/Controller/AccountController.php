<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AccountDeleter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    /**
     * L'ancien "tableau de bord" est remplace par la page "Mes trajets".
     * On garde /compte pour que les anciens liens continuent de fonctionner.
     */
    #[Route('/compte', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_rides');
    }

    /**
     * Supprimer MON compte. Pas d'id dans l'URL : on agit toujours sur l'utilisateur connecte.
     * Il faut le jeton CSRF + le mot de passe actuel (un onglet laisse ouvert ne suffit pas).
     */
    #[Route('/compte/supprimer', name: 'app_account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        AccountDeleter $deleter,
        UserPasswordHasherInterface $hasher,
        Security $security,
        #[Autowire(service: 'limiter.sensitive')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $back = $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);

        if (!$this->isCsrfTokenValid('delete-account', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expiree, reessayez.');

            return $back;
        }
        if (!$limiter->create('delete-' . $user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives. Reessayez dans 15 minutes.');

            return $back;
        }
        if (!$hasher->isPasswordValid($user, $request->request->getString('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : votre compte n\'a pas ete supprime.');

            return $back;
        }

        $deleter->delete($user);

        // Deconnexion (sans re-verifier de jeton : on vient de le faire) puis message d'au revoir
        $security->logout(false);
        $this->addFlash('success', 'Votre compte a ete supprime. Merci d\'avoir voyage avec Covoite.');

        return $this->redirectToRoute('app_home');
    }
}
