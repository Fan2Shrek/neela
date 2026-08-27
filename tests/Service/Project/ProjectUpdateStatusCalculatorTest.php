<?php

declare(strict_types=1);

namespace App\Tests\Service\Project;

use App\Enum\ProjectUpdateStatus;
use App\Repository\DependencyRepository;
use App\Repository\VersionRepository;
use App\Service\Package\PackageUpdateChecker;
use App\Service\Project\ProjectUpdateStatusCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Uid\Uuid;

final class ProjectUpdateStatusCalculatorTest extends TestCase
{
    public function testProjectsAreBucketedByUpdateStatus(): void
    {
        $upToDateProject = Uuid::v7();
        $partiallyUpToDateProject = Uuid::v7();
        $outdatedProject = Uuid::v7();
        $unknownPackageProject = Uuid::v7();

        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('findLockedVersionsGroupedByProject')->willReturn([
            // Package 1's absolute latest release is v3.0.0, but this constraint only
            // allows the 2.x line — v2.0.0 is genuinely up to date, not "behind v3".
            ['projectId' => $upToDateProject, 'packageId' => 1, 'lockedVersion' => 'v2.0.0', 'constraint' => '^2.0'],
            ['projectId' => $upToDateProject, 'packageId' => 2, 'lockedVersion' => 'v1.0.0', 'constraint' => '^1.0'],

            ['projectId' => $partiallyUpToDateProject, 'packageId' => 1, 'lockedVersion' => 'v2.0.0', 'constraint' => '^2.0'],
            ['projectId' => $partiallyUpToDateProject, 'packageId' => 2, 'lockedVersion' => 'v0.9.0', 'constraint' => '^1.0'],

            ['projectId' => $outdatedProject, 'packageId' => 1, 'lockedVersion' => 'v0.5.0', 'constraint' => '^1.0'],
            ['projectId' => $outdatedProject, 'packageId' => 2, 'lockedVersion' => 'v0.9.0', 'constraint' => '^1.0'],

            // Package 3 has no known registry data: not comparable, project has nothing else.
            ['projectId' => $unknownPackageProject, 'packageId' => 3, 'lockedVersion' => 'v1.0.0', 'constraint' => '^1.0'],
        ]);

        $versionRepository = $this->createStub(VersionRepository::class);
        $versionRepository->method('findStableVersionsIndexedByPackageId')->willReturn([
            1 => ['v1.0.0', 'v2.0.0', 'v3.0.0'],
            2 => ['v1.0.0'],
        ]);

        $calculator = new ProjectUpdateStatusCalculator($dependencyRepository, $versionRepository, new PackageUpdateChecker(), new TagAwareAdapter(new ArrayAdapter()));

        $statuses = $calculator->calculate();

        self::assertSame(ProjectUpdateStatus::UP_TO_DATE, $statuses[(string) $upToDateProject]);
        self::assertSame(ProjectUpdateStatus::PARTIALLY_UP_TO_DATE, $statuses[(string) $partiallyUpToDateProject]);
        self::assertSame(ProjectUpdateStatus::OUTDATED, $statuses[(string) $outdatedProject]);
        self::assertArrayNotHasKey((string) $unknownPackageProject, $statuses);
    }

    public function testResultIsCachedAcrossCalls(): void
    {
        $dependencyRepository = $this->createMock(DependencyRepository::class);
        $dependencyRepository->expects(self::once())
            ->method('findLockedVersionsGroupedByProject')
            ->willReturn([]);

        $versionRepository = $this->createStub(VersionRepository::class);
        $versionRepository->method('findStableVersionsIndexedByPackageId')->willReturn([]);

        $calculator = new ProjectUpdateStatusCalculator($dependencyRepository, $versionRepository, new PackageUpdateChecker(), new TagAwareAdapter(new ArrayAdapter()));

        $calculator->calculate();
        $calculator->calculate();
    }
}
