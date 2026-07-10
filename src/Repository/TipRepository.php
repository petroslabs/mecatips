<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tip;
use App\Entity\User;
use App\Enum\TipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tip>
 */
class TipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tip::class);
    }

    /** @return list<Tip> */
    public function findPublished(): array
    {
        return $this->findBy(['status' => TipStatus::PUBLISHED], ['publishedAt' => 'DESC']);
    }

    /** @return list<Tip> */
    public function findByAuthor(User $author): array
    {
        return $this->findBy(['author' => $author], ['createdAt' => 'DESC']);
    }
}
