<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

final class NpmDependencyManager implements DependencyManagerInterface
{
    public function getName(): string
    {
        return 'npm';
    }

    public function getManifestFilenames(): array
    {
        return ['package.json'];
    }

    public function getLockFilenames(): array
    {
        return ['package-lock.json', 'npm-shrinkwrap.json'];
    }

    public function supports(string $projectPath): bool
    {
        return \in_array(basename($projectPath), $this->getManifestFilenames(), true);
    }

    public function getDependencies(string $projectPath): array
    {
        throw new \LogicException('Not implemented yet.');
    }
}
