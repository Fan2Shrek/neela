<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Dependency;
use App\Entity\Package;
use App\Repository\DependencyManagerRepository;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use App\Repository\VersionRepository;
use App\Service\Package\PackageUpdateChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PackageController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
        private readonly DependencyManagerRepository $dependencyManagerRepository,
        private readonly VersionRepository $versionRepository,
        private readonly PackageUpdateChecker $packageUpdateChecker,
    ) {
    }

    #[Route('/packages', name: 'app_package_index', methods: ['GET'])]
    public function index(): Response
    {
        $packages = $this->packageRepository->findAllWithVendor();
        $usage = $this->dependencyRepository->countUsageByPackage();

        $rows = array_map(static fn (Package $package): array => [
            'package' => $package,
            'projectCount' => $usage[$package->getId()]['projectCount'] ?? 0,
            'dependencyCount' => $usage[$package->getId()]['dependencyCount'] ?? 0,
        ], $packages);

        // Grouped by ecosystem, most-used package first within each; filtering itself is
        // client-side (see list_filter_controller.js), rows just carry data-* to match on.
        usort($rows, static function (array $a, array $b): int {
            $managerComparison = $a['package']->getVendor()->getDependencyManager()->getName()
                <=> $b['package']->getVendor()->getDependencyManager()->getName();

            return 0 !== $managerComparison ? $managerComparison : $b['dependencyCount'] <=> $a['dependencyCount'];
        });

        return $this->render('package/index.html.twig', [
            'rows' => $rows,
            'packageCount' => \count($packages),
            'dependencyManagers' => $this->dependencyManagerRepository->findAll(),
        ]);
    }

    #[Route('/packages/{id}', name: 'app_package_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Package $package): Response
    {
        $dependencies = $this->dependencyRepository->findByPackageWithProject($package);
        $stableVersions = $this->versionRepository->findStableVersionsIndexedByPackageId([$package->getId()])[$package->getId()] ?? [];

        $rows = array_map(function (Dependency $dependency) use ($stableVersions): array {
            $latestVersion = $this->packageUpdateChecker->findLatestSatisfying($stableVersions, $dependency->getConstraint());

            $status = match (true) {
                null === $latestVersion => 'unknown',
                $dependency->getLockedVersion() === $latestVersion => 'up_to_date',
                default => 'outdated',
            };

            return [
                'dependency' => $dependency,
                'latestVersion' => $latestVersion,
                'status' => $status,
            ];
        }, $dependencies);

        return $this->render('package/show.html.twig', [
            'package' => $package,
            'rows' => $rows,
            'dependencyCount' => \count($dependencies),
            'projectCount' => \count(array_unique(array_map(
                static fn (Dependency $dependency): string => (string) $dependency->getManifest()->getProject()->getId(),
                $dependencies,
            ))),
            'latestVersion' => $this->packageUpdateChecker->findLatest($stableVersions),
        ]);
    }
}
