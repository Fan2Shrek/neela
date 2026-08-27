<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\TechnologyReleaseCycle;
use App\Entity\Vendor;
use App\Enum\Technology;
use App\Enum\TechnologySupportStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class TechnologyTemplateTest extends KernelTestCase
{
    public function testIndexListsEveryKnownTechnology(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/technologies'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('technology/index.html.twig', [
            'rows' => [
                ['technology' => Technology::SYMFONY, 'projectCount' => 3, 'statuses' => ['lts', 'technology_outdated']],
                ['technology' => Technology::LARAVEL, 'projectCount' => 0, 'statuses' => []],
                ['technology' => Technology::REACT, 'projectCount' => 1, 'statuses' => ['unknown']],
                ['technology' => Technology::VUE, 'projectCount' => 0, 'statuses' => []],
            ],
            'technologyCount' => 4,
        ]);

        self::assertStringContainsString('Symfony', $html);
        self::assertStringContainsString('Laravel', $html);
        self::assertStringContainsString('React', $html);
        self::assertStringContainsString('Vue', $html);
        self::assertStringContainsString('/technologies/symfony', $html);
        self::assertStringContainsString('badge--lts', $html);
        self::assertStringContainsString('badge--technology_outdated', $html);
        self::assertStringContainsString('badge--unknown', $html);
    }

    public function testShowEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/technologies/laravel'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('technology/show.html.twig', [
            'technology' => Technology::LARAVEL,
            'rows' => [],
            'projectCount' => 0,
            'cycles' => [],
        ]);

        self::assertStringContainsString('Laravel', $html);
        self::assertStringContainsString('No project uses this technology yet.', $html);
    }

    public function testShowPopulatedStateInFrenchWithReleaseCycles(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/technologies/symfony'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $cycle = new TechnologyReleaseCycle(Technology::SYMFONY, '7.4', '7.4.17', true);
        $cycle->setReleaseDate(new \DateTimeImmutable('2025-11-27'));
        $cycle->setEolDate(new \DateTimeImmutable('2029-11-30'));

        $composer = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $composer);
        $package = new Package('framework-bundle', $vendor);

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');
        $dependency = new Dependency($manifest, $package, '7.4.*', 'v7.4.2', 'require');

        $html = $twig->render('technology/show.html.twig', [
            'technology' => Technology::SYMFONY,
            'rows' => [
                ['dependency' => $dependency, 'status' => TechnologySupportStatus::UP_TO_DATE],
            ],
            'projectCount' => 1,
            'cycles' => [$cycle],
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('v7.4.2', $html);
        self::assertStringContainsString('7.4', $html);
        self::assertStringContainsString('7.4.17', $html);
        self::assertStringContainsString('LTS', $html);
        self::assertStringContainsString('27/11/2025', $html);
        self::assertStringContainsString('30/11/2029', $html);
        self::assertStringContainsString('À jour', $html);
    }
}
