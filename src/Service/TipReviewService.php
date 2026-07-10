<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommitteeVote;
use App\Entity\Notification;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\RevisionStatus;
use App\Enum\TipStatus;
use App\Enum\VoteDecision;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vote du comité sur une révision et résolution (publication/rejet) une fois
 * le quorum atteint — voir ROADMAP.md, section "Modèle collaboratif".
 */
final class TipReviewService
{
    private const int QUORUM = 3;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function vote(TipRevision $revision, User $member, VoteDecision $decision, ?string $comment): void
    {
        $vote = (new CommitteeVote())
            ->setMember($member)
            ->setDecision($decision)
            ->setComment($comment);

        $revision->addVote($vote);
        $this->entityManager->persist($vote);

        $this->resolveIfDecided($revision);

        $this->entityManager->flush();
    }

    private function resolveIfDecided(TipRevision $revision): void
    {
        $votes = $revision->getVotes();

        if (count($votes) < self::QUORUM) {
            return;
        }

        $for = 0;
        $against = 0;
        foreach ($votes as $vote) {
            if ($vote->getDecision() === VoteDecision::FOR) {
                $for++;
            } else {
                $against++;
            }
        }

        // Égalité : on attend un vote de plus plutôt que de trancher sans majorité claire.
        if ($for === $against) {
            return;
        }

        $now = new \DateTimeImmutable();
        $revision->setReviewedAt($now);
        $tip = $revision->getTip();

        // Capturé avant mutation : distingue une première soumission d'une
        // modification sur un tip déjà publié, pour le bon type de
        // notification (mêmes critères que committee/queue.html.twig).
        $wasAlreadyPublished = $tip->getStatus() === TipStatus::PUBLISHED;

        if ($for > $against) {
            $revision->setStatus(RevisionStatus::APPROVED);
            $tip->setPublishedTitle($revision->getTitle());
            $tip->setPublishedContent($revision->getContent());
            $tip->setStatus(TipStatus::PUBLISHED);
            if ($tip->getPublishedAt() === null) {
                $tip->setPublishedAt($now);
            }

            $this->notify($tip, $wasAlreadyPublished ? NotificationType::EDIT_PUBLISHED : NotificationType::TIP_PUBLISHED);

            return;
        }

        $revision->setStatus(RevisionStatus::REJECTED);

        // Si le tip n'a encore jamais été publié, c'est la révision initiale
        // qui est refusée : le tip entier tombe. Si une version publiée
        // existe déjà, c'est une modification proposée qui est refusée — la
        // version publiée reste inchangée et visible.
        if ($tip->getStatus() === TipStatus::PENDING) {
            $tip->setStatus(TipStatus::REJECTED);
        }

        $this->notify($tip, $wasAlreadyPublished ? NotificationType::EDIT_REJECTED : NotificationType::TIP_REJECTED);
    }

    private function notify(Tip $tip, NotificationType $type): void
    {
        $notification = (new Notification())
            ->setRecipient($tip->getAuthor())
            ->setTip($tip)
            ->setType($type);

        $this->entityManager->persist($notification);
    }
}
