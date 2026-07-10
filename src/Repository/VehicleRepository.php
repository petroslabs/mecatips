<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Vehicle;
use App\Enum\TipStatus;
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

    /**
     * Véhicules ayant au moins un tip publié — pour le filtre de recherche,
     * pas d'intérêt à lister un véhicule qui ne mènerait à aucun résultat.
     *
     * @return list<Vehicle>
     */
    public function findWithPublishedTips(): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.tips', 't')
            ->andWhere('t.status = :status')
            ->setParameter('status', TipStatus::PUBLISHED->name)
            ->distinct()
            ->orderBy('v.label', 'ASC')
            ->getQuery()
            ->getResult();
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
