<?php

declare(strict_types=1);

namespace App\Service\EndOfLife;

interface EndOfLifeClientInterface
{
    /**
     * @return EndOfLifeCycleData[]
     */
    public function getCycles(string $productSlug): array;
}
