<?php

declare(strict_types=1);

namespace App\Tests\Service\Package;

use App\Service\Package\PackageUpdateChecker;
use PHPUnit\Framework\TestCase;

final class PackageUpdateCheckerTest extends TestCase
{
    private PackageUpdateChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PackageUpdateChecker();
    }

    public function testIgnoresVersionsOutsideTheConstraint(): void
    {
        // symfony/web-profiler-bundle pinned to "7.3.*" must never resolve to a v8 release.
        $versions = ['v7.3.1', 'v7.3.2', 'v7.3.3', 'v8.0.0', 'v8.1.5'];

        self::assertSame('v7.3.3', $this->checker->findLatestSatisfying($versions, '7.3.*'));
    }

    public function testCaretConstraintAllowsMinorAndPatchOnly(): void
    {
        $versions = ['v1.0.0', 'v1.4.0', 'v1.9.9', 'v2.0.0'];

        self::assertSame('v1.9.9', $this->checker->findLatestSatisfying($versions, '^1.0'));
    }

    public function testReturnsNullWhenNoVersionSatisfiesTheConstraint(): void
    {
        $versions = ['v1.0.0', 'v2.0.0'];

        self::assertNull($this->checker->findLatestSatisfying($versions, '^3.0'));
    }

    public function testReturnsNullWhenNoVersionsAreAvailable(): void
    {
        self::assertNull($this->checker->findLatestSatisfying([], '^1.0'));
    }

    public function testUnderstandsNpmStyleXWildcards(): void
    {
        $versions = ['18.1.0', '18.2.0', '18.2.9', '19.0.0'];

        self::assertSame('18.2.9', $this->checker->findLatestSatisfying($versions, '18.2.x'));
    }

    /**
     * npm allows constraints Composer's parser can't make sense of at all (git URLs,
     * workspace/tag references, local paths, ...); these must degrade to "unknown"
     * rather than crash the page.
     */
    public function testReturnsNullInsteadOfThrowingOnAConstraintComposerCannotParse(): void
    {
        $versions = ['1.0.0', '2.0.0'];

        self::assertNull($this->checker->findLatestSatisfying($versions, 'workspace:*'));
        self::assertNull($this->checker->findLatestSatisfying($versions, 'git+https://github.com/facebook/react.git'));
        self::assertNull($this->checker->findLatestSatisfying($versions, 'latest'));
    }
}
