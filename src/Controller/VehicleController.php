<?php

namespace App\Controller;

use App\Entity\Vehicle;
use App\Form\VehicleFormType;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-vehicules')]
final class VehicleController extends AbstractController
{
    #[Route('', name: 'app_vehicle_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $this->getUser()->getVehicles(),
        ]);
    }

    #[Route('/nouveau', name: 'app_vehicle_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $vehicle = new Vehicle();
        $form    = $this->createForm(VehicleFormType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicle->setOwner($this->getUser());

            // Si c'est le seul véhicule, le définir comme default automatiquement
            if ($this->getUser()->getVehicles()->isEmpty()) {
                $vehicle->setIsDefault(true);
            }

            // Si marqué default, désactiver l'ancien default
            if ($vehicle->isDefault()) {
                foreach ($this->getUser()->getVehicles() as $v) {
                    $v->setIsDefault(false);
                }
            }

            $em->persist($vehicle);
            $em->flush();

            $this->addFlash('success', 'Véhicule ajouté avec succès !');
            return $this->redirectToRoute('app_vehicle_index');
        }

        return $this->render('vehicle/form.html.twig', [
            'vehicleForm' => $form,
            'vehicle'     => null,
            'title'       => 'Ajouter un véhicule',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_vehicle_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Vehicle $vehicle, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($vehicle->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(VehicleFormType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si marqué default, désactiver l'ancien default
            if ($vehicle->isDefault()) {
                foreach ($this->getUser()->getVehicles() as $v) {
                    if ($v->getId() !== $vehicle->getId()) {
                        $v->setIsDefault(false);
                    }
                }
            }

            $em->flush();
            $this->addFlash('success', 'Véhicule modifié avec succès !');
            return $this->redirectToRoute('app_vehicle_index');
        }

        return $this->render('vehicle/form.html.twig', [
            'vehicleForm' => $form,
            'vehicle'     => $vehicle,
            'title'       => 'Modifier le véhicule',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_vehicle_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Vehicle $vehicle,
        Request $request,
        EntityManagerInterface $em,
        TripRepository $tripRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($vehicle->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-vehicle-' . $vehicle->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_vehicle_index');
        }

        // Vérifier qu'aucun trajet actif n'utilise ce véhicule
        $tripsCount = $tripRepo->count(['vehicle' => $vehicle]);
        if ($tripsCount > 0) {
            $this->addFlash('error', 'Ce véhicule est utilisé dans des trajets, vous ne pouvez pas le supprimer.');
            return $this->redirectToRoute('app_vehicle_index');
        }

        $em->remove($vehicle);
        $em->flush();

        $this->addFlash('success', 'Véhicule supprimé.');
        return $this->redirectToRoute('app_vehicle_index');
    }
}