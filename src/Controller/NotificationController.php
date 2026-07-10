<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notification_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(NotificationRepository $notificationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $notificationRepository->findByRecipient($user);

        // Consultées = lues : pas de bouton "marquer lu" par ligne à gérer,
        // le compteur de la nav se vide simplement en visitant la page.
        $notificationRepository->markAllAsRead($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
