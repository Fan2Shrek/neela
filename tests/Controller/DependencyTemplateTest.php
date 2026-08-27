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

final class DependencyTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/dependencies'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('dependency/index.html.twig', [
            'dependencies' => [],
            'dependencyCount' => 0,
        ]);

        self::assertStringContainsString('Dependencies', $html);
        self::assertStringContainsString('No dependencies discovered yet.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/dependencies'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');
        $vendor = new Vendor('symfony', $composer);
        $package = new Package('console', $vendor);
        $dependency = new Dependency($manifest, $package, '^6.4', 'v6.4.18', 'require');

        $html = $twig->render('dependency/index.html.twig', [
            'dependencies' => [$dependency],
            'dependencyCount' => 1,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('symfony/console', $html);
        self::assertStringContainsString('^6.4', $html);
        self::assertStringContainsString('v6.4.18', $html);
        self::assertStringContainsString('Une dépendance découverte', $html);
    }
}
