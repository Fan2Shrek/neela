<?php

declare(strict_types=1);

namespace App\Domain\Command\Project;

final readonly class RescanProjectCommand
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
