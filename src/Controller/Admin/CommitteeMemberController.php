<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des membres du comité (ROADMAP.md, section "Comité (modération)").
 * Ne gère que ROLE_COMMITTEE : accorder ROLE_ADMIN reste volontairement
 * réservé à un accès direct à la base, trop sensible pour une UI.
 */
#[IsGranted('ROLE_ADMIN')]
final class CommitteeMemberController extends AbstractController
{
    #[Route('/admin/committee', name: 'admin_committee_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/committee/index.html.twig', [
            'users' => $userRepository->findBy([], ['username' => 'ASC']),
        ]);
    }

    #[Route('/admin/committee/{id}/grant', name: 'admin_committee_grant', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function grant(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkCsrf($user, $request);

        $user->addRole('ROLE_COMMITTEE');
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s a rejoint le comité.', $user->getUsername()));

        return $this->redirectToRoute('admin_committee_index');
    }

    #[Route('/admin/committee/{id}/revoke', name: 'admin_committee_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkCsrf($user, $request);

        $user->removeRole('ROLE_COMMITTEE');
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s ne fait plus partie du comité.', $user->getUsername()));

        return $this->redirectToRoute('admin_committee_index');
    }

    private function checkCsrf(User $user, Request $request): void
    {
        if (!$this->isCsrfTokenValid('admin-committee-' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
