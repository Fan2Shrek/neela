<?php

declare(strict_types=1);

namespace App\Domain\Command\Package;

use App\Entity\Version;
use App\Repository\PackageRepository;
use App\Repository\VersionRepository;
use App\Service\DependencyManager\DependencyManagerResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPackageVersionHandler
{
    public function __construct(
        private PackageRepository $packageRepository,
        private VersionRepository $versionRepository,
        private DependencyManagerResolver $dependencyManagerResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetPackageVersionCommand $command): void
    {
        $package = $this->packageRepository->find($command->packageId)
            ?? throw new \RuntimeException(\sprintf('Package "%d" not found.', $command->packageId));

        $vendor = $package->getVendor();
        $dependencyManager = $this->dependencyManagerResolver->resolve($vendor->getDependencyManager()->getName());
        $registry = $dependencyManager->getRegistry();

        if (null === $registry) {
            // No registry client for this ecosystem yet: nothing to fetch.
            return;
        }

        foreach ($registry->getVersions($vendor->getName(), $package->getName()) as $versionData) {
            $version = $this->versionRepository->findOneBy([
                'package' => $package,
                'normalizedVersion' => $versionData->normalizedVersion,
            ]);

            if (null === $version) {
                $version = new Version($package, $versionData->version, $versionData->normalizedVersion, $versionData->stability);
                $this->entityManager->persist($version);
            } else {
                $version->setVersion($versionData->version);
                $version->setStability($versionData->stability);
            }

            $version->setReleasedAt($versionData->releasedAt);
            $version->setRuntimeConstraint($versionData->runtimeConstraint);
        }

        $this->entityManager->flush();

        // This handler runs inside a long-lived worker process that handles one message
        // per package, one after another, for as long as the worker stays up — nothing
        // else in that process still needs this package's entities afterwards. Without
        // clearing, hundreds of packages' worth of Package/Vendor/Version pile up in the
        // EntityManager's identity map across the batch until the worker itself OOMs.
        $this->entityManager->clear();
    }
}
