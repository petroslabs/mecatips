<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nombre de notifications non lues à la nav (base.html.twig),
 * commune à toutes les pages — pas de moyen propre de faire transiter cette
 * donnée par chaque contrôleur, contrairement au reste du site.
 */
final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notification_count', $this->unreadNotificationCount(...)),
        ];
    }

    public function unreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnread($user);
    }
}
