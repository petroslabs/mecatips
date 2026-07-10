<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tip;
use App\Entity\UsefulVote;
use App\Entity\User;
use App\Enum\ContributorBadge;
use App\Enum\TipStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Classement des contributeurs — voir ROADMAP.md, section "Ranking &
 * gamification" : score = nombre de tips validés + score d'utilité net
 * (votes utile - votes pas utile sur ces tips), pas la seule quantité, pour
 * ne pas inciter au spam de tips médiocres. Calculé à la volée plutôt que
 * dénormalisé sur User, pour ne pas avoir à maintenir un compteur en plus
 * du reste des données.
 */
final class ContributorRankingService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<ContributorRanking> */
    public function getRanking(): array
    {
        $tipCounts = $this->countByAuthor(
            'SELECT IDENTITY(t.author) AS authorId, COUNT(t.id) AS cnt
             FROM ' . Tip::class . ' t
             WHERE t.status = :status
             GROUP BY t.author',
        );

        $usefulCounts = $this->countByAuthor(
            'SELECT IDENTITY(t.author) AS authorId, COUNT(uv.id) AS cnt
             FROM ' . UsefulVote::class . ' uv
             JOIN uv.tip t
             WHERE t.status = :status AND uv.useful = true
             GROUP BY t.author',
        );

        $notUsefulCounts = $this->countByAuthor(
            'SELECT IDENTITY(t.author) AS authorId, COUNT(uv.id) AS cnt
             FROM ' . UsefulVote::class . ' uv
             JOIN uv.tip t
             WHERE t.status = :status AND uv.useful = false
             GROUP BY t.author',
        );

        $authorIds = array_unique(array_keys($tipCounts));
        if ($authorIds === []) {
            return [];
        }

        $users = $this->entityManager->getRepository(User::class)->findBy(['id' => $authorIds]);

        $rankings = [];
        foreach ($users as $user) {
            $id = $user->getId();
            $publishedTipsCount = $tipCounts[$id] ?? 0;
            $usefulScore = ($usefulCounts[$id] ?? 0) - ($notUsefulCounts[$id] ?? 0);

            $rankings[] = new ContributorRanking(
                user: $user,
                publishedTipsCount: $publishedTipsCount,
                usefulScore: $usefulScore,
                totalScore: $publishedTipsCount + $usefulScore,
                badge: ContributorBadge::fromPublishedTipsCount($publishedTipsCount),
            );
        }

        usort($rankings, static fn (ContributorRanking $a, ContributorRanking $b) => $b->totalScore <=> $a->totalScore);

        return $rankings;
    }

    /** @return array<int, int> authorId => count */
    private function countByAuthor(string $dql): array
    {
        // setParameter() sur du DQL brut contourne EnumNameType (Doctrine
        // infère le type du paramètre et lie ->value, pas ->name) — on
        // passe donc directement le name en string plutôt que l'instance
        // d'enum, comme la colonne le stocke réellement.
        $rows = $this->entityManager->createQuery($dql)
            ->setParameter('status', TipStatus::PUBLISHED->name)
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['authorId']] = (int) $row['cnt'];
        }

        return $result;
    }
}
