<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

use App\Repository\ProjectRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * "Maintenance de parc" only works if the dashboard reflects reality without someone
 * having to remember to click "Rescan" on every project. Fans out one RescanProjectCommand
 * per known project so dependency/technology data refreshes on its own.
 */
#[AsMessageHandler]
final class ScheduledRescanHandler
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ScheduledRescanCommand $command): void
    {
        foreach ($this->projectRepository->findAll() as $project) {
            $this->bus->dispatch(new RescanProjectCommand((string) $project->getId()));
        }
    }
}
