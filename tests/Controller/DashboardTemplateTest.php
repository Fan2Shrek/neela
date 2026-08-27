<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class DashboardTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('dashboard/index.html.twig', [
            'projectCount' => 0,
            'scanStatusCounts' => [],
        ]);

        self::assertStringContainsString('Neela', $html);
        self::assertStringContainsString('Dashboard', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('dashboard/index.html.twig', [
            'projectCount' => 3,
            'scanStatusCounts' => ['completed' => 2, 'failed' => 1],
        ]);

        self::assertStringContainsString('3 projets suivis', $html);
        self::assertStringContainsString('scans à jour', $html);
        self::assertStringContainsString('scans en erreur', $html);
    }
}
