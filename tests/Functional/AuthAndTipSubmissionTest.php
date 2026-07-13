<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\TipStatus;
use App\Repository\TipRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthAndTipSubmissionTest extends WebTestCase
{
    public function testRegistrationCreatesAccountAndLogsInAutomatically(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'nouveau@mecatips.test',
            'registration_form[username]' => 'nouveau_contributeur',
            'registration_form[plainPassword]' => 'un-mot-de-passe-solide',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'nouveau@mecatips.test']);

        self::assertNotNull($user);
        self::assertNotSame('un-mot-de-passe-solide', $user->getPassword());

        // L'inscription connecte automatiquement (Security::login() dans
        // RegistrationController) : une requête suivante doit être authentifiée.
        $client->request('GET', '/tips/mine');
        self::assertResponseIsSuccessful();
    }

    public function testLoginWithWrongPasswordShowsErrorAndStaysLoggedOut(): void
    {
        $client = static::createClient();
        $this->createUser('existant@mecatips.test', 'existant', 'le-bon-mot-de-passe');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'existant@mecatips.test',
            '_password' => 'un-mauvais-mot-de-passe',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();

        self::assertGreaterThan(0, $crawler->filter('.flash--error')->count());
        self::assertNotSame('', trim($crawler->filter('.flash--error')->text()));

        $client->request('GET', '/tips/mine');
        self::assertResponseRedirects('/login');
    }

    public function testLoginWithCorrectPasswordAuthenticatesUser(): void
    {
        $client = static::createClient();
        $this->createUser('valide@mecatips.test', 'valide', 'le-bon-mot-de-passe');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'valide@mecatips.test',
            '_password' => 'le-bon-mot-de-passe',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();

        $client->request('GET', '/tips/mine');
        self::assertResponseIsSuccessful();
    }

    public function testTipSubmissionCreatesPendingTipAndRevision(): void
    {
        $client = static::createClient();
        $author = $this->createUser('contributeur@mecatips.test', 'contributeur', 'peu-importe');
        $category = $this->createLeafCategory();
        $client->loginUser($author);

        $crawler = $client->request('GET', '/tips/new');
        $form = $crawler->selectButton('Envoyer mon tip')->form([
            'tip_form[title]' => 'Purger le circuit de freinage sans aide',
            'tip_form[content]' => 'Ouvrir la vis de purge d\'un quart de tour et pomper doucement.',
            'tip_form[category]' => (string) $category->getId(),
            'tip_form[type]' => 'ADVICE',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');

        $tipRepository = self::getContainer()->get(TipRepository::class);
        $tips = $tipRepository->findByAuthor($author);

        self::assertCount(1, $tips);
        $tip = $tips[0];
        self::assertSame(TipStatus::PENDING, $tip->getStatus());
        self::assertCount(1, $tip->getRevisions());
        self::assertSame('Purger le circuit de freinage sans aide', $tip->getRevisions()->first()->getTitle());
    }

    /**
     * Régression : un texte saisi dans "véhicule concerné" doit être pris en
     * compte même si la case "valable pour tous véhicules" (cochée par
     * défaut) n'a pas été décochée — TipController::resolveVehicle() /
     * TipController::new() ne doit pas ignorer le champ dans ce cas.
     */
    public function testVehicleFieldIsUsedEvenWhenAllVehiclesCheckboxStaysChecked(): void
    {
        $client = static::createClient();
        $author = $this->createUser('contributeur2@mecatips.test', 'contributeur2', 'peu-importe');
        $category = $this->createLeafCategory();
        $client->loginUser($author);

        $crawler = $client->request('GET', '/tips/new');
        $checkbox = $crawler->filter('input[name="tip_form[allVehicles]"]');
        self::assertSame('checked', $checkbox->attr('checked'), 'La case doit être cochée par défaut pour reproduire le bug.');

        $form = $crawler->selectButton('Envoyer mon tip')->form([
            'tip_form[title]' => 'Un tip spécifique à un véhicule',
            'tip_form[content]' => 'Contenu du tip.',
            'tip_form[category]' => (string) $category->getId(),
            'tip_form[type]' => 'ADVICE',
            'tip_form[vehicleLabel]' => 'Peugeot 208 1.6 HDi',
        ]);
        // La checkbox reste cochée : on ne la décoche pas volontairement.
        $client->submit($form);

        self::assertResponseRedirects('/');

        $tipRepository = self::getContainer()->get(TipRepository::class);
        $tips = $tipRepository->findByAuthor($author);

        self::assertCount(1, $tips);
        self::assertNotNull($tips[0]->getVehicle());
        self::assertSame('Peugeot 208 1.6 HDi', $tips[0]->getVehicle()->getLabel());
    }

    public function testRateLimitBlocksSixthSubmissionWithoutPersistingIt(): void
    {
        $client = static::createClient();
        $author = $this->createUser('flooder@mecatips.test', 'flooder', 'peu-importe');
        $category = $this->createLeafCategory();
        $client->loginUser($author);

        for ($i = 1; $i <= 5; ++$i) {
            $crawler = $client->request('GET', '/tips/new');
            $form = $crawler->selectButton('Envoyer mon tip')->form([
                'tip_form[title]' => sprintf('Tip numéro %d', $i),
                'tip_form[content]' => 'Contenu du tip.',
                'tip_form[category]' => (string) $category->getId(),
                'tip_form[type]' => 'ADVICE',
            ]);
            $client->submit($form);
            self::assertResponseRedirects('/', null, sprintf('La soumission n°%d devrait être acceptée.', $i));
        }

        $tipRepository = self::getContainer()->get(TipRepository::class);
        self::assertCount(5, $tipRepository->findByAuthor($author));

        $crawler = $client->request('GET', '/tips/new');
        $form = $crawler->selectButton('Envoyer mon tip')->form([
            'tip_form[title]' => 'Tip numéro 6, refusé',
            'tip_form[content]' => 'Contenu du tip.',
            'tip_form[category]' => (string) $category->getId(),
            'tip_form[type]' => 'ADVICE',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertCount(5, $tipRepository->findByAuthor($author), 'La 6e soumission ne doit pas créer de tip.');
    }

    private function createUser(string $email, string $username, string $plainPassword): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createLeafCategory(): Category
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $parent = (new Category())->setName('Freinage')->setSlug('freinage-' . uniqid());
        $entityManager->persist($parent);

        $leaf = (new Category())->setName('Purge du circuit')->setSlug('purge-circuit-' . uniqid())->setParent($parent);
        $entityManager->persist($leaf);

        $entityManager->flush();

        return $leaf;
    }
}
