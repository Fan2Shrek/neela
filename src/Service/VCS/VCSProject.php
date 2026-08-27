<?php

declare(strict_types=1);

namespace App\Service\VCS;

final readonly class VCSProject
{
    public function __construct(
        public string $name,
        public string $owner,
    ) {
    }
}
