<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TipRepository;
use App\Repository\UserRepository;
use App\Service\ContributorRankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContributorController extends AbstractController
{
    #[Route('/contributors/{username}', name: 'contributor_show', methods: ['GET'])]
    public function show(
        string $username,
        UserRepository $userRepository,
        TipRepository $tipRepository,
        ContributorRankingService $rankingService,
    ): Response {
        $contributor = $userRepository->findOneBy(['username' => $username]);

        if ($contributor === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('contributor/show.html.twig', [
            'contributor' => $contributor,
            'ranking' => $rankingService->getForUser($contributor),
            'tips' => $tipRepository->findPublishedByAuthor($contributor),
        ]);
    }
}
