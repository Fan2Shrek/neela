<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vendor;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use App\Repository\VendorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VendorController extends AbstractController
{
    public function __construct(
        private readonly VendorRepository $vendorRepository,
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
    ) {
    }

    #[Route('/vendors', name: 'app_vendor_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $vendors = $this->vendorRepository->findAllWithDependencyManager();
        $packageCounts = $this->packageRepository->countByVendor();
        $projectCounts = $this->dependencyRepository->countDistinctProjectsByVendor();

        $rows = array_map(static fn (Vendor $vendor): array => [
            'vendor' => $vendor,
            'packageCount' => $packageCounts[$vendor->getId()] ?? 0,
            'projectCount' => $projectCounts[$vendor->getId()] ?? 0,
        ], $vendors);

        return $this->render('vendor/index.html.twig', [
            'rows' => $rows,
            'vendorCount' => \count($vendors),
        ]);
    }
}
