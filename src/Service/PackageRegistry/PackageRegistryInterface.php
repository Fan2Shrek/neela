<?php

declare(strict_types=1);

namespace App\Service\PackageRegistry;

interface PackageRegistryInterface
{
    /**
     * @return PackageVersionData[]
     */
    public function getVersions(string $vendor, string $name): array;
}
