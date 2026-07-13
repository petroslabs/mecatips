<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\User;
use App\Enum\TipStatus;
use App\Enum\TipType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessControlAndTipShowTest extends WebTestCase
{
    public function testCommitteeQueueRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/committee');

        self::assertResponseRedirects('/login');
    }

    public function testCommitteeQueueRejectsPlainUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser('plain@mecatips.test', 'plain_user', []);
        $client->loginUser($user);

        $client->request('GET', '/committee');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCommitteeQueueAllowsCommitteeMember(): void
    {
        $client = static::createClient();
        $member = $this->createUser('member@mecatips.test', 'committee_member', ['ROLE_COMMITTEE']);
        $client->loginUser($member);

        $client->request('GET', '/committee');

        self::assertResponseIsSuccessful();
    }

    public function testAdminCommitteeIndexRejectsCommitteeMemberWithoutAdminRole(): void
    {
        $client = static::createClient();
        $member = $this->createUser('member2@mecatips.test', 'committee_member_2', ['ROLE_COMMITTEE']);
        $client->loginUser($member);

        $client->request('GET', '/admin/committee');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCommitteeIndexAllowsAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin@mecatips.test', 'the_admin', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/committee');

        self::assertResponseIsSuccessful();
    }

    public function testTipShowReturns200ForPublishedTipSlug(): void
    {
        $client = static::createClient();
        $tip = $this->createPublishedTip('Vidanger le moteur en toute sécurité');

        $client->request('GET', '/tips/' . $tip->getSlug());

        self::assertResponseIsSuccessful();
    }

    public function testTipShowReturns404ForUnknownSlug(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tips/ce-slug-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTipShowReturns404ForUnpublishedTipEvenToItsAuthor(): void
    {
        $client = static::createClient();
        $tip = $this->createPublishedTip('Un tip republié en attente de modification');
        $author = $tip->getAuthor();

        // Simule une révision d'édition qui a fait repasser le statut du tip
        // hors PUBLISHED (aucun flux normal ne laisse un slug pointer vers un
        // statut non publié en pratique, mais TipController::show() doit s'en
        // prémunir explicitement, y compris pour l'auteur).
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $tip->setStatus(TipStatus::PENDING);
        $entityManager->flush();

        $client->loginUser($author);
        $client->request('GET', '/tips/' . $tip->getSlug());

        self::assertResponseStatusCodeSame(404);
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $username, array $roles): User
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

        $author = $this->createUser('author-' . uniqid() . '@mecatips.test', 'author_' . uniqid(), []);

        $parent = (new Category())->setName('Moteur')->setSlug('moteur-' . uniqid());
        $entityManager->persist($parent);
        $leaf = (new Category())->setName('Vidange')->setSlug('vidange-' . uniqid())->setParent($parent);
        $entityManager->persist($leaf);

        $tip = (new Tip())
            ->setAuthor($author)
            ->setCategory($leaf)
            ->setType(TipType::ADVICE)
            ->setStatus(TipStatus::PUBLISHED)
            ->setPublishedTitle($title)
            ->setPublishedContent('Contenu publié.')
            ->setPublishedAt(new \DateTimeImmutable())
            ->setSlug(strtolower(str_replace([' ', 'é', 'è'], ['-', 'e', 'e'], $title)) . '-' . uniqid());

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
