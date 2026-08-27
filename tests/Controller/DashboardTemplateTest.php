<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Enum\ProjectUpdateStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

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
            'updateStatusCounts' => [],
            'projectsNeedingUpdate' => [],
        ]);

        self::assertStringContainsString('Neela', $html);
        self::assertStringContainsString('Dashboard', $html);
        self::assertStringContainsString('All projects are up to date.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $outdatedProject = new Project('my-outdated-project', 'git@github.com:acme/my-outdated-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($outdatedProject, Uuid::v7());

        $html = $twig->render('dashboard/index.html.twig', [
            'projectCount' => 3,
            'scanStatusCounts' => ['completed' => 2, 'failed' => 1],
            'updateStatusCounts' => ['up_to_date' => 1, 'partially_up_to_date' => 1, 'outdated' => 1],
            'projectsNeedingUpdate' => [
                ['project' => $outdatedProject, 'status' => ProjectUpdateStatus::OUTDATED],
            ],
        ]);

        self::assertStringContainsString('3 projets suivis', $html);
        self::assertStringContainsString('scans à jour', $html);
        self::assertStringContainsString('scans en erreur', $html);
        self::assertStringContainsString('projets à jour', $html);
        self::assertStringContainsString('projets partiellement à jour', $html);
        self::assertStringContainsString('projets à mettre à jour', $html);
        self::assertStringContainsString('my-outdated-project', $html);
        self::assertStringContainsString('À mettre à jour', $html);
    }
}
