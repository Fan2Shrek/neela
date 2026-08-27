<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use App\Service\ProjectScanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScanProjectDependenciesHandler
{
    public function __construct(
        private ProjectScanner $projectScanner,
        private ScanRepository $scanRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ScanProjectDependenciesCommand $command): void
    {
        $scan = $this->scanRepository->find($command->scanId)
            ?? throw new \RuntimeException(\sprintf('Scan "%d" not found.', $command->scanId));

        $scan->setStatus(ScanStatus::IN_PROGRESS);
        $scan->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        try {
            $this->projectScanner->scan($scan->getProject());
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
