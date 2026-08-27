<?php

declare(strict_types=1);

namespace App\Service\ManifestDiscovery;

use App\Service\DependencyManager\DependencyManagerInterface;
use App\Service\VCS\GitTree;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ManifestMatcher
{
    /**
     * @param iterable<DependencyManagerInterface> $dependencyManagers
     * @param string[]                              $excludedDirectories
     */
    public function __construct(
        #[AutowireIterator(DependencyManagerInterface::class)]
        private readonly iterable $dependencyManagers,
        private readonly array $excludedDirectories = ['.git', 'vendor', 'node_modules'],
    ) {
    }

    /**
     * @return DiscoveredManifest[]
     */
    public function match(GitTree $tree): array
    {
        $blobPaths = [];
        foreach ($tree->entries as $entry) {
            if ('blob' === $entry->type) {
                $blobPaths[$entry->path] = true;
            }
        }

        $manifestFilenames = $this->buildManifestFilenameMap();

        $discovered = [];
        foreach (array_keys($blobPaths) as $path) {
            if ($this->isExcluded($path)) {
                continue;
            }

            $filename = basename($path);

            if (!isset($manifestFilenames[$filename])) {
                continue;
            }

            $dependencyManager = $manifestFilenames[$filename];
            $lockPath = $this->findLockPath($path, $dependencyManager, $blobPaths);

            $discovered[] = new DiscoveredManifest($path, $lockPath, $dependencyManager);
        }

        return $discovered;
    }

    /**
     * @return array<string, DependencyManagerInterface>
     */
    private function buildManifestFilenameMap(): array
    {
        $map = [];
        foreach ($this->dependencyManagers as $dependencyManager) {
            foreach ($dependencyManager->getManifestFilenames() as $filename) {
                $map[$filename] = $dependencyManager;
            }
        }

        return $map;
    }

    /**
     * @param array<string, true> $blobPaths
     */
    private function findLockPath(string $manifestPath, DependencyManagerInterface $dependencyManager, array $blobPaths): ?string
    {
        $directory = \dirname($manifestPath);

        foreach ($dependencyManager->getLockFilenames() as $lockFilename) {
            $lockPath = '.' === $directory ? $lockFilename : $directory.'/'.$lockFilename;

            if (isset($blobPaths[$lockPath])) {
                return $lockPath;
            }
        }

        return null;
    }

    private function isExcluded(string $path): bool
    {
        $segments = explode('/', $path);
        array_pop($segments);

        foreach ($segments as $segment) {
            if (\in_array($segment, $this->excludedDirectories, true)) {
                return true;
            }
        }

        return false;
    }
}
