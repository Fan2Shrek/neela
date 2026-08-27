<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

use App\Domain\Command\Manifest\ScanManifestDependenciesCommand;
use App\Entity\Scan;
use App\Repository\ManifestRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RescanProjectHandler
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ManifestRepository $manifestRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RescanProjectCommand $command): void
    {
        $project = $this->projectRepository->find($command->projectId)
            ?? throw new \RuntimeException(\sprintf('Project "%s" not found.', $command->projectId));

        $manifests = $this->manifestRepository->findBy(['project' => $project]);

        $scans = [];
        foreach ($manifests as $manifest) {
            $scan = new Scan($manifest);
            $this->entityManager->persist($scan);
            $scans[] = $scan;
        }

        $this->entityManager->flush();

        foreach ($scans as $scan) {
            $this->bus->dispatch(new ScanManifestDependenciesCommand($scan->getId()));
        }
    }
}
