<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'ancien "tableau de bord" est remplace par la page "Mes trajets".
 * On garde la route /compte pour que les anciens liens et redirections
 * continuent de fonctionner : elle renvoie simplement vers /mes-trajets.
 */
final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->redirectToRoute('app_rides');
    }
}
