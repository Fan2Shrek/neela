<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\Scan;
use App\Entity\Vendor;
use App\Enum\ScanStatus;
use App\Enum\Technology;
use App\Enum\TechnologySupportStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class ProjectShowTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());
        self::getContainer()->get('request_stack')->push(Request::create('/projects/'.$project->getId()));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('project/show.html.twig', [
            'project' => $project,
            'manifestRows' => [],
            'manifestCount' => 0,
            'dependencyCount' => 0,
            'outdatedDependencyRows' => [],
            'scans' => [],
            'lastScan' => null,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('No manifests discovered yet.', $html);
        self::assertStringContainsString('No scans yet.', $html);
        self::assertStringContainsString('No outdated dependencies.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());
        self::getContainer()->get('request_stack')->push(Request::create('/projects/'.$project->getId()));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');

        $vendor = new Vendor('symfony', $composer);
        $package = new Package('console', $vendor);
        $outdatedDependency = new Dependency($manifest, $package, '^6.4', 'v6.4.18', 'require');

        $scan = new Scan($manifest, ScanStatus::COMPLETED);
        $scan->setStartedAt(new \DateTimeImmutable('2026-08-27 10:00:00'));
        $scan->setCompletedAt(new \DateTimeImmutable('2026-08-27 10:01:00'));

        $html = $twig->render('project/show.html.twig', [
            'project' => $project,
            'manifestRows' => [
                [
                    'manifest' => $manifest,
                    'dependencyCount' => 4,
                    'technology' => Technology::SYMFONY,
                    'technologyVersion' => 'v7.4.2',
                    'technologySupportStatus' => TechnologySupportStatus::LTS,
                ],
            ],
            'manifestCount' => 1,
            'dependencyCount' => 4,
            'outdatedDependencyRows' => [
                ['dependency' => $outdatedDependency, 'latestVersion' => 'v6.4.19'],
            ],
            'scans' => [$scan],
            'lastScan' => $scan,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('composer.json', $html);
        self::assertStringContainsString('Composer', $html);
        self::assertStringContainsString('À jour', $html);
        self::assertStringContainsString('27/08/2026 10:00', $html);
        self::assertStringContainsString('symfony/console', $html);
        self::assertStringContainsString('v6.4.18', $html);
        self::assertStringContainsString('v6.4.19', $html);
        self::assertStringContainsString('Dépendances obsolètes', $html);
        self::assertStringContainsString('Symfony v7.4.2', $html);
        self::assertStringContainsString('LTS', $html);
    }
}
