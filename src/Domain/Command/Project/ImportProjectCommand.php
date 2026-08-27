<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

final readonly class ImportProjectCommand
{
    public function __construct(
        public string $sshLink,
        public bool $scanNow = true,
    ) {
    }
}
