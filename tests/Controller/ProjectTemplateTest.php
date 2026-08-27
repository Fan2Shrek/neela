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

final class ProjectTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/projects'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('project/index.html.twig', [
            'rows' => [],
            'projectCount' => 0,
        ]);

        self::assertStringContainsString('Projects', $html);
        self::assertStringContainsString('No projects tracked yet.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/projects'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');

        $scan = new Scan($manifest, ScanStatus::COMPLETED);
        $scan->setStartedAt(new \DateTimeImmutable('2026-08-27 10:00:00'));
        $scan->setCompletedAt(new \DateTimeImmutable('2026-08-27 10:01:00'));

        $html = $twig->render('project/index.html.twig', [
            'rows' => [
                [
                    'project' => $project,
                    'manifestCount' => 1,
                    'dependencyManagers' => [$manifest->getDependencyManager()->getName()],
                    'lastScan' => $scan,
                ],
            ],
            'projectCount' => 1,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('Composer', $html);
        self::assertStringContainsString('À jour', $html);
        self::assertStringContainsString('Un projet suivi', $html);
        self::assertStringContainsString('27/08/2026 10:00', $html);
    }
}
