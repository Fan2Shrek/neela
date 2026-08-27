<?php

declare(strict_types=1);

namespace App\Service\ManifestDiscovery;

use App\Entity\Manifest;
use App\Entity\Project;

interface ManifestDiscoveryInterface
{
    /**
     * @return Manifest[]
     */
    public function discover(Project $project): array;
}
