<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Package;
use App\Repository\DependencyManagerRepository;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
        private readonly DependencyManagerRepository $dependencyManagerRepository,
    ) {
    }

    #[Route('/packages', name: 'app_package_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
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

        usort($rows, static fn (array $a, array $b): int => $b['dependencyCount'] <=> $a['dependencyCount']) ?: $rows;

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
}
