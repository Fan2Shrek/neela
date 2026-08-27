<?php

declare(strict_types=1);

namespace App\Domain\Command\Manifest;

use App\Entity\Scan;
use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use App\Service\Cache\CacheTags;
use App\Service\ManifestScannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final class ScanManifestDependenciesHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private ManifestScannerInterface $manifestScanner,
        private ScanRepository $scanRepository,
        private TagAwareCacheInterface $cache,
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
            $this->recordFailure($command->scanId, $exception->getMessage());

            throw $exception;
        } finally {
            // Dependencies may already have been persisted by the scanner even if
            // something later fails, so invalidate regardless of the outcome.
            $this->cache->invalidateTags([CacheTags::SCAN_DATA]);
        }

        $scan->setStatus(ScanStatus::COMPLETED);
        $scan->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * A failed flush inside the manifest scan can leave this handler's own EntityManager
     * closed. When that happens, get a fresh one from the registry so the failure can
     * still be recorded on the scan instead of masking the original exception.
     */
    private function recordFailure(int $scanId, string $error): void
    {
        $entityManager = $this->entityManager->isOpen()
            ? $this->entityManager
            : $this->managerRegistry->resetManager();

        $scan = $entityManager->find(Scan::class, $scanId);

        if (null === $scan) {
            return;
        }

        $scan->setStatus(ScanStatus::FAILED);
        $scan->setError($error);
        $scan->setCompletedAt(new \DateTimeImmutable());
        $entityManager->flush();
    }
}
