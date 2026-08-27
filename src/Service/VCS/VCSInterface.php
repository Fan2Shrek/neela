<?php

declare(strict_types=1);

namespace App\Service\VCS;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface VCSInterface
{
    public function supports(string $sshLink): bool;

    public function getVCSInfo(string $projectPath): VCSProject;

    public function getTree(string $sshLink): GitTree;

    public function getFileContent(string $sshLink, string $path): ?string;
}
