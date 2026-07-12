<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Report;
use App\Entity\Tip;
use App\Entity\User;
use App\Enum\ReportStatus;
use App\Enum\TipStatus;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReportController extends AbstractController
{
    #[Route('/tips/{id}/report', name: 'tip_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function report(
        Tip $tip,
        Request $request,
        EntityManagerInterface $entityManager,
        ReportRepository $reportRepository,
    ): Response {
        if ($tip->getStatus() !== TipStatus::PUBLISHED) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('report-' . $tip->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $reason = trim((string) $request->request->get('reason'));

        if ($reason === '') {
            $this->addFlash('error', 'Précise la raison du signalement.');

            return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
        }

        $existing = $reportRepository->findOneBy(['tip' => $tip, 'reporter' => $user, 'status' => ReportStatus::PENDING]);
        if ($existing !== null) {
            $this->addFlash('error', 'Tu as déjà signalé ce tip, le comité va l\'examiner.');

            return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
        }

        $report = (new Report())
            ->setTip($tip)
            ->setReporter($user)
            ->setReason($reason);

        $entityManager->persist($report);
        $entityManager->flush();

        $this->addFlash('success', 'Signalement transmis, merci — le comité va l\'examiner.');

        return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
    }

    #[Route('/committee/reports', name: 'committee_reports', methods: ['GET'])]
    #[IsGranted('ROLE_COMMITTEE')]
    public function queue(ReportRepository $reportRepository): Response
    {
        return $this->render('committee/reports.html.twig', [
            'reports' => $reportRepository->findPending(),
        ]);
    }

    #[Route('/committee/reports/{id}/resolve', name: 'committee_report_resolve', methods: ['POST'])]
    #[IsGranted('ROLE_COMMITTEE')]
    public function resolve(Report $report, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('resolve-report-' . $report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('committee_reports');
        }

        if ($report->getStatus() !== ReportStatus::PENDING) {
            $this->addFlash('error', 'Ce signalement a déjà été traité.');

            return $this->redirectToRoute('committee_reports');
        }

        $decision = (string) $request->request->get('decision');
        $status = match ($decision) {
            'reviewed' => ReportStatus::REVIEWED,
            'dismissed' => ReportStatus::DISMISSED,
            default => null,
        };

        if ($status === null) {
            $this->addFlash('error', 'Choisis "traité" ou "classer sans suite".');

            return $this->redirectToRoute('committee_reports');
        }

        $report->setStatus($status);
        $entityManager->flush();

        $this->addFlash('success', 'Signalement mis à jour.');

        return $this->redirectToRoute('committee_reports');
    }
}
