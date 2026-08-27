<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

use App\Enum\Technology;
use App\Service\PackageRegistry\PackageRegistryInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DependencyManagerInterface
{
    public function getName(): string;

    /**
     * @return string[]
     */
    public function getManifestFilenames(): array;

    /**
     * @return string[]
     */
    public function getLockFilenames(): array;

    public function supports(string $projectPath): bool;

    /**
     * @return DiscoveredDependency[]
     */
    public function getDependencies(string $manifestContent, ?string $lockContent): array;

    /**
     * The registry to query for this ecosystem's published package versions
     * (e.g. Packagist for Composer). Null when not implemented yet.
     */
    public function getRegistry(): ?PackageRegistryInterface;

    /**
     * The runtime version constraint this manifest declares for itself (e.g. Composer's
     * require.php, npm's engines.node), if any. Null when this manifest has none.
     */
    public function getRuntimeConstraint(string $manifestContent): ?string;

    /**
     * Which Technology getRuntimeConstraint()'s value maps to, if this ecosystem has one.
     */
    public function getRuntimeTechnology(): ?Technology;
}
