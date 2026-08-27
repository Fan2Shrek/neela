<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Package;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
    ) {
    }

    #[Route('/packages', name: 'app_package_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $packages = $this->packageRepository->findAllWithVendor();
        $usage = $this->dependencyRepository->countUsageByPackage();

        $rows = array_map(static fn (Package $package): array => [
            'package' => $package,
            'projectCount' => $usage[$package->getId()]['projectCount'] ?? 0,
            'dependencyCount' => $usage[$package->getId()]['dependencyCount'] ?? 0,
        ], $packages);

        return $this->render('package/index.html.twig', [
            'rows' => $rows,
            'packageCount' => \count($packages),
        ]);
    }
}
