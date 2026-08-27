<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

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
}
