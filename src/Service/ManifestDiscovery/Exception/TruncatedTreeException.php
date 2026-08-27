<?php

declare(strict_types=1);

namespace App\Service\ManifestDiscovery\Exception;

use App\Entity\Project;

final class TruncatedTreeException extends \RuntimeException
{
    public function __construct(Project $project)
    {
        parent::__construct(\sprintf(
            'The Git tree for project "%s" was truncated by the GitHub API; manifest discovery cannot guarantee completeness.',
            $project->getName(),
        ));
    }
}
