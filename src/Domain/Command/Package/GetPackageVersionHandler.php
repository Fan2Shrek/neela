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

        $versions = [];
        foreach ($registry->getVersions($vendor->getName(), $package->getName()) as $versionData) {
            $version = $this->versionRepository->findOneBy([
                'package' => $package,
                'normalizedVersion' => $versionData->normalizedVersion,
            ]);

            if (null === $version) {
                $version = new Version($package, $versionData->version, $versionData->normalizedVersion);
                $this->entityManager->persist($version);
            } else {
                $version->setVersion($versionData->version);
            }

            $version->setReleasedAt($versionData->releasedAt);
            $version->setRuntimeConstraint($versionData->runtimeConstraint);
            $versions[] = $version;
        }

        $this->entityManager->flush();

        // A single package can have hundreds of releases on the registry. Detach them
        // once persisted so they don't pile up in the EntityManager's identity map for
        // the rest of this (still fully synchronous) request — Package/Vendor stay
        // managed since the caller (ManifestScanner) keeps using them afterwards.
        foreach ($versions as $version) {
            $this->entityManager->detach($version);
        }
    }
}
