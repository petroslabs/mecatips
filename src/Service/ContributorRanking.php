<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\ContributorBadge;

final readonly class ContributorRanking
{
    public function __construct(
        public User $user,
        public int $publishedTipsCount,
        public int $usefulScore,
        public int $totalScore,
        public ContributorBadge $badge,
    ) {
    }
}
