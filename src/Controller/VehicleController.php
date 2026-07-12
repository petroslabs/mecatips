<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Repository\TipRepository;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleController extends AbstractController
{
    /**
     * Alimente l'autocomplete du champ "Véhicule concerné" du formulaire de
     * soumission (ux-autocomplete, mode "remote data" + création libre) —
     * suggère les véhicules déjà connus pendant la saisie, sans empêcher de
     * taper un véhicule qui n'existe pas encore.
     */
    #[Route('/vehicles/autocomplete', name: 'vehicle_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, VehicleRepository $vehicleRepository): JsonResponse
    {
        $query = trim((string) $request->query->get('query', ''));

        $results = $query === '' ? [] : array_map(
            static fn (Vehicle $vehicle) => ['value' => $vehicle->getLabel(), 'text' => $vehicle->getLabel()],
            $vehicleRepository->searchByLabel($query),
        );

        return $this->json(['results' => $results]);
    }

    #[Route('/vehicles/{id}', name: 'vehicle_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Vehicle $vehicle, TipRepository $tipRepository): Response
    {
        $tips = $tipRepository->findPublishedForVehicle($vehicle);

        // Pas de page pour un véhicule sans aucun tip publié — qu'il soit
        // encore "pending" ou juste sans contenu, il n'y a rien à montrer.
        if ($tips === []) {
            throw $this->createNotFoundException();
        }

        return $this->render('vehicle/show.html.twig', [
            'vehicle' => $vehicle,
            'tips' => $tips,
        ]);
    }
}
