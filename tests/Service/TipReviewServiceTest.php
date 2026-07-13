<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\CommitteeVote;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\RevisionStatus;
use App\Enum\TipStatus;
use App\Enum\TipType;
use App\Enum\VoteDecision;
use App\Repository\NotificationRepository;
use App\Service\TipReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Couvre la logique de vote à quorum de TipReviewService — voir ROADMAP.md,
 * section "Modèle collaboratif". Le quorum vaut 3 (impair), donc une égalité
 * ne peut jamais survenir en votant séquentiellement via vote() ; le test de
 * l'égalité contourne délibérément le service pour ajouter des votes
 * directement sur l'entité afin d'exercer cette branche.
 */
final class TipReviewServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TipReviewService $tipReviewService;
    private NotificationRepository $notificationRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->tipReviewService = self::getContainer()->get(TipReviewService::class);
        $this->notificationRepository = self::getContainer()->get(NotificationRepository::class);
    }

    public function testBelowQuorumDoesNotResolve(): void
    {
        $tip = $this->createPendingTipWithRevision();
        $revision = $tip->getRevisions()->first();

        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m1'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m2'), VoteDecision::FOR, null);

        self::assertSame(RevisionStatus::PENDING, $revision->getStatus());
        self::assertSame(TipStatus::PENDING, $tip->getStatus());
        self::assertNull($tip->getPublishedAt());
    }

    public function testMajorityForOnFirstRevisionPublishesTipAndGeneratesSlug(): void
    {
        $tip = $this->createPendingTipWithRevision('Purger le circuit de freinage');
        $revision = $tip->getRevisions()->first();

        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m1'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m2'), VoteDecision::AGAINST, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m3'), VoteDecision::FOR, null);

        self::assertSame(RevisionStatus::APPROVED, $revision->getStatus());
        self::assertSame(TipStatus::PUBLISHED, $tip->getStatus());
        self::assertSame('Purger le circuit de freinage', $tip->getPublishedTitle());
        self::assertNotNull($tip->getPublishedAt());
        self::assertSame('purger-le-circuit-de-freinage', $tip->getSlug());

        $notifications = $this->notificationRepository->findBy(['recipient' => $tip->getAuthor()]);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::TIP_PUBLISHED, $notifications[0]->getType());
    }

    public function testMajorityAgainstOnFirstRevisionRejectsTip(): void
    {
        $tip = $this->createPendingTipWithRevision();
        $revision = $tip->getRevisions()->first();

        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m1'), VoteDecision::AGAINST, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m2'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m3'), VoteDecision::AGAINST, null);

        self::assertSame(RevisionStatus::REJECTED, $revision->getStatus());
        self::assertSame(TipStatus::REJECTED, $tip->getStatus());
        self::assertNull($tip->getPublishedAt());
        self::assertNull($tip->getSlug());

        $notifications = $this->notificationRepository->findBy(['recipient' => $tip->getAuthor()]);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationType::TIP_REJECTED, $notifications[0]->getType());
    }

    public function testMajorityForOnEditPublishesUpdatedContentWithoutRegeneratingSlug(): void
    {
        $tip = $this->createPublishedTip('Vidanger le liquide de refroidissement');
        $originalSlug = $tip->getSlug();

        $edit = $this->createRevision($tip, 'Vidanger le liquide de refroidissement (v2)', 'Contenu mis à jour.');

        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m1'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m2'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m3'), VoteDecision::AGAINST, null);

        self::assertSame(RevisionStatus::APPROVED, $edit->getStatus());
        self::assertSame(TipStatus::PUBLISHED, $tip->getStatus());
        self::assertSame('Vidanger le liquide de refroidissement (v2)', $tip->getPublishedTitle());
        self::assertSame('Contenu mis à jour.', $tip->getPublishedContent());
        self::assertSame($originalSlug, $tip->getSlug());

        $notifications = $this->notificationRepository->findBy(['recipient' => $tip->getAuthor(), 'type' => NotificationType::EDIT_PUBLISHED]);
        self::assertCount(1, $notifications);
    }

    public function testMajorityAgainstOnEditLeavesPublishedVersionUntouched(): void
    {
        $tip = $this->createPublishedTip('Contrôler la pression des pneus');
        $originalTitle = $tip->getPublishedTitle();
        $originalContent = $tip->getPublishedContent();

        $edit = $this->createRevision($tip, 'Une mauvaise modification', 'Contenu erroné.');

        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m1'), VoteDecision::AGAINST, null);
        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m2'), VoteDecision::AGAINST, null);
        $this->tipReviewService->vote($edit, $this->createCommitteeMember('m3'), VoteDecision::FOR, null);

        self::assertSame(RevisionStatus::REJECTED, $edit->getStatus());
        self::assertSame(TipStatus::PUBLISHED, $tip->getStatus());
        self::assertSame($originalTitle, $tip->getPublishedTitle());
        self::assertSame($originalContent, $tip->getPublishedContent());

        $notifications = $this->notificationRepository->findBy(['recipient' => $tip->getAuthor(), 'type' => NotificationType::EDIT_REJECTED]);
        self::assertCount(1, $notifications);
    }

    public function testTieWaitsForAnotherVote(): void
    {
        $tip = $this->createPendingTipWithRevision();
        $revision = $tip->getRevisions()->first();

        // Deux votes ajoutés directement sur l'entité, en contournant le
        // service, pour atteindre 2 pour / 1 contre sans déclencher de
        // résolution (impossible d'obtenir une égalité au 3e vote via
        // vote() puisque 3 est impair) — puis un 4e vote via le service
        // ramène à 2/2 et doit laisser la révision en attente.
        $vote1 = (new CommitteeVote())->setMember($this->createCommitteeMember('m1'))->setDecision(VoteDecision::FOR);
        $vote2 = (new CommitteeVote())->setMember($this->createCommitteeMember('m2'))->setDecision(VoteDecision::FOR);
        $vote3 = (new CommitteeVote())->setMember($this->createCommitteeMember('m3'))->setDecision(VoteDecision::AGAINST);

        foreach ([$vote1, $vote2, $vote3] as $vote) {
            $revision->addVote($vote);
            $this->entityManager->persist($vote);
        }
        $this->entityManager->flush();

        $this->tipReviewService->vote($revision, $this->createCommitteeMember('m4'), VoteDecision::AGAINST, null);

        self::assertSame(RevisionStatus::PENDING, $revision->getStatus());
        self::assertSame(TipStatus::PENDING, $tip->getStatus());
        self::assertNull($tip->getPublishedAt());
    }

    private function createPendingTipWithRevision(string $title = 'Un titre de tip quelconque'): Tip
    {
        $author = $this->createUser('author');
        $category = $this->createCategory();

        $tip = (new Tip())
            ->setAuthor($author)
            ->setCategory($category)
            ->setType(TipType::ADVICE);

        $this->entityManager->persist($tip);

        $revision = $this->createRevision($tip, $title, 'Contenu de la révision.');

        $this->entityManager->flush();

        return $tip;
    }

    private function createPublishedTip(string $title): Tip
    {
        $tip = $this->createPendingTipWithRevision($title);
        $revision = $tip->getRevisions()->first();

        $this->tipReviewService->vote($revision, $this->createCommitteeMember('p1'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('p2'), VoteDecision::FOR, null);
        $this->tipReviewService->vote($revision, $this->createCommitteeMember('p3'), VoteDecision::FOR, null);

        self::assertSame(TipStatus::PUBLISHED, $tip->getStatus());

        return $tip;
    }

    private function createRevision(Tip $tip, string $title, string $content): TipRevision
    {
        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content);

        $tip->addRevision($revision);
        $this->entityManager->persist($revision);
        $this->entityManager->flush();

        return $revision;
    }

    private function createCommitteeMember(string $suffix): User
    {
        return $this->createUser($suffix, ['ROLE_COMMITTEE']);
    }

    /** @param list<string> $roles */
    private function createUser(string $suffix, array $roles = []): User
    {
        static $counter = 0;
        ++$counter;

        $user = (new User())
            ->setEmail(sprintf('%s-%d@mecatips.test', $suffix, $counter))
            ->setUsername(sprintf('%s_%d', $suffix, $counter))
            ->setPassword('irrelevant-hash')
            ->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createCategory(): Category
    {
        static $counter = 0;
        ++$counter;

        $category = (new Category())
            ->setName(sprintf('Catégorie %d', $counter))
            ->setSlug(sprintf('categorie-%d', $counter));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }
}
