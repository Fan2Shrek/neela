<?php

declare(strict_types=1);

namespace App\Tests\Service\DependencyManager;

use App\Service\DependencyManager\NpmDependencyManager;
use App\Service\PackageRegistry\PackageRegistryInterface;
use PHPUnit\Framework\TestCase;

final class NpmDependencyManagerTest extends TestCase
{
    public function testExtractsScopedAndUnscopedDependenciesWithLockedVersions(): void
    {
        $manifest = json_encode([
            'dependencies' => [
                'lodash' => '^4.17.21',
                '@babel/core' => '^7.20.0',
            ],
            'devDependencies' => [
                'jest' => '^29.0.0',
            ],
        ]);

        $lock = json_encode([
            'packages' => [
                '' => ['version' => '1.0.0'],
                'node_modules/lodash' => ['version' => '4.17.21'],
                'node_modules/@babel/core' => ['version' => '7.20.5'],
                'node_modules/jest' => ['version' => '29.7.0'],
            ],
        ]);

        $dependencies = $this->npm()->getDependencies($manifest, $lock);

        self::assertCount(3, $dependencies);

        $byName = [];
        foreach ($dependencies as $dependency) {
            $byName[$dependency->vendor.'/'.$dependency->name] = $dependency;
        }

        self::assertSame('lodash', $byName['lodash/lodash']->vendor);
        self::assertSame('lodash', $byName['lodash/lodash']->name);
        self::assertSame('4.17.21', $byName['lodash/lodash']->lockedVersion);
        self::assertSame('require', $byName['lodash/lodash']->type);

        self::assertSame('@babel', $byName['@babel/core']->vendor);
        self::assertSame('core', $byName['@babel/core']->name);
        self::assertSame('7.20.5', $byName['@babel/core']->lockedVersion);

        self::assertSame('require-dev', $byName['jest/jest']->type);
    }

    public function testFallsBackToLegacyLockfileV1Format(): void
    {
        $manifest = json_encode(['dependencies' => ['lodash' => '^4.17.21']]);

        $lock = json_encode([
            'dependencies' => [
                'lodash' => ['version' => '4.17.21'],
            ],
        ]);

        $dependencies = $this->npm()->getDependencies($manifest, $lock);

        self::assertCount(1, $dependencies);
        self::assertSame('4.17.21', $dependencies[0]->lockedVersion);
    }

    public function testWithoutLockfileLockedVersionIsNull(): void
    {
        $manifest = json_encode(['dependencies' => ['lodash' => '^4.17.21']]);

        $dependencies = $this->npm()->getDependencies($manifest, null);

        self::assertCount(1, $dependencies);
        self::assertNull($dependencies[0]->lockedVersion);
    }

    private function npm(): NpmDependencyManager
    {
        return new NpmDependencyManager($this->createStub(PackageRegistryInterface::class));
    }
}
