<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Manifest;
use App\Service\VCS\VCSResolver;

final class ManifestScanner
{
    public function __construct(
        private VCSResolver $vcsResolver,
    ) {
    }

    public function scan(Manifest $manifest): void
    {
        $project = $manifest->getProject();

        $vcsClient = $this->vcsResolver->resolve($project->getSshLink());

        $vcsInfo = $vcsClient->getVCSInfo($project->getSshLink());

        // parse manifest + lockfile via $manifest->getDependencyManager()

        // get dependencies
    }
}
