<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Service\VCS\VCSResolver;

final class ProjectScanner
{
    public function __construct(
        private VCSResolver $vcsResolver,
    ) {
    }

    public function scan(Project $project)
    {
        $vcsClient = $this->vcsResolver->resolve($project->getSshLink());

        $vcsInfo = $vcsClient->getVCSInfo($project->getSshLink());

        // get dependencyManager

        // get dependencies
    }
}
