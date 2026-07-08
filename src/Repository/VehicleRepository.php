<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    public function findOneByLabel(string $label): ?Vehicle
    {
        return $this->createQueryBuilder('v')
            ->where('LOWER(v.label) = LOWER(:label)')
            ->setParameter('label', $label)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<string> */
    public function findAllLabels(): array
    {
        return array_column(
            $this->createQueryBuilder('v')
                ->select('v.label')
                ->orderBy('v.label', 'ASC')
                ->getQuery()
                ->getScalarResult(),
            'label',
        );
    }
}
