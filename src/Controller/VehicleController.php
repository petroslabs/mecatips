<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Repository\TipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleController extends AbstractController
{
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
