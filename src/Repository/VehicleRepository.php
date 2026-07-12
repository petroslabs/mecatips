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

    /**
     * Pour l'autocomplete du formulaire de soumission : suggérer les
     * véhicules déjà connus pendant la saisie, pour inciter à réutiliser une
     * entrée existante plutôt que d'en recréer une quasi-identique.
     *
     * @return list<Vehicle>
     */
    public function searchByLabel(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->where('LOWER(v.label) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('v.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
