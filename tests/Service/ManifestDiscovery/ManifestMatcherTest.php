<?php

declare(strict_types=1);

namespace App\Tests\Service\ManifestDiscovery;

use App\Service\DependencyManager\ComposerDependencyManager;
use App\Service\DependencyManager\DependencyManagerInterface;
use App\Service\DependencyManager\NpmDependencyManager;
use App\Service\ManifestDiscovery\DiscoveredManifest;
use App\Service\ManifestDiscovery\ManifestMatcher;
use App\Service\VCS\GitTree;
use App\Service\VCS\GitTreeEntry;
use PHPUnit\Framework\TestCase;

final class ManifestMatcherTest extends TestCase
{
    public function testComposerManifestAtRoot(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()]);

        $discovered = $matcher->match($this->tree(['composer.json']));

        self::assertCount(1, $discovered);
        self::assertSame('composer.json', $discovered[0]->path);
        self::assertNull($discovered[0]->lockPath);
        self::assertSame('Composer', $discovered[0]->dependencyManager->getName());
    }

    public function testRepositoryWithoutManifest(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()]);

        $discovered = $matcher->match($this->tree(['README.md', 'src/App.php']));

        self::assertSame([], $discovered);
    }

    public function testMultipleComposerManifests(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()]);

        $discovered = $matcher->match($this->tree([
            'composer.json',
            'backend/composer.json',
            'packages/foo/composer.json',
        ]));

        self::assertSame(
            ['composer.json', 'backend/composer.json', 'packages/foo/composer.json'],
            $this->paths($discovered),
        );
    }

    public function testComposerAndNpmInSameRepository(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager(), new NpmDependencyManager()]);

        $discovered = $matcher->match($this->tree([
            'app/back/composer.json',
            'app/front/package.json',
        ]));

        $names = $this->dependencyManagerNamesByPath($discovered);

        self::assertSame('Composer', $names['app/back/composer.json']);
        self::assertSame('npm', $names['app/front/package.json']);
    }

    public function testManifestWithLockfile(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()]);

        $discovered = $matcher->match($this->tree(['app/back/composer.json', 'app/back/composer.lock']));

        self::assertCount(1, $discovered);
        self::assertSame('app/back/composer.lock', $discovered[0]->lockPath);
    }

    public function testManifestWithoutLockfile(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()]);

        $discovered = $matcher->match($this->tree(['app/back/composer.json']));

        self::assertCount(1, $discovered);
        self::assertNull($discovered[0]->lockPath);
    }

    public function testManifestsInSubdirectories(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager(), new NpmDependencyManager()]);

        $discovered = $matcher->match($this->tree([
            'frontend/package.json',
            'tools/package.json',
        ]));

        self::assertSame(['frontend/package.json', 'tools/package.json'], $this->paths($discovered));
    }

    public function testExcludedDirectoriesAreIgnored(): void
    {
        $matcher = new ManifestMatcher([new ComposerDependencyManager()], ['.git', 'vendor', 'node_modules']);

        $discovered = $matcher->match($this->tree([
            'composer.json',
            'vendor/some-package/composer.json',
            'node_modules/some-package/package.json',
            '.git/hooks/composer.json',
        ]));

        self::assertSame(['composer.json'], $this->paths($discovered));
    }

    public function testMultipleDependencyManagers(): void
    {
        $cargo = new class implements DependencyManagerInterface {
            public function getName(): string
            {
                return 'Cargo';
            }

            public function getManifestFilenames(): array
            {
                return ['Cargo.toml'];
            }

            public function getLockFilenames(): array
            {
                return ['Cargo.lock'];
            }

            public function supports(string $projectPath): bool
            {
                return \in_array(basename($projectPath), $this->getManifestFilenames(), true);
            }

            public function getDependencies(string $projectPath): array
            {
                return [];
            }
        };

        $matcher = new ManifestMatcher([new ComposerDependencyManager(), new NpmDependencyManager(), $cargo]);

        $discovered = $matcher->match($this->tree([
            'composer.json',
            'frontend/package.json',
            'engine/Cargo.toml',
            'engine/Cargo.lock',
        ]));

        $names = $this->dependencyManagerNamesByPath($discovered);

        self::assertSame('Composer', $names['composer.json']);
        self::assertSame('npm', $names['frontend/package.json']);
        self::assertSame('Cargo', $names['engine/Cargo.toml']);
    }

    private function tree(array $paths): GitTree
    {
        return new GitTree(
            array_map(static fn (string $path) => new GitTreeEntry($path, 'blob'), $paths),
            false,
        );
    }

    /**
     * @param DiscoveredManifest[] $discovered
     *
     * @return string[]
     */
    private function paths(array $discovered): array
    {
        return array_map(static fn (DiscoveredManifest $manifest) => $manifest->path, $discovered);
    }

    /**
     * @param DiscoveredManifest[] $discovered
     *
     * @return array<string, string>
     */
    private function dependencyManagerNamesByPath(array $discovered): array
    {
        $result = [];
        foreach ($discovered as $manifest) {
            $result[$manifest->path] = $manifest->dependencyManager->getName();
        }

        return $result;
    }
}
