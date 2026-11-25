<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TripStatus;
use App\Form\ProfileFormType;
use App\Repository\TripRepository;
use App\Service\AvatarUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    // ── PROFIL (public) + edition directe si c'est le mien ─────────────────
    #[Route('/profil/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(User $user, Request $request, TripRepository $tripRepo, EntityManagerInterface $em, AvatarUploader $avatars): Response
    {
        if ($user->getDeletedAt() !== null || $user->getSuspendedAt() !== null) {
            throw $this->createNotFoundException('Ce profil n\'existe pas.');
        }

        $me    = $this->getUser();
        $isMe  = $me !== null && $me->getId() === $user->getId();
        $form  = null;

        // Le formulaire n'existe QUE si je regarde mon propre profil
        if ($isMe) {
            $form = $this->createForm(ProfileFormType::class, $user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Photo : nouvelle photo envoyee, ou case "Supprimer ma photo" cochee
                $file = $form->get('avatarFile')->getData();
                if ($file !== null || $form->get('removeAvatar')->getData()) {
                    $old = $user->getAvatar();
                    try {
                        $user->setAvatar($file !== null ? $avatars->upload($file) : null);
                        $avatars->remove($old);
                    } catch (\Throwable) {
                        $this->addFlash('error', 'La photo n\'a pas pu etre enregistree. Essayez avec une autre image.');
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Profil mis a jour.');

                return $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);
            }

            if ($form->isSubmitted()) {
                // Formulaire invalide : Doctrine garde les mauvaises valeurs en memoire
                // mais on ne flush pas, donc rien n'est enregistre en base.
                $em->refresh($user);
            }
        } elseif ($request->isMethod('POST')) {
            throw $this->createAccessDeniedException();
        }

        $reviews = $user->getReviewsReceived()->filter(
            fn ($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );

        $avgRating = null;
        if (count($reviews) > 0) {
            $avgRating = round(
                array_sum(array_map(fn ($r) => $r->getRating(), $reviews->toArray())) / count($reviews),
                1
            );
        }

        // Prochains trajets publies par cette personne
        $upcomingTrips = $tripRepo->createQueryBuilder('t')
            ->where('t.driver = :user')
            ->andWhere('t.status = :status')
            ->andWhere('t.departureAt > :now')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('status', TripStatus::Active)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.departureAt', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        return $this->render('profile/show.html.twig', [
            'user'          => $user,
            'isMe'          => $isMe,
            'profileForm'   => $form,
            'editing'       => $form !== null && $form->isSubmitted() && !$form->isValid(),
            'reviews'       => array_slice($reviews->toArray(), 0, 6),
            'reviewCount'   => count($reviews),
            'avgRating'     => $avgRating,
            'upcomingTrips' => $upcomingTrips,
        ], new Response(null, $form !== null && $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    // ── RACCOURCI "Mon profil" (et compatibilite avec l'ancien lien) ───────
    #[Route('/compte/profil', name: 'app_profile_me')]
    #[Route('/compte/profil/modifier', name: 'app_profile_edit')]
    public function me(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->redirectToRoute('app_profile_show', ['id' => $this->getUser()->getId()]);
    }
}
