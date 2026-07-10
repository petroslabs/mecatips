<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<Notification> */
    public function findByRecipient(User $recipient): array
    {
        return $this->findBy(['recipient' => $recipient], ['createdAt' => 'DESC']);
    }

    public function countUnread(User $recipient): int
    {
        return $this->count(['recipient' => $recipient, 'readAt' => null]);
    }

    /** Marque tout d'un coup plutôt qu'un aller-retour par notification — pas besoin d'un bouton "marquer lu" par ligne. */
    public function markAllAsRead(User $recipient): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.recipient = :recipient')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->execute();
    }
}
