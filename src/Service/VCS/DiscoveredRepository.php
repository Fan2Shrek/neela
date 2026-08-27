<?php

declare(strict_types=1);

namespace App\Service\VCS;

final readonly class DiscoveredRepository
{
    public function __construct(
        public string $name,
        public string $sshLink,
        public bool $private,
    ) {
    }
}
