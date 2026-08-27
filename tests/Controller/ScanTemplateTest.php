<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Entity\Scan;
use App\Enum\ScanStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ScanTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/scans'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('scan/index.html.twig', [
            'scans' => [],
            'scanCount' => 0,
        ]);

        self::assertStringContainsString('Scans', $html);
        self::assertStringContainsString('No scans yet.', $html);
    }

    public function testPopulatedStateInFrenchWithFailedScan(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/scans'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');

        $scan = new Scan($manifest, ScanStatus::FAILED);
        $scan->setStartedAt(new \DateTimeImmutable('2026-08-27 10:00:00'));
        $scan->setCompletedAt(new \DateTimeImmutable('2026-08-27 10:01:00'));
        $scan->setError('GitHub API request failed.');

        $html = $twig->render('scan/index.html.twig', [
            'scans' => [$scan],
            'scanCount' => 1,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('En erreur', $html);
        self::assertStringContainsString('GitHub API request failed.', $html);
        self::assertStringContainsString('27/08/2026 10:00', $html);
        self::assertStringContainsString('Un scan', $html);
    }
}
