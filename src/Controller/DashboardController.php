<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Manifest;
use App\Entity\Project;
use App\Repository\ManifestRepository;
use App\Repository\ProjectRepository;
use App\Repository\ScanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ManifestRepository $manifestRepository,
        private readonly ScanRepository $scanRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        $projects = $this->projectRepository->findAll();

        $rows = array_map(function (Project $project): array {
            $manifests = $this->manifestRepository->findBy(['project' => $project]);

            $dependencyManagers = array_unique(array_map(
                static fn (Manifest $manifest): string => $manifest->getDependencyManager()->getName(),
                $manifests,
            ));

            return [
                'project' => $project,
                'manifestCount' => \count($manifests),
                'dependencyManagers' => $dependencyManagers,
                'lastScan' => $this->scanRepository->findLatestForProject($project),
            ];
        }, $projects);

        $scanStatusCounts = array_count_values(array_filter(array_map(
            static fn (array $row) => $row['lastScan']?->getStatus()->value,
            $rows,
        )));

        return $this->render('dashboard/index.html.twig', [
            'rows' => $rows,
            'projectCount' => \count($projects),
            'scanStatusCounts' => $scanStatusCounts,
        ]);
    }
}
