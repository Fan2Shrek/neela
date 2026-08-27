<?php

declare(strict_types=1);

namespace App\Service\VCS;

final readonly class GitTree
{
    /**
     * @param GitTreeEntry[] $entries
     */
    public function __construct(
        public array $entries,
        public bool $truncated,
    ) {
    }
}
