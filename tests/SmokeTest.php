<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que le pipeline de test lui-même fonctionne (boot du kernel,
 * connexion à mecatips_test, rollback DAMA entre tests) avant d'écrire des
 * tests métier dessus.
 */
final class SmokeTest extends KernelTestCase
{
    public function testKernelBootsAndConnectsToTestDatabase(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        self::assertSame(0, $entityManager->getRepository(User::class)->count([]));
    }

    /**
     * Si DAMA ne fait pas son rollback correctement, cette insertion
     * "fuiterait" vers le test suivant — testRollbackWorked() en dépend.
     */
    public function testInsertUserThenNextTestShouldNotSeeIt(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail('smoke@mecatips.test')
            ->setUsername('smoke_test')
            ->setPassword('irrelevant-hash');

        $entityManager->persist($user);
        $entityManager->flush();

        self::assertSame(1, $entityManager->getRepository(User::class)->count([]));
    }

    public function testRollbackWorked(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        self::assertSame(0, $entityManager->getRepository(User::class)->count([]));
    }
}
