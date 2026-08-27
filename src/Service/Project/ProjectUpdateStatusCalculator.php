<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Enum\ProjectUpdateStatus;
use App\Repository\DependencyRepository;
use App\Repository\VersionRepository;
use App\Service\Cache\CacheTags;
use App\Service\Package\PackageUpdateChecker;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class ProjectUpdateStatusCalculator
{
    private const CACHE_KEY = 'project_update_status';

    public function __construct(
        private readonly DependencyRepository $dependencyRepository,
        private readonly VersionRepository $versionRepository,
        private readonly PackageUpdateChecker $packageUpdateChecker,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * Compares each dependency's locked version against the latest release still allowed
     * by its own constraint (not just the package's overall latest, which a narrow
     * constraint like "7.3.*" may not even permit installing). Projects with no
     * comparable dependency (nothing scanned yet, or no registry data fetched for any of
     * its packages) are left out entirely.
     *
     * Resolving a semver constraint against every dependency in the app is expensive
     * enough (~1.5s with hundreds of dependencies) to cache rather than redo on every
     * dashboard view. Depends on both scan data (dependencies/locked versions) and
     * fetched package versions, so it's tagged with both — invalidated by whichever
     * handler changes either one (see ScanManifestDependenciesHandler,
     * GetPackageVersionHandler) without either needing to know this specific cache key.
     * The TTL here is only a safety net against a missed invalidation path.
     *
     * @return array<string, ProjectUpdateStatus> project id => status
     */
    public function calculate(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(300);
            $item->tag([CacheTags::SCAN_DATA, CacheTags::PACKAGE_VERSIONS]);

            return $this->doCalculate();
        });
    }

    /**
     * @return array<string, ProjectUpdateStatus>
     */
    private function doCalculate(): array
    {
        $rows = $this->dependencyRepository->findLockedVersionsGroupedByProject();

        $packageIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['packageId'],
            $rows,
        )));
        $stableVersionsByPackageId = $this->versionRepository->findStableVersionsIndexedByPackageId($packageIds);

        $totalCounts = [];
        $outdatedCounts = [];
        $latestVersionCache = [];

        foreach ($rows as $row) {
            // Semver constraint resolution is expensive (each package can have hundreds
            // of releases to parse), and many projects share the exact same package at
            // the exact same constraint — cache the result per (package, constraint) pair
            // instead of recomputing it for every dependency row.
            $cacheKey = $row['packageId'].':'.$row['constraint'];

            if (!\array_key_exists($cacheKey, $latestVersionCache)) {
                $availableVersions = $stableVersionsByPackageId[$row['packageId']] ?? [];
                $latestVersionCache[$cacheKey] = $this->packageUpdateChecker->findLatestSatisfying($availableVersions, $row['constraint']);
            }

            $latestVersion = $latestVersionCache[$cacheKey];

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
