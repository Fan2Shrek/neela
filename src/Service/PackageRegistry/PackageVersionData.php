<?php

declare(strict_types=1);

namespace App\Service\PackageRegistry;

use App\Enum\Stability;

final readonly class PackageVersionData
{
    public function __construct(
        public string $version,
        public string $normalizedVersion,
        public ?\DateTimeImmutable $releasedAt,
        public ?string $runtimeConstraint,
        public Stability $stability,
    ) {
    }
}
