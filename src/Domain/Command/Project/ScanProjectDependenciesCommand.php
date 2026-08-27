<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

final readonly class ScanProjectDependenciesCommand
{
    public function __construct(
        public int $scanId,
    ) {
    }
}
