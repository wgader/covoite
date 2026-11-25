<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Changer son mot de passe.
 *
 * Securite : l'URL ne contient AUCUN id. On modifie toujours $this->getUser(), l'utilisateur
 * de la session. Impossible donc de changer le mot de passe de quelqu'un d'autre en
 * bricolant l'URL ou la requete, et l'ancien mot de passe est redemande.
 */
#[IsGranted('ROLE_USER')]
final class PasswordController extends AbstractController
{
    #[Route('/compte/mot-de-passe', name: 'app_password', methods: ['GET', 'POST'])]
    public function change(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        // 5 essais par quart d'heure : empeche de deviner l'ancien mot de passe en boucle
        #[Autowire(service: 'limiter.sensitive')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$limiter->create('password-' . $user->getId())->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives. Reessayez dans 15 minutes.');

                return $this->redirectToRoute('app_password');
            }

            if ($form->isValid()) {
                $new = (string) $form->get('newPassword')->getData();

                if ($hasher->isPasswordValid($user, $new)) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit etre different de l\'ancien.');

                    return $this->redirectToRoute('app_password');
                }

                $user->setPassword($hasher->hashPassword($user, $new));
                $em->flush();

                $this->addFlash('success', 'Mot de passe modifie.');

                return $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);
            }
        }

        return $this->render('account/password.html.twig', [
            'passwordForm' => $form,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }
}
