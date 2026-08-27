<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Manifest;

use App\Domain\Command\Manifest\ScanManifestDependenciesCommand;
use App\Domain\Command\Manifest\ScanManifestDependenciesHandler;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Entity\Scan;
use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use App\Service\ManifestScannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class ScanManifestDependenciesHandlerTest extends TestCase
{
    public function testSuccessfulScanMarksTheScanAsCompleted(): void
    {
        $scan = $this->scan();

        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('find')->willReturn($scan);

        $manifestScanner = $this->createMock(ManifestScannerInterface::class);
        $manifestScanner->expects(self::once())->method('scan')->with($scan->getManifest());

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(true);

        $handler = new ScanManifestDependenciesHandler(
            $entityManager,
            $this->createStub(ManagerRegistry::class),
            $manifestScanner,
            $scanRepository,
        );

        $handler(new ScanManifestDependenciesCommand(1));

        self::assertSame(ScanStatus::COMPLETED, $scan->getStatus());
        self::assertNotNull($scan->getCompletedAt());
    }

    public function testFailureRecordsErrorEvenWhenTheEntityManagerWasClosedByTheScan(): void
    {
        $scan = $this->scan();

        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('find')->willReturn($scan);

        $manifestScanner = $this->createStub(ManifestScannerInterface::class);
        $manifestScanner->method('scan')->willThrowException(new \RuntimeException('GitHub API is down.'));

        // Simulate a flush failure inside the scan leaving this handler's own EntityManager closed.
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(false);

        $freshEntityManager = $this->createMock(EntityManagerInterface::class);
        $freshEntityManager->method('find')->willReturn($scan);
        $freshEntityManager->expects(self::once())->method('flush');

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('resetManager')->willReturn($freshEntityManager);

        $handler = new ScanManifestDependenciesHandler(
            $entityManager,
            $managerRegistry,
            $manifestScanner,
            $scanRepository,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GitHub API is down.');

        try {
            $handler(new ScanManifestDependenciesCommand(1));
        } finally {
            self::assertSame(ScanStatus::FAILED, $scan->getStatus());
            self::assertSame('GitHub API is down.', $scan->getError());
        }
    }

    private function scan(): Scan
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $manifest = new Manifest($project, new DependencyManager('Composer'), 'composer.json', 'composer.lock');

        return new Scan($manifest);
    }
}
