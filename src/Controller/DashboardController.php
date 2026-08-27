<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\ScanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ScanRepository $scanRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        $projects = $this->projectRepository->findAll();

        $scanStatusCounts = array_count_values(array_filter(array_map(
            fn (Project $project) => $this->scanRepository->findLatestForProject($project)?->getStatus()->value,
            $projects,
        )));

        return $this->render('dashboard/index.html.twig', [
            'projectCount' => \count($projects),
            'scanStatusCounts' => $scanStatusCounts,
        ]);
    }
}
