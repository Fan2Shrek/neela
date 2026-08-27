<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Enum\ProjectUpdateStatus;
use App\Repository\ProjectRepository;
use App\Repository\ScanRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\Messenger\QueueDepthProvider;
use App\Service\Project\ProjectUpdateStatusCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ScanRepository $scanRepository,
        private readonly ProjectUpdateStatusCalculator $projectUpdateStatusCalculator,
        private readonly QueueDepthProvider $queueDepthProvider,
        private readonly VulnerabilityRepository $vulnerabilityRepository,
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

        $updateStatuses = $this->projectUpdateStatusCalculator->calculate();

        $updateStatusCounts = array_count_values(array_map(
            static fn (ProjectUpdateStatus $status): string => $status->value,
            $updateStatuses,
        ));

        $severity = static fn (ProjectUpdateStatus $status): int => match ($status) {
            ProjectUpdateStatus::OUTDATED => 0,
            ProjectUpdateStatus::PARTIALLY_UP_TO_DATE => 1,
            ProjectUpdateStatus::UP_TO_DATE => 2,
        };

        $projectsNeedingUpdate = array_values(array_filter(array_map(
            static function (Project $project) use ($updateStatuses): ?array {
                $status = $updateStatuses[(string) $project->getId()] ?? null;

                return (null === $status || ProjectUpdateStatus::UP_TO_DATE === $status)
                    ? null
                    : ['project' => $project, 'status' => $status];
            },
            $projects,
        )));

        usort($projectsNeedingUpdate, static fn (array $a, array $b): int => $severity($a['status']) <=> $severity($b['status']));

        return $this->render('dashboard/index.html.twig', [
            'projectCount' => \count($projects),
            'scanStatusCounts' => $scanStatusCounts,
            'updateStatusCounts' => $updateStatusCounts,
            'projectsNeedingUpdate' => $projectsNeedingUpdate,
            'asyncQueueDepth' => $this->queueDepthProvider->getAsyncQueueDepth(),
            'failedQueueDepth' => $this->queueDepthProvider->getFailedQueueDepth(),
            'vulnerableDependencyCount' => $this->vulnerabilityRepository->countAffectedDependencies(),
        ]);
    }
}
