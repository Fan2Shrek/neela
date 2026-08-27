<?php

declare(strict_types=1);

namespace App\Domain\Command\Dependency;

final readonly class CheckDependencyVulnerabilitiesCommand
{
    public function __construct(
        public int $dependencyId,
    ) {
    }
}
