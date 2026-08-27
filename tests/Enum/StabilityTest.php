<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Stability;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StabilityTest extends TestCase
{
    #[DataProvider('versionProvider')]
    public function testFromVersionString(string $version, Stability $expected): void
    {
        self::assertSame($expected, Stability::fromVersionString($version));
    }

    /**
     * @return iterable<string, array{string, Stability}>
     */
    public static function versionProvider(): iterable
    {
        yield 'stable release' => ['v6.4.19', Stability::STABLE];
        yield 'stable release without v prefix' => ['1.2.3', Stability::STABLE];
        yield 'alpha' => ['2.0.0-alpha1', Stability::ALPHA];
        yield 'alpha shorthand' => ['2.0.0-a1', Stability::ALPHA];
        yield 'beta' => ['2.0.0-beta2', Stability::BETA];
        yield 'beta shorthand' => ['2.0.0-b2', Stability::BETA];
        yield 'beta with dot separator' => ['2.0.0-beta.2', Stability::BETA];
        yield 'RC' => ['2.0.0-RC1', Stability::RC];
        yield 'branch alias dev' => ['1.4.x-dev', Stability::DEV];
        yield 'dev branch prefix' => ['dev-master', Stability::DEV];
        yield 'npm canary' => ['19.3.0-canary-a1124489-20260826', Stability::DEV];
        yield 'npm next' => ['13.0.0-next.5', Stability::DEV];
        yield 'npm experimental' => ['0.0.0-experimental-a1124489-20260826', Stability::DEV];
        yield 'npm nightly' => ['1.0.0-nightly.20260101', Stability::DEV];
        yield 'npm insiders' => ['1.0.0-insiders.1', Stability::DEV];
        yield 'npm preview' => ['1.0.0-preview.1', Stability::DEV];
    }

    public function testIsPreRelease(): void
    {
        self::assertFalse(Stability::STABLE->isPreRelease());
        self::assertTrue(Stability::RC->isPreRelease());
        self::assertTrue(Stability::BETA->isPreRelease());
        self::assertTrue(Stability::ALPHA->isPreRelease());
        self::assertTrue(Stability::DEV->isPreRelease());
    }
}
