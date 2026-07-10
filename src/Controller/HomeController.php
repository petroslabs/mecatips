<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(TipRepository $tipRepository): Response
    {
        $published = $tipRepository->findPublished();

        return $this->render('home/index.html.twig', [
            // Un vrai tip plutôt qu'un texte marketing pour illustrer le concept.
            'featuredTip' => $published[0] ?? null,
        ]);
    }
}
