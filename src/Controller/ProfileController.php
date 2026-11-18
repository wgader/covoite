<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    // ── PROFIL PUBLIC ──────────────────────────────────────────────────────
    #[Route('/profil/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'])]
    public function show(User $user, TripRepository $tripRepo): Response
    {
        if ($user->getDeletedAt() !== null || $user->getSuspendedAt() !== null) {
            throw $this->createNotFoundException('Ce profil n\'existe pas.');
        }

        $reviews = $user->getReviewsReceived()->filter(
            fn($r) => $r->getDeletedAt() === null && $r->getSuspendedAt() === null
        );

        $avgRating = null;
        if (count($reviews) > 0) {
            $avgRating = round(
                array_sum(array_map(fn($r) => $r->getRating(), $reviews->toArray())) / count($reviews),
                1
            );
        }

        $trips = $tripRepo->findBy(
            ['driver' => $user],
            ['departureAt' => 'DESC'],
            5
        );

        return $this->render('profile/show.html.twig', [
            'user'      => $user,
            'reviews'   => array_slice($reviews->toArray(), 0, 5),
            'avgRating' => $avgRating,
            'trips'     => $trips,
        ]);
    }

    // ── EDITION DE MON PROFIL ──────────────────────────────────────────────
    #[Route('/compte/profil/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès !');
            return $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);
        }

        return $this->render('profile/edit.html.twig', [
            'editForm' => $form,
        ]);
    }
}