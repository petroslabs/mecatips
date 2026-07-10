<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Report;
use App\Enum\ReportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /** @return list<Report> */
    public function findPending(): array
    {
        return $this->findBy(['status' => ReportStatus::PENDING], ['createdAt' => 'ASC']);
    }
}
