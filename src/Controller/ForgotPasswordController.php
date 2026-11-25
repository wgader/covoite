<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ForgotPasswordFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mot de passe oublie, SANS table supplementaire en base :
 *
 * 1. On envoie par e-mail un lien SIGNE (UriSigner : signature HMAC avec APP_SECRET) qui contient
 *    l'id de l'utilisateur, une date d'expiration (1 h) et une "empreinte" de son mot de passe actuel.
 *    Modifier un seul caractere du lien casse la signature.
 * 2. Des que le mot de passe change, l'empreinte ne correspond plus : le lien devient inutilisable.
 *    Il ne sert donc qu'UNE fois.
 * 3. Le lien n'est jamais garde dans l'adresse de la page : on le verifie, on note le resultat en
 *    session, puis on redirige vers une adresse "propre" (sinon il pourrait fuiter dans l'historique
 *    ou vers les sites externes que charge la page).
 * 4. On repond toujours la meme chose, que l'e-mail existe ou non : impossible de savoir qui est inscrit.
 */
final class ForgotPasswordController extends AbstractController
{
    private const MINUTES = 60;
    private const SESSION = 'reset_password';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    // ── 1. Demander un lien ────────────────────────────────────────────────
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $users,
        UriSigner $signer,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire(service: 'limiter.sensitive')]
        RateLimiterFactoryInterface $limiter,
        #[Autowire(env: 'MAILER_FROM')]
        string $from,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_password');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 5 demandes par quart d'heure et par connexion : pas de bombardement d'e-mails
            if (!$limiter->create('forgot-' . $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes. Reessayez dans 15 minutes.');

                return $this->redirectToRoute('app_forgot_password');
            }

            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            $user  = $users->findOneBy(['email' => $email]);

            if ($user !== null && $user->getDeletedAt() === null && $user->getSuspendedAt() === null) {
                $url = $this->generateUrl('app_reset_password_check', [
                    'id' => $user->getId(),
                    'h'  => $this->fingerprint($user),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $link = $signer->sign($url, new \DateTimeImmutable('+' . self::MINUTES . ' minutes'));

                try {
                    $mailer->send((new TemplatedEmail())
                        ->from(Address::create($from))
                        ->to(new Address((string) $user->getEmail(), (string) $user->getFirstName()))
                        ->subject('Changer votre mot de passe — Covoite')
                        ->htmlTemplate('email/reset_password.html.twig')
                        ->textTemplate('email/reset_password.txt.twig')
                        ->context(['firstName' => $user->getFirstName(), 'link' => $link, 'minutes' => self::MINUTES]));
                } catch (\Throwable $e) {
                    $logger->error('E-mail de reinitialisation non envoye ({message})', ['message' => $e->getMessage()]);
                }
            }

            // Meme message dans tous les cas (voir point 4 en haut du fichier)
            $this->addFlash('success', 'Si un compte existe avec cette adresse, vous allez recevoir un e-mail avec un lien (valable ' . self::MINUTES . ' minutes).');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    // ── 2. Le lien de l'e-mail : verification puis redirection vers une adresse propre ──
    #[Route('/mot-de-passe-oublie/lien', name: 'app_reset_password_check', methods: ['GET'])]
    public function check(Request $request, UriSigner $signer, UserRepository $users): Response
    {
        $user = $users->find($request->query->getInt('id'));

        // checkRequest() verifie la signature ET la date d'expiration
        if (!$signer->checkRequest($request) || $user === null || !hash_equals($this->fingerprint($user), $request->query->getString('h'))) {
            $this->addFlash('error', 'Ce lien n\'est plus valable (expire ou deja utilise). Demandez-en un nouveau.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $request->getSession()->set(self::SESSION, [
            'id'    => $user->getId(),
            'h'     => $this->fingerprint($user),
            'until' => time() + 15 * 60, // 15 min pour choisir le nouveau mot de passe
        ]);

        return $this->redirectToRoute('app_reset_password');
    }

    // ── 3. Choisir le nouveau mot de passe ─────────────────────────────────
    #[Route('/mot-de-passe-oublie/nouveau', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $data = $request->getSession()->get(self::SESSION);
        $user = is_array($data) ? $users->find($data['id'] ?? 0) : null;

        // Session absente, expiree, ou mot de passe deja change depuis (empreinte differente)
        if ($user === null || ($data['until'] ?? 0) < time() || !hash_equals($this->fingerprint($user), (string) ($data['h'] ?? ''))) {
            $request->getSession()->remove(self::SESSION);
            $this->addFlash('error', 'Ce lien n\'est plus valable. Demandez-en un nouveau.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('newPassword')->getData()));
            $em->flush();
            $request->getSession()->remove(self::SESSION);

            $this->addFlash('success', 'Mot de passe modifie. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form'      => $form,
            'firstName' => $user->getFirstName(),
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /** Empreinte du mot de passe actuel (change des que le mot de passe change). */
    private function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getPassword(), $this->secret), 0, 32);
    }
}
