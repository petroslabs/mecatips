<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Enum\VehicleStatus;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de la base véhicules (ROADMAP.md, section "Comité (modération)") :
 * validation des propositions, dédoublonnage/fusion des entrées proches
 * (ex. "Golf 4" / "Golf IV" / "VW Golf 4").
 */
#[IsGranted('ROLE_COMMITTEE')]
final class VehicleModerationController extends AbstractController
{
    #[Route('/committee/vehicles', name: 'committee_vehicles', methods: ['GET'])]
    public function index(VehicleRepository $vehicleRepository): Response
    {
        return $this->render('committee/vehicles.html.twig', [
            'vehicles' => $vehicleRepository->findBy([], ['label' => 'ASC']),
        ]);
    }

    #[Route('/committee/vehicles/{id}/validate', name: 'committee_vehicle_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validateVehicle(Vehicle $vehicle, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('validate-vehicle-' . $vehicle->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('committee_vehicles');
        }

        if ($vehicle->getStatus() !== VehicleStatus::PENDING) {
            $this->addFlash('error', 'Ce véhicule est déjà validé.');

            return $this->redirectToRoute('committee_vehicles');
        }

        $vehicle->setStatus(VehicleStatus::VALIDATED);
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s validé.', $vehicle->getLabel()));

        return $this->redirectToRoute('committee_vehicles');
    }

    /**
     * Fusionne $vehicle (le doublon) dans le véhicule cible choisi : tous ses
     * tips sont réaffectés, puis le doublon est supprimé. Deux flush()
     * séparés pour garantir que la réaffectation est actée en base avant la
     * suppression (le `onDelete: SET NULL` sur `Tip.vehicle` mettrait
     * silencieusement les tips à "tous véhicules" si l'ordre était inversé).
     */
    #[Route('/committee/vehicles/{id}/merge', name: 'committee_vehicle_merge', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function merge(
        Vehicle $vehicle,
        Request $request,
        VehicleRepository $vehicleRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('merge-vehicle-' . $vehicle->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('committee_vehicles');
        }

        $targetId = (int) $request->request->get('target');
        $target = $targetId > 0 ? $vehicleRepository->find($targetId) : null;

        if ($target === null || $target->getId() === $vehicle->getId()) {
            $this->addFlash('error', 'Choisis un véhicule cible différent pour la fusion.');

            return $this->redirectToRoute('committee_vehicles');
        }

        foreach ($vehicle->getTips() as $tip) {
            $tip->setVehicle($target);
        }
        $entityManager->flush();

        $entityManager->remove($vehicle);
        $entityManager->flush();

        $this->addFlash('success', sprintf('"%s" fusionné dans "%s".', $vehicle->getLabel(), $target->getLabel()));

        return $this->redirectToRoute('committee_vehicles');
    }
}
