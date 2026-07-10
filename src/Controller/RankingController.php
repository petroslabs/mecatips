<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ContributorRankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RankingController extends AbstractController
{
    #[Route('/ranking', name: 'ranking_index', methods: ['GET'])]
    public function index(ContributorRankingService $rankingService): Response
    {
        return $this->render('ranking/index.html.twig', [
            'rankings' => $rankingService->getRanking(),
        ]);
    }
}
