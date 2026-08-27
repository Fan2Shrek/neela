<?php

declare(strict_types=1);

namespace App\Service\ManifestDiscovery;

use App\Service\DependencyManager\DependencyManagerInterface;

final readonly class DiscoveredManifest
{
    public function __construct(
        public string $path,
        public ?string $lockPath,
        public DependencyManagerInterface $dependencyManager,
    ) {
    }
}
