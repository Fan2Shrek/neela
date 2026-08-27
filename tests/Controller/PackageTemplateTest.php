<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DependencyManager;
use App\Entity\Package;
use App\Entity\Vendor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class PackageTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/packages'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('package/index.html.twig', [
            'rows' => [],
            'packageCount' => 0,
            'search' => '',
            'dependencyManagerName' => '',
            'dependencyManagers' => [],
            'isFiltered' => false,
        ]);

        self::assertStringContainsString('Packages', $html);
        self::assertStringContainsString('No packages discovered yet.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/packages'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $composer = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $composer);
        $package = new Package('console', $vendor);
        (new \ReflectionProperty(Package::class, 'id'))->setValue($package, 1);

        $html = $twig->render('package/index.html.twig', [
            'rows' => [
                [
                    'package' => $package,
                    'projectCount' => 3,
                    'dependencyCount' => 5,
                ],
            ],
            'packageCount' => 1,
            'search' => '',
            'dependencyManagerName' => '',
            'dependencyManagers' => [$composer],
            'isFiltered' => false,
        ]);

        self::assertStringContainsString('symfony/console', $html);
        self::assertStringContainsString('Composer', $html);
        self::assertStringContainsString('Un paquet découvert', $html);
    }

    public function testFilteredEmptyStateShowsANoResultsMessageWithAClearLink(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/packages'));
        $twig = self::getContainer()->get('twig');

        $composer = new DependencyManager('Composer');

        $html = $twig->render('package/index.html.twig', [
            'rows' => [],
            'packageCount' => 3,
            'search' => 'nonexistent',
            'dependencyManagerName' => 'Composer',
            'dependencyManagers' => [$composer],
            'isFiltered' => true,
        ]);

        self::assertStringContainsString('No packages match your filters.', $html);
        self::assertStringNotContainsString('No packages discovered yet.', $html);
        self::assertStringContainsString('value="nonexistent"', $html);
        self::assertStringContainsString('selected', $html);
    }
}
