<?php

declare(strict_types=1);

namespace App\Service\PackageRegistry;

final readonly class PackageVersionData
{
    public function __construct(
        public string $version,
        public string $normalizedVersion,
        public ?\DateTimeImmutable $releasedAt,
        public ?string $runtimeConstraint,
    ) {
    }
}
