<?php

declare(strict_types=1);

namespace App\Domain\Command\Scan;

use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A worker can die mid-scan (OOM, killed, network partition) without ever getting the
 * chance to mark its Scan as failed, leaving it "in_progress" forever. That silently
 * hides incomplete dependency/technology data behind what looks like a scan still running.
 */
#[AsMessageHandler]
final class DetectStalledScansHandler
{
    private const int STALLED_AFTER_MINUTES = 15;

    public function __construct(
        private readonly ScanRepository $scanRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DetectStalledScansCommand $command): void
    {
        $threshold = new \DateTimeImmutable(\sprintf('-%d minutes', self::STALLED_AFTER_MINUTES));
        $stalledScans = $this->scanRepository->findStalledInProgress($threshold);

        foreach ($stalledScans as $scan) {
            $scan->setStatus(ScanStatus::FAILED);
            $scan->setError(\sprintf(
                'Scan timed out after %d minutes without completing (worker likely crashed or was killed).',
                self::STALLED_AFTER_MINUTES,
            ));
            $scan->setCompletedAt(new \DateTimeImmutable());

            $this->logger->warning('Marked stalled scan {scanId} as failed.', ['scanId' => $scan->getId()]);
        }

        if ([] !== $stalledScans) {
            $this->entityManager->flush();
        }
    }
}
