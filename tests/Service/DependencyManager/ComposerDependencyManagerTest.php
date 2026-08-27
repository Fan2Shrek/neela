<?php

declare(strict_types=1);

namespace App\Tests\Service\DependencyManager;

use App\Service\DependencyManager\ComposerDependencyManager;
use PHPUnit\Framework\TestCase;

final class ComposerDependencyManagerTest extends TestCase
{
    public function testExtractsRequireAndRequireDevDependenciesWithLockedVersions(): void
    {
        $manifest = json_encode([
            'require' => [
                'php' => '^8.2',
                'symfony/console' => '^6.4',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
        ]);

        $lock = json_encode([
            'packages' => [
                ['name' => 'symfony/console', 'version' => 'v6.4.18'],
            ],
            'packages-dev' => [
                ['name' => 'phpunit/phpunit', 'version' => '10.5.9'],
            ],
        ]);

        $dependencies = (new ComposerDependencyManager())->getDependencies($manifest, $lock);

        self::assertCount(2, $dependencies);

        $byName = [];
        foreach ($dependencies as $dependency) {
            $byName[$dependency->vendor.'/'.$dependency->name] = $dependency;
        }

        self::assertSame('^6.4', $byName['symfony/console']->constraint);
        self::assertSame('v6.4.18', $byName['symfony/console']->lockedVersion);
        self::assertSame('require', $byName['symfony/console']->type);

        self::assertSame('require-dev', $byName['phpunit/phpunit']->type);
        self::assertSame('10.5.9', $byName['phpunit/phpunit']->lockedVersion);
    }

    public function testPlatformRequirementsAreIgnored(): void
    {
        $manifest = json_encode([
            'require' => [
                'php' => '^8.2',
                'ext-json' => '*',
                'symfony/console' => '^6.4',
            ],
        ]);

        $dependencies = (new ComposerDependencyManager())->getDependencies($manifest, null);

        self::assertCount(1, $dependencies);
        self::assertSame('console', $dependencies[0]->name);
    }

    public function testWithoutLockfileLockedVersionIsNull(): void
    {
        $manifest = json_encode(['require' => ['symfony/console' => '^6.4']]);

        $dependencies = (new ComposerDependencyManager())->getDependencies($manifest, null);

        self::assertCount(1, $dependencies);
        self::assertNull($dependencies[0]->lockedVersion);
    }
}
