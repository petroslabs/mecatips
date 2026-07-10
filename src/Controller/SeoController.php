<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'robots_txt', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemapUrl = $this->generateUrl('sitemap_xml', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $lines = [
            'User-agent: *',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /tips/new',
            'Disallow: /tips/mine',
            'Disallow: /tips/*/report',
            'Disallow: /tips/*/vote-useful',
            'Disallow: /committee',
            'Disallow: /committee/',
            'Disallow: /admin',
            'Disallow: /admin/',
            '',
            'Sitemap: ' . $sitemapUrl,
        ];

        return new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Construit le plan du site à partir des tips publiés plutôt que de
     * multiplier les requêtes par type de page : une seule liste de tips
     * publiés suffit à dériver les opérations, véhicules et contributeurs
     * qui ont effectivement du contenu à montrer (mêmes critères que les
     * pages elles-mêmes — pas d'URL menant à du vide dans le sitemap).
     */
    #[Route('/sitemap.xml', name: 'sitemap_xml', methods: ['GET'])]
    public function sitemap(TipRepository $tipRepository): Response
    {
        $publishedTips = $tipRepository->findPublished();

        $operations = [];
        $vehicles = [];
        $contributors = [];

        foreach ($publishedTips as $tip) {
            $operations[$tip->getCategory()->getId()] = $tip->getCategory();

            if ($tip->getVehicle() !== null) {
                $vehicles[$tip->getVehicle()->getId()] = $tip->getVehicle();
            }

            $contributors[$tip->getAuthor()->getId()] = $tip->getAuthor();
        }

        $response = $this->render('sitemap/index.xml.twig', [
            'tips' => $publishedTips,
            'operations' => $operations,
            'vehicles' => $vehicles,
            'contributors' => $contributors,
        ]);

        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}
