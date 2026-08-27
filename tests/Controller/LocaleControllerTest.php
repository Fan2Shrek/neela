<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleControllerTest extends WebTestCase
{
    public function testSwitchingLocalePersistsItInSessionAndRedirectsBack(): void
    {
        $client = self::createClient();

        $client->request('GET', '/locale/fr?redirect=/projects');

        self::assertResponseRedirects('/projects');
        self::assertSame('fr', $client->getRequest()->getSession()->get('_locale'));
    }

    public function testUnsupportedLocaleIsRejectedByRouting(): void
    {
        $client = self::createClient();

        $client->request('GET', '/locale/de?redirect=/projects');

        self::assertResponseStatusCodeSame(404);
    }

    public function testProtocolRelativeRedirectIsIgnored(): void
    {
        $client = self::createClient();

        $client->request('GET', '/locale/fr?redirect=//evil.example.com');

        self::assertResponseRedirects('/');
    }

    public function testMissingRedirectFallsBackToDashboard(): void
    {
        $client = self::createClient();

        $client->request('GET', '/locale/en');

        self::assertResponseRedirects('/');
    }
}
