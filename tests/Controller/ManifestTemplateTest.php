<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ManifestTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/manifests'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('manifest/index.html.twig', [
            'rows' => [],
            'manifestCount' => 0,
        ]);

        self::assertStringContainsString('Manifests', $html);
        self::assertStringContainsString('No manifests discovered yet.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/manifests'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'backend/composer.json', 'backend/composer.lock');

        $html = $twig->render('manifest/index.html.twig', [
            'rows' => [
                ['manifest' => $manifest, 'dependencyCount' => 4],
            ],
            'manifestCount' => 1,
        ]);

        self::assertStringContainsString('my-project', $html);
        self::assertStringContainsString('backend/composer.json', $html);
        self::assertStringContainsString('backend/composer.lock', $html);
        self::assertStringContainsString('Composer', $html);
        self::assertStringContainsString('Un manifest découvert', $html);
    }
}
