<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommitteeVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommitteeVote>
 */
class CommitteeVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommitteeVote::class);
    }
}
