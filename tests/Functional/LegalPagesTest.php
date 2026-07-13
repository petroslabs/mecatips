<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPagesTest extends WebTestCase
{
    public function testLegalNoticeIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/legal-notice');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
    }

    public function testTermsAreReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/terms');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "Conditions générales d'utilisation");
    }

    public function testPrivacyPolicyIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/privacy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
    }

    public function testFooterLinksToTheThreePages(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.site-footer a[href="/legal-notice"]')->count());
        self::assertGreaterThan(0, $crawler->filter('.site-footer a[href="/terms"]')->count());
        self::assertGreaterThan(0, $crawler->filter('.site-footer a[href="/privacy"]')->count());
    }
}
