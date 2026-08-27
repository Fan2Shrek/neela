<?php

declare(strict_types=1);

namespace App\Domain\Command\Manifest;

final readonly class ScanManifestDependenciesCommand
{
    public function __construct(
        public int $scanId,
    ) {
    }
}
