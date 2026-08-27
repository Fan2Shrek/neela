<?php

declare(strict_types=1);

namespace App\Service\VCS;

final readonly class GitTreeEntry
{
    public function __construct(
        public string $path,
        public string $type,
    ) {
    }
}
