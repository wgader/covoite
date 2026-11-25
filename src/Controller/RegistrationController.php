<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
        // Limite definie dans config/packages/rate_limiter.yaml : 5 inscriptions / heure / adresse IP
        #[Autowire(service: 'limiter.registration')]
        RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        // Deja connecte : rien a faire ici
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Anti-robot : trop de comptes crees depuis la meme connexion
            if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop d\'inscriptions depuis votre connexion. Reessayez dans une heure.');

                return $this->redirectToRoute('app_register');
            }

            $user->setFirstName($form->get('firstName')->getData());
            $user->setLastName($form->get('lastName')->getData());
            $user->setDateOfBirth($form->get('dateOfBirth')->getData());
            $user->setCguAcceptedAt(new \DateTimeImmutable());

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            $entityManager->persist($user);
            $entityManager->flush();

            // On connecte directement le nouvel utilisateur (plus besoin de retaper son mot de passe)
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Bienvenue sur Covoite, ' . $user->getFirstName() . ' !');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }
}
