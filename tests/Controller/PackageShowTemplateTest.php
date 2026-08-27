<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\Vendor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class PackageShowTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        $composer = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $composer);
        $package = new Package('console', $vendor);
        self::getContainer()->get('request_stack')->push(Request::create('/packages/1'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('package/show.html.twig', [
            'package' => $package,
            'rows' => [],
            'dependencyCount' => 0,
            'projectCount' => 0,
            'latestVersion' => null,
        ]);

        self::assertStringContainsString('symfony/console', $html);
        self::assertStringContainsString('No project depends on this package.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/packages/1'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $composer = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $composer);
        $package = new Package('console', $vendor);

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');

        $upToDateDependency = new Dependency($manifest, $package, '^7.3', 'v7.3.10', 'require');
        $outdatedDependency = new Dependency($manifest, $package, '^6.4', 'v6.4.18', 'require');
        $unknownDependency = new Dependency($manifest, $package, 'workspace:*', 'v1.0.0', 'require');

        $html = $twig->render('package/show.html.twig', [
            'package' => $package,
            'rows' => [
                ['dependency' => $upToDateDependency, 'latestVersion' => 'v7.3.10', 'status' => 'up_to_date'],
                ['dependency' => $outdatedDependency, 'latestVersion' => 'v6.4.19', 'status' => 'outdated'],
                ['dependency' => $unknownDependency, 'latestVersion' => null, 'status' => 'unknown'],
            ],
            'dependencyCount' => 3,
            'projectCount' => 1,
            'latestVersion' => 'v7.3.10',
        ]);

        self::assertStringContainsString('symfony/console', $html);
        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('v7.3.10', $html);
        self::assertStringContainsString('v6.4.18', $html);
        self::assertStringContainsString('v6.4.19', $html);
        self::assertStringContainsString('À jour', $html);
        self::assertStringContainsString('Obsolète', $html);
        self::assertStringContainsString('Inconnu', $html);
    }
}
