<?php

declare(strict_types=1);

namespace App\Service\Technology;

use App\Entity\Dependency;
use App\Enum\Technology;

final class TechnologyDetector
{
    /**
     * Looks for a "signal package" (e.g. symfony/framework-bundle) among a manifest's
     * dependencies to name the framework it's built on.
     *
     * @param Dependency[] $dependencies dependencies belonging to a single manifest
     */
    public function detect(array $dependencies): ?DetectedTechnology
    {
        foreach (Technology::cases() as $technology) {
            [$vendor, $name] = $technology->getSignalPackage();

            foreach ($dependencies as $dependency) {
                $package = $dependency->getPackage();

                if ($package->getVendor()->getName() === $vendor && $package->getName() === $name) {
                    return new DetectedTechnology($technology, $dependency);
                }
            }
        }

        return null;
    }
}
