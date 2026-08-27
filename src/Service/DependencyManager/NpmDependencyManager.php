<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

use App\Enum\Technology;
use App\Service\PackageRegistry\NpmRegistryClient;
use App\Service\PackageRegistry\PackageRegistryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class NpmDependencyManager implements DependencyManagerInterface
{
    public function __construct(
        #[Autowire(service: NpmRegistryClient::class)]
        private readonly PackageRegistryInterface $registry,
    ) {
    }

    public function getName(): string
    {
        return 'npm';
    }

    public function getManifestFilenames(): array
    {
        return ['package.json'];
    }

    public function getLockFilenames(): array
    {
        return ['package-lock.json', 'npm-shrinkwrap.json'];
    }

    public function supports(string $projectPath): bool
    {
        return \in_array(basename($projectPath), $this->getManifestFilenames(), true);
    }

    public function getRegistry(): ?PackageRegistryInterface
    {
        return $this->registry;
    }

    public function getDependencies(string $manifestContent, ?string $lockContent): array
    {
        $manifest = json_decode($manifestContent, true, flags: \JSON_THROW_ON_ERROR);
        $lockedVersions = $this->extractLockedVersions($lockContent);

        $sections = [
            'dependencies' => 'require',
            'devDependencies' => 'require-dev',
        ];

        $dependencies = [];
        foreach ($sections as $section => $type) {
            foreach ($manifest[$section] ?? [] as $name => $constraint) {
                // Scoped packages (e.g. "@babel/core") already contain a "/"; unscoped
                // packages (e.g. "lodash") have no vendor, so they act as their own.
                [$vendor, $packageName] = str_contains($name, '/') ? explode('/', $name, 2) : [$name, $name];

                $dependencies[] = new DiscoveredDependency(
                    vendor: $vendor,
                    name: $packageName,
                    constraint: $constraint,
                    lockedVersion: $lockedVersions[$name] ?? null,
                    type: $type,
                );
            }
        }

        return $dependencies;
    }

    public function getRuntimeConstraint(string $manifestContent): ?string
    {
        $manifest = json_decode($manifestContent, true, flags: \JSON_THROW_ON_ERROR);

        return $manifest['engines']['node'] ?? null;
    }

    public function getRuntimeTechnology(): ?Technology
    {
        return Technology::NODE;
    }

    /**
     * @return array<string, string>
     */
    private function extractLockedVersions(?string $lockContent): array
    {
        if (null === $lockContent) {
            return [];
        }

        $lock = json_decode($lockContent, true, flags: \JSON_THROW_ON_ERROR);

        $versions = [];
        foreach ($lock['packages'] ?? [] as $path => $package) {
            if ('' === $path || !isset($package['version'])) {
                // Skip the root package entry (empty path key).
                continue;
            }

            $name = preg_replace('#^.*node_modules/#', '', (string) $path);
            $versions[$name] = $package['version'];
        }

        if ([] === $versions) {
            // Legacy lockfile v1 format.
            foreach ($lock['dependencies'] ?? [] as $name => $package) {
                if (isset($package['version'])) {
                    $versions[$name] = $package['version'];
                }
            }
        }

        return $versions;
    }
}
