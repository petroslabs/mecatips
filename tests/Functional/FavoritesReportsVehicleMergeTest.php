<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\TipStatus;
use App\Enum\TipType;
use App\Enum\VehicleStatus;
use App\Repository\FavoriteRepository;
use App\Repository\ReportRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class FavoritesReportsVehicleMergeTest extends WebTestCase
{
    public function testFavoriteToggleAddsThenRemoves(): void
    {
        $client = static::createClient();
        $user = $this->createUser('fan@mecatips.test', 'fan');
        $tip = $this->createPublishedTip('Changer un joint de culasse');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/tips/' . $tip->getSlug());
        $token = $this->csrfTokenFrom($crawler, '.tip-detail__favorite-form');

        $client->request('POST', '/tips/' . $tip->getId() . '/favorite', ['_token' => $token]);
        self::assertResponseRedirects();

        $favoriteRepository = self::getContainer()->get(FavoriteRepository::class);
        self::assertNotNull($favoriteRepository->findOneBy(['tip' => $tip, 'user' => $user]));

        $client->request('POST', '/tips/' . $tip->getId() . '/favorite', ['_token' => $token]);
        self::assertResponseRedirects();

        self::assertNull($favoriteRepository->findOneBy(['tip' => $tip, 'user' => $user]));
    }

    public function testReportSubmissionCreatesReport(): void
    {
        $client = static::createClient();
        $user = $this->createUser('reporter@mecatips.test', 'reporter');
        $tip = $this->createPublishedTip('Remplacer une courroie de distribution');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/tips/' . $tip->getSlug());
        $token = $this->csrfTokenFrom($crawler, '.report-form');

        $client->request('POST', '/tips/' . $tip->getId() . '/report', [
            '_token' => $token,
            'reason' => 'Information incorrecte sur le couple de serrage.',
        ]);

        self::assertResponseRedirects();

        $reportRepository = self::getContainer()->get(ReportRepository::class);
        $reports = $reportRepository->findBy(['tip' => $tip, 'reporter' => $user]);
        self::assertCount(1, $reports);
        self::assertSame('Information incorrecte sur le couple de serrage.', $reports[0]->getReason());
    }

    public function testDuplicatePendingReportIsBlocked(): void
    {
        $client = static::createClient();
        $user = $this->createUser('reporter2@mecatips.test', 'reporter2');
        $tip = $this->createPublishedTip('Contrôler le niveau de liquide de direction');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/tips/' . $tip->getSlug());
        $token = $this->csrfTokenFrom($crawler, '.report-form');

        $client->request('POST', '/tips/' . $tip->getId() . '/report', [
            '_token' => $token,
            'reason' => 'Premier signalement.',
        ]);
        $client->request('POST', '/tips/' . $tip->getId() . '/report', [
            '_token' => $token,
            'reason' => 'Second signalement, ne devrait pas être créé.',
        ]);

        $reportRepository = self::getContainer()->get(ReportRepository::class);
        $reports = $reportRepository->findBy(['tip' => $tip, 'reporter' => $user]);
        self::assertCount(1, $reports, 'Un signalement déjà en attente pour ce tip/utilisateur doit bloquer un second envoi.');
    }

    public function testVehicleMergeReassignsTipsAndDeletesDuplicate(): void
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $moderator = $this->createUser('moderator@mecatips.test', 'moderator', ['ROLE_COMMITTEE']);

        $duplicate = (new Vehicle())->setLabel('Golf 4')->setStatus(VehicleStatus::VALIDATED);
        $target = (new Vehicle())->setLabel('Volkswagen Golf IV')->setStatus(VehicleStatus::VALIDATED);
        $entityManager->persist($duplicate);
        $entityManager->persist($target);
        $entityManager->flush();

        $tip = $this->createPublishedTip('Régler le ralenti');
        $tip->setVehicle($duplicate);
        $entityManager->flush();

        $duplicateId = $duplicate->getId();
        $targetId = $target->getId();

        $client->loginUser($moderator);
        $crawler = $client->request('GET', '/committee/vehicles');
        $token = $this->csrfTokenFrom($crawler, sprintf('form[action$="/committee/vehicles/%d/merge"]', $duplicateId));

        $client->request('POST', '/committee/vehicles/' . $duplicateId . '/merge', [
            '_token' => $token,
            'target' => (string) $targetId,
        ]);

        self::assertResponseRedirects('/committee/vehicles');

        $vehicleRepository = self::getContainer()->get(VehicleRepository::class);
        self::assertNull($vehicleRepository->find($duplicateId));

        // Le passage par le client HTTP réinitialise l'EntityManager entre
        // les requêtes (kernel.reset) : $tip est détaché, on doit le
        // recharger plutôt que le rafraîchir.
        $refreshedTip = $entityManager->find(Tip::class, $tip->getId());
        self::assertNotNull($refreshedTip->getVehicle());
        self::assertSame($targetId, $refreshedTip->getVehicle()->getId());
    }

    private function csrfTokenFrom(Crawler $crawler, string $formSelector): string
    {
        return (string) $crawler->filter($formSelector . ' input[name="_token"]')->attr('value');
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $username, array $roles = []): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword('irrelevant-hash')
            ->setRoles($roles);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createPublishedTip(string $title): Tip
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $author = $this->createUser('author-' . uniqid() . '@mecatips.test', 'author_' . uniqid());

        $parent = (new Category())->setName('Moteur')->setSlug('moteur-' . uniqid());
        $entityManager->persist($parent);
        $leaf = (new Category())->setName('Entretien')->setSlug('entretien-' . uniqid())->setParent($parent);
        $entityManager->persist($leaf);

        $tip = (new Tip())
            ->setAuthor($author)
            ->setCategory($leaf)
            ->setType(TipType::ADVICE)
            ->setStatus(TipStatus::PUBLISHED)
            ->setPublishedTitle($title)
            ->setPublishedContent('Contenu publié.')
            ->setPublishedAt(new \DateTimeImmutable())
            ->setSlug(strtolower(str_replace(' ', '-', $title)) . '-' . uniqid());

        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent('Contenu publié.');
        $tip->addRevision($revision);

        $entityManager->persist($tip);
        $entityManager->persist($revision);
        $entityManager->flush();

        return $tip;
    }
}
