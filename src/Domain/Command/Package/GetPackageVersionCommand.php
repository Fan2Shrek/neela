<?php

declare(strict_types=1);

namespace App\Domain\Command\Package;

final readonly class GetPackageVersionCommand
{
    public function __construct(
        public int $packageId,
    ) {
    }
}
