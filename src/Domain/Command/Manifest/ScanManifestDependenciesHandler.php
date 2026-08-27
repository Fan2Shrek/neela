<?php

declare(strict_types=1);

namespace App\Domain\Command\Manifest;

use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use App\Service\ManifestScanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScanManifestDependenciesHandler
{
    public function __construct(
        private ManifestScanner $manifestScanner,
        private ScanRepository $scanRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ScanManifestDependenciesCommand $command): void
    {
        $scan = $this->scanRepository->find($command->scanId)
            ?? throw new \RuntimeException(\sprintf('Scan "%d" not found.', $command->scanId));

        $scan->setStatus(ScanStatus::IN_PROGRESS);
        $scan->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        try {
            $this->manifestScanner->scan($scan->getManifest());
        } catch (\Throwable $exception) {
            $scan->setStatus(ScanStatus::FAILED);
            $scan->setError($exception->getMessage());
            $scan->setCompletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            throw $exception;
        }

        $scan->setStatus(ScanStatus::COMPLETED);
        $scan->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
