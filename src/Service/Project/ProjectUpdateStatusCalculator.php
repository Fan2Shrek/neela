<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Enum\ProjectUpdateStatus;
use App\Repository\DependencyRepository;
use App\Repository\VersionRepository;
use App\Service\Package\PackageUpdateChecker;

final class ProjectUpdateStatusCalculator
{
    public function __construct(
        private readonly DependencyRepository $dependencyRepository,
        private readonly VersionRepository $versionRepository,
        private readonly PackageUpdateChecker $packageUpdateChecker,
    ) {
    }

    /**
     * Compares each dependency's locked version against the latest release still allowed
     * by its own constraint (not just the package's overall latest, which a narrow
     * constraint like "7.3.*" may not even permit installing). Projects with no
     * comparable dependency (nothing scanned yet, or no registry data fetched for any of
     * its packages) are left out entirely.
     *
     * @return array<string, ProjectUpdateStatus> project id => status
     */
    public function calculate(): array
    {
        $rows = $this->dependencyRepository->findLockedVersionsGroupedByProject();

        $packageIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['packageId'],
            $rows,
        )));
        $stableVersionsByPackageId = $this->versionRepository->findStableVersionsIndexedByPackageId($packageIds);

        $totalCounts = [];
        $outdatedCounts = [];

        foreach ($rows as $row) {
            $availableVersions = $stableVersionsByPackageId[$row['packageId']] ?? [];
            $latestVersion = $this->packageUpdateChecker->findLatestSatisfying($availableVersions, $row['constraint']);

            if (null === $latestVersion) {
                continue;
            }

            $projectId = (string) $row['projectId'];
            $totalCounts[$projectId] = ($totalCounts[$projectId] ?? 0) + 1;

            if ($row['lockedVersion'] !== $latestVersion) {
                $outdatedCounts[$projectId] = ($outdatedCounts[$projectId] ?? 0) + 1;
            }
        }

        $statuses = [];
        foreach ($totalCounts as $projectId => $total) {
            $outdated = $outdatedCounts[$projectId] ?? 0;

            $statuses[$projectId] = match (true) {
                0 === $outdated => ProjectUpdateStatus::UP_TO_DATE,
                $outdated === $total => ProjectUpdateStatus::OUTDATED,
                default => ProjectUpdateStatus::PARTIALLY_UP_TO_DATE,
            };
        }

        return $statuses;
    }
}
