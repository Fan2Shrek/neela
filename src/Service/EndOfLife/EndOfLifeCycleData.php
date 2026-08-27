<?php

declare(strict_types=1);

namespace App\Service\EndOfLife;

final readonly class EndOfLifeCycleData
{
    public function __construct(
        public string $cycle,
        public string $latestVersion,
        public bool $isLts,
        public ?\DateTimeImmutable $releaseDate,
        public ?\DateTimeImmutable $eolDate,
    ) {
    }
}
