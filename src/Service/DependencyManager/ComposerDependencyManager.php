<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

use App\Service\PackageRegistry\PackageRegistryInterface;
use App\Service\PackageRegistry\PackagistClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ComposerDependencyManager implements DependencyManagerInterface
{
    public function __construct(
        #[Autowire(service: PackagistClient::class)]
        private readonly PackageRegistryInterface $registry,
    ) {
    }

    public function getName(): string
    {
        return 'Composer';
    }

    public function getManifestFilenames(): array
    {
        return ['composer.json'];
    }

    public function getLockFilenames(): array
    {
        return ['composer.lock'];
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
            'require' => 'require',
            'require-dev' => 'require-dev',
        ];

        $dependencies = [];
        foreach ($sections as $section => $type) {
            foreach ($manifest[$section] ?? [] as $name => $constraint) {
                if (!str_contains($name, '/')) {
                    // Platform requirement (php, ext-*, lib-*, ...), not a real Packagist package.
                    continue;
                }

                [$vendor, $packageName] = explode('/', $name, 2);

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
        foreach (['packages', 'packages-dev'] as $section) {
            foreach ($lock[$section] ?? [] as $package) {
                if (isset($package['name'], $package['version'])) {
                    $versions[$package['name']] = $package['version'];
                }
            }
        }

        return $versions;
    }
}
