<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

final class ComposerDependencyManager implements DependencyManagerInterface
{
    public function getName(): string
    {
        return 'Composer';
    }

    public function getManifestFilenames(): array
    {
        return ['composer.json'];
    }

    public function getLockFilenames(): array
    {
        return ['composer.lock'];
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
