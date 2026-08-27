<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Technology;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TechnologyTest extends TestCase
{
    #[DataProvider('cycleProvider')]
    public function testExtractCycle(Technology $technology, string $version, string $expectedCycle): void
    {
        self::assertSame($expectedCycle, $technology->extractCycle($version));
    }

    /**
     * @return iterable<string, array{Technology, string, string}>
     */
    public static function cycleProvider(): iterable
    {
        yield 'symfony minor.patch' => [Technology::SYMFONY, 'v7.4.2', '7.4'];
        yield 'symfony without v prefix' => [Technology::SYMFONY, '6.4.44', '6.4'];
        yield 'laravel major only' => [Technology::LARAVEL, 'v12.1.0', '12'];
        yield 'react major only' => [Technology::REACT, '18.2.0', '18'];
    }

    public function testSignalPackages(): void
    {
        self::assertSame(['symfony', 'framework-bundle'], Technology::SYMFONY->getSignalPackage());
        self::assertSame(['laravel', 'framework'], Technology::LARAVEL->getSignalPackage());
        self::assertSame(['react', 'react'], Technology::REACT->getSignalPackage());
        self::assertSame(['vue', 'vue'], Technology::VUE->getSignalPackage());
    }

    public function testOnlyComposerTechnologiesHaveAnEndOfLifeProduct(): void
    {
        self::assertSame('symfony', Technology::SYMFONY->getEndOfLifeProductSlug());
        self::assertSame('laravel', Technology::LARAVEL->getEndOfLifeProductSlug());
        self::assertNull(Technology::REACT->getEndOfLifeProductSlug());
        self::assertNull(Technology::VUE->getEndOfLifeProductSlug());
    }
}
