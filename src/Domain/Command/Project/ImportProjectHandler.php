<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

use App\Domain\Command\Manifest\ScanManifestDependenciesCommand;
use App\Entity\Project;
use App\Entity\Scan;
use App\Repository\ProjectRepository;
use App\Service\ManifestDiscovery\ManifestDiscoveryInterface;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ImportProjectHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectRepository $projectRepository,
        private VCSResolver $vcsResolver,
        private ManifestDiscoveryInterface $manifestDiscoveryService,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ImportProjectCommand $command): void
    {
        $existing = $this->projectRepository->findOneBySshLink($command->sshLink);

        if (null !== $existing) {
            // Already imported: re-scan its known manifests instead of creating a duplicate project.
            if ($command->scanNow) {
                $this->bus->dispatch(new RescanProjectCommand((string) $existing->getId()));
            }

            return;
        }

        $vcs = $this->vcsResolver->resolve($command->sshLink);
        $vcsProject = $vcs->getVCSInfo($command->sshLink);

        $entity = new Project($vcsProject->name, $command->sshLink);

        $this->em->persist($entity);
        $this->em->flush();

        $manifests = $this->manifestDiscoveryService->discover($entity);

        if ($command->scanNow) {
            $scans = [];

            foreach ($manifests as $manifest) {
                $scan = new Scan($manifest);

                $this->em->persist($scan);
                $scans[] = $scan;
            }

            $this->em->flush();

            foreach ($scans as $scan) {
                $this->bus->dispatch(new ScanManifestDependenciesCommand($scan->getId()));
            }
        }
    }
}
