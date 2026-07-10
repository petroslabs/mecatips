<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Tip;
use App\Entity\User;
use App\Enum\TipStatus;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FavoriteController extends AbstractController
{
    #[Route('/tips/{id}/favorite', name: 'tip_favorite', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        Tip $tip,
        Request $request,
        EntityManagerInterface $entityManager,
        FavoriteRepository $favoriteRepository,
    ): Response {
        if ($tip->getStatus() !== TipStatus::PUBLISHED) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('favorite-' . $tip->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('tip_show', ['id' => $tip->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $favorite = $favoriteRepository->findOneBy(['tip' => $tip, 'user' => $user]);

        if ($favorite) {
            $entityManager->remove($favorite);
            $this->addFlash('success', 'Retiré de tes favoris.');
        } else {
            $favorite = (new Favorite())->setTip($tip)->setUser($user);
            $entityManager->persist($favorite);
            $this->addFlash('success', 'Ajouté à tes favoris.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('tip_show', ['id' => $tip->getId()]);
    }

    #[Route('/favorites', name: 'favorite_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(FavoriteRepository $favoriteRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('favorite/index.html.twig', [
            'favorites' => $favoriteRepository->findByUser($user),
        ]);
    }
}
