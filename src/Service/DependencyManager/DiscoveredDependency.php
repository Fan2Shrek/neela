<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

final readonly class DiscoveredDependency
{
    public function __construct(
        public string $vendor,
        public string $name,
        public string $constraint,
        public ?string $lockedVersion,
        public string $type,
    ) {
    }
}
