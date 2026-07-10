<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TipRevision;
use App\Entity\User;
use App\Enum\RevisionStatus;
use App\Enum\VoteDecision;
use App\Repository\CommitteeVoteRepository;
use App\Repository\TipRevisionRepository;
use App\Service\TipReviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CommitteeController extends AbstractController
{
    #[Route('/committee', name: 'committee_queue', methods: ['GET'])]
    #[IsGranted('ROLE_COMMITTEE')]
    public function queue(TipRevisionRepository $revisionRepository, CommitteeVoteRepository $voteRepository): Response
    {
        /** @var User $member */
        $member = $this->getUser();

        $items = [];
        foreach ($revisionRepository->findPending() as $revision) {
            $for = 0;
            $against = 0;
            foreach ($revision->getVotes() as $vote) {
                $vote->getDecision() === VoteDecision::FOR ? $for++ : $against++;
            }

            $items[] = [
                'revision' => $revision,
                'for' => $for,
                'against' => $against,
                'hasVoted' => $voteRepository->hasVoted($revision, $member),
            ];
        }

        return $this->render('committee/queue.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/committee/{id}/vote', name: 'committee_vote', methods: ['POST'])]
    #[IsGranted('ROLE_COMMITTEE')]
    public function vote(
        TipRevision $revision,
        Request $request,
        TipReviewService $reviewService,
        CommitteeVoteRepository $voteRepository,
    ): Response {
        /** @var User $member */
        $member = $this->getUser();

        if (!$this->isCsrfTokenValid('vote-' . $revision->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('committee_queue');
        }

        if ($revision->getStatus() !== RevisionStatus::PENDING) {
            $this->addFlash('error', 'Cette révision a déjà été tranchée par le comité.');

            return $this->redirectToRoute('committee_queue');
        }

        if ($voteRepository->hasVoted($revision, $member)) {
            $this->addFlash('error', 'Tu as déjà voté sur ce tip.');

            return $this->redirectToRoute('committee_queue');
        }

        $decision = VoteDecision::tryFromName((string) $request->request->get('decision'));
        $comment = trim((string) $request->request->get('comment'));

        if (!$decision) {
            $this->addFlash('error', 'Choisis "pour" ou "contre".');

            return $this->redirectToRoute('committee_queue');
        }

        if ($decision === VoteDecision::AGAINST && $comment === '') {
            $this->addFlash('error', 'Un commentaire est obligatoire en cas de vote contre.');

            return $this->redirectToRoute('committee_queue');
        }

        $reviewService->vote($revision, $member, $decision, $comment !== '' ? $comment : null);

        $this->addFlash('success', 'Vote enregistré.');

        return $this->redirectToRoute('committee_queue');
    }
}
