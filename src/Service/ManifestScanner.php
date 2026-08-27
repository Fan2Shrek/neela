<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Command\Dependency\CheckDependencyVulnerabilitiesCommand;
use App\Domain\Command\Package\GetPackageVersionCommand;
use App\Entity\Dependency;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Vendor;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use App\Repository\VendorRepository;
use App\Service\DependencyManager\DependencyManagerResolver;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ManifestScanner implements ManifestScannerInterface
{
    public function __construct(
        private readonly VCSResolver $vcsResolver,
        private readonly DependencyManagerResolver $dependencyManagerResolver,
        private readonly VendorRepository $vendorRepository,
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function scan(Manifest $manifest): void
    {
        if (null === $manifest->getLockPath()) {
            // Nothing to compare an installed version against without a lockfile.
            return;
        }

        $project = $manifest->getProject();
        $vcs = $this->vcsResolver->resolve($project->getSshLink());

        $manifestContent = $vcs->getFileContent($project->getSshLink(), $manifest->getPath());
        $lockContent = $vcs->getFileContent($project->getSshLink(), $manifest->getLockPath());

        if (null === $manifestContent || null === $lockContent) {
            // The manifest or lockfile disappeared from the repository since it was discovered.
            return;
        }

        $definition = $this->dependencyManagerResolver->resolve($manifest->getDependencyManager()->getName());

        /** @var array<string, Vendor> $vendorCache */
        $vendorCache = [];
        /** @var array<string, Package> $packageCache */
        $packageCache = [];
        /** @var Dependency[] $dependencies */
        $dependencies = [];

        foreach ($definition->getDependencies($manifestContent, $lockContent) as $discovered) {
            if (null === $discovered->lockedVersion) {
                continue;
            }

            $vendor = $vendorCache[$discovered->vendor] ??= $this->resolveVendor($manifest, $discovered->vendor);
            $packageKey = $discovered->vendor.'/'.$discovered->name;

            if (!isset($packageCache[$packageKey])) {
                $packageCache[$packageKey] = $this->resolvePackage($vendor, $discovered->name);
                $this->bus->dispatch(new GetPackageVersionCommand($packageCache[$packageKey]->getId()));
            }

            $package = $packageCache[$packageKey];

            $dependency = $this->dependencyRepository->findOneBy(['manifest' => $manifest, 'package' => $package]);

            if (null === $dependency) {
                $dependency = new Dependency($manifest, $package, $discovered->constraint, $discovered->lockedVersion, $discovered->type);
                $this->entityManager->persist($dependency);
            } else {
                $dependency->setConstraint($discovered->constraint);
                $dependency->setLockedVersion($discovered->lockedVersion);
                $dependency->setDependencyType($discovered->type);
            }

            $dependencies[] = $dependency;
        }

        $this->entityManager->flush();

        foreach ($dependencies as $dependency) {
            $this->bus->dispatch(new CheckDependencyVulnerabilitiesCommand($dependency->getId()));
        }
    }

    private function resolveVendor(Manifest $manifest, string $name): Vendor
    {
        $vendor = $this->vendorRepository->findOneBy(['dependencyManager' => $manifest->getDependencyManager(), 'name' => $name]);

        if (null === $vendor) {
            $vendor = new Vendor($name, $manifest->getDependencyManager());
            $this->entityManager->persist($vendor);
            // Flush now: the vendor's id is needed right away to look up its packages.
            $this->entityManager->flush();
        }

        return $vendor;
    }

    private function resolvePackage(Vendor $vendor, string $name): Package
    {
        $package = $this->packageRepository->findOneBy(['vendor' => $vendor, 'name' => $name]);

        if (null === $package) {
            $package = new Package($name, $vendor);
            $this->entityManager->persist($package);
            // Flush now: the package's id is needed right away to look up its dependency rows.
            $this->entityManager->flush();
        }

        return $package;
    }
}
