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
use Symfony\Component\HttpFoundation\Request;
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
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $dependencyManagerName = trim((string) $request->query->get('dependency_manager', ''));

        $packages = $this->packageRepository->findAllWithVendor();
        $usage = $this->dependencyRepository->countUsageByPackage();

        $rows = array_map(static fn (Package $package): array => [
            'package' => $package,
            'projectCount' => $usage[$package->getId()]['projectCount'] ?? 0,
            'dependencyCount' => $usage[$package->getId()]['dependencyCount'] ?? 0,
        ], $packages);

        usort($rows, static function (array $a, array $b): int {
            $managerComparison = $a['package']->getVendor()->getDependencyManager()->getName()
                <=> $b['package']->getVendor()->getDependencyManager()->getName();

            return 0 !== $managerComparison ? $managerComparison : $b['dependencyCount'] <=> $a['dependencyCount'];
        });

        if ('' !== $search) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => false !== stripos(
                    $row['package']->getVendor()->getName().'/'.$row['package']->getName(),
                    $search,
                ),
            ));
        }

        if ('' !== $dependencyManagerName) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['package']->getVendor()->getDependencyManager()->getName() === $dependencyManagerName,
            ));
        }

        return $this->render('package/index.html.twig', [
            'rows' => $rows,
            'packageCount' => \count($packages),
            'search' => $search,
            'dependencyManagerName' => $dependencyManagerName,
            'dependencyManagers' => $this->dependencyManagerRepository->findAll(),
            'isFiltered' => '' !== $search || '' !== $dependencyManagerName,
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
