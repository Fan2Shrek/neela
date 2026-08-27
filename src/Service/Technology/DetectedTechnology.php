<?php

declare(strict_types=1);

namespace App\Service\Technology;

use App\Entity\Dependency;
use App\Enum\Technology;

final readonly class DetectedTechnology
{
    public function __construct(
        public Technology $technology,
        public Dependency $dependency,
    ) {
    }
}
