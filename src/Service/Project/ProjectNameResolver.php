<?php

declare(strict_types=1);

namespace App\Service\Project;

final class ProjectNameResolver
{
    public function resolve(string $sshLink): string
    {
        $path = rtrim(preg_replace('#\.git$#', '', trim($sshLink)), '/');
        $segments = preg_split('#[:/]#', $path);
        $name = end($segments);

        return $name ?: $sshLink;
    }
}
