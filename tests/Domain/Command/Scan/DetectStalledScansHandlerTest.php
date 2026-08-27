<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Scan;

use App\Domain\Command\Scan\DetectStalledScansCommand;
use App\Domain\Command\Scan\DetectStalledScansHandler;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Entity\Scan;
use App\Enum\ScanStatus;
use App\Repository\ScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DetectStalledScansHandlerTest extends TestCase
{
    public function testMarksStalledScansAsFailedAndFlushes(): void
    {
        $manifest = new Manifest(new Project('my-project', 'git@github.com:acme/my-project.git'), new DependencyManager('Composer'), 'composer.json', 'composer.lock');
        $stalled = new Scan($manifest, ScanStatus::IN_PROGRESS);
        $stalled->setStartedAt(new \DateTimeImmutable('-1 hour'));
        (new \ReflectionProperty(Scan::class, 'id'))->setValue($stalled, 1);

        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findStalledInProgress')->willReturn([$stalled]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $handler = new DetectStalledScansHandler($scanRepository, $entityManager, new NullLogger());

        $handler(new DetectStalledScansCommand());

        self::assertSame(ScanStatus::FAILED, $stalled->getStatus());
        self::assertNotNull($stalled->getError());
        self::assertNotNull($stalled->getCompletedAt());
    }

    public function testDoesNothingWhenNoScanIsStalled(): void
    {
        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findStalledInProgress')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $handler = new DetectStalledScansHandler($scanRepository, $entityManager, new NullLogger());

        $handler(new DetectStalledScansCommand());
    }
}
