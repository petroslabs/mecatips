<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Tip;
use App\Entity\User;
use App\Entity\UsefulVote;
use App\Entity\Vehicle;
use App\Enum\TipStatus;
use App\Enum\TipType;
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

    /**
     * Contrairement à findByAuthor() (utilisé par "mes tips", qui montre
     * aussi les tips en attente/refusés à leur propre auteur), le profil
     * public ne doit exposer que les tips déjà publiés.
     *
     * @return list<Tip>
     */
    public function findPublishedByAuthor(User $author): array
    {
        return $this->findBy(
            ['status' => TipStatus::PUBLISHED, 'author' => $author],
            ['publishedAt' => 'DESC'],
        );
    }

    /**
     * Tips propres à ce véhicule uniquement (pas les tips "tous véhicules")
     * — contrairement à search(), la page véhicule ne doit pas se remplir de
     * tips génériques : ça viderait de son sens le 404 "aucun tip pour ce
     * véhicule" et ferait apparaître du contenu pour n'importe quel id.
     *
     * @return list<Tip>
     */
    public function findPublishedForVehicle(Vehicle $vehicle): array
    {
        return $this->findBy(
            ['status' => TipStatus::PUBLISHED, 'vehicle' => $vehicle],
            ['publishedAt' => 'DESC'],
        );
    }

    /**
     * Recherche/filtres sur les tips publiés (ROADMAP.md — visiteur).
     *
     * @param 'recent'|'useful' $sort
     *
     * @return list<Tip>
     */
    public function search(
        ?Category $category,
        ?Vehicle $vehicle,
        ?TipType $type,
        ?string $query,
        string $sort,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            // setParameter() sur du DQL contourne EnumNameType (Doctrine
            // infère le type du paramètre et lie ->value, pas ->name) — on
            // passe donc le name en string, comme la colonne le stocke.
            ->setParameter('status', TipStatus::PUBLISHED->name)
            ->orderBy('t.publishedAt', 'DESC');

        if ($category !== null) {
            $qb->andWhere('t.category = :category')->setParameter('category', $category);
        }

        // Un tip "tous véhicules" reste pertinent quel que soit le véhicule du
        // visiteur : aucun véhicule choisi => uniquement les tips généraux ;
        // véhicule choisi => tips généraux + tips propres à ce véhicule
        // (additif, pas un remplacement). orX() explicite plutôt qu'une
        // condition en chaîne de caractères, pour garantir le parenthésage
        // face au AND du reste de la requête.
        if ($vehicle !== null) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('t.vehicle'),
                $qb->expr()->eq('t.vehicle', ':vehicle'),
            ))->setParameter('vehicle', $vehicle);
        } else {
            $qb->andWhere($qb->expr()->isNull('t.vehicle'));
        }

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type->name);
        }

        if ($query !== null && $query !== '') {
            $qb->andWhere('t.publishedTitle LIKE :query OR t.publishedContent LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        /** @var list<Tip> $tips */
        $tips = $qb->getQuery()->getResult();

        if ($sort === 'useful' && $tips !== []) {
            $tips = $this->sortByUsefulScore($tips);
        }

        return $tips;
    }

    /**
     * @param list<Tip> $tips
     *
     * @return list<Tip>
     */
    private function sortByUsefulScore(array $tips): array
    {
        $ids = array_map(static fn (Tip $tip) => $tip->getId(), $tips);

        $usefulCounts = $this->countUsefulVotesByTip($ids, true);
        $notUsefulCounts = $this->countUsefulVotesByTip($ids, false);

        // usort() est stable depuis PHP 8 : à score égal, l'ordre par date
        // (déjà appliqué par la requête ci-dessus) est conservé.
        usort($tips, static function (Tip $a, Tip $b) use ($usefulCounts, $notUsefulCounts) {
            $scoreA = ($usefulCounts[$a->getId()] ?? 0) - ($notUsefulCounts[$a->getId()] ?? 0);
            $scoreB = ($usefulCounts[$b->getId()] ?? 0) - ($notUsefulCounts[$b->getId()] ?? 0);

            return $scoreB <=> $scoreA;
        });

        return $tips;
    }

    /**
     * @param list<int> $tipIds
     *
     * @return array<int, int> tipId => nombre de votes
     */
    private function countUsefulVotesByTip(array $tipIds, bool $useful): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(uv.tip) AS tipId, COUNT(uv.id) AS cnt')
            ->from(UsefulVote::class, 'uv')
            ->where('uv.tip IN (:ids)')
            ->andWhere('uv.useful = :useful')
            ->setParameter('ids', $tipIds)
            ->setParameter('useful', $useful)
            ->groupBy('uv.tip')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['tipId']] = (int) $row['cnt'];
        }

        return $result;
    }
}
