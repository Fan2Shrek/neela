<?php

declare(strict_types=1);

namespace App\Enum;

enum Technology: string
{
    case SYMFONY = 'symfony';
    case LARAVEL = 'laravel';
    case REACT = 'react';
    case VUE = 'vue';
    case PHP = 'php';

    public function getLabel(): string
    {
        return match ($this) {
            self::SYMFONY => 'Symfony',
            self::LARAVEL => 'Laravel',
            self::REACT => 'React',
            self::VUE => 'Vue',
            self::PHP => 'PHP',
        };
    }

    /**
     * The package whose presence in a manifest marks it as using this technology. Null
     * for runtimes like PHP, which aren't detected from a dependency but from a manifest's
     * own platform requirement (Composer's require.php) — see ManifestScanner instead.
     *
     * @return array{0: string, 1: string}|null vendor, name
     */
    public function getSignalPackage(): ?array
    {
        return match ($this) {
            self::SYMFONY => ['symfony', 'framework-bundle'],
            self::LARAVEL => ['laravel', 'framework'],
            self::REACT => ['react', 'react'],
            self::VUE => ['vue', 'vue'],
            self::PHP => null,
        };
    }

    /**
     * endoflife.date product slug (see https://endoflife.date/api). Null when this
     * technology has no support/LTS tracking yet — it will still be detected and
     * displayed, just without a freshness status.
     */
    public function getEndOfLifeProductSlug(): ?string
    {
        return match ($this) {
            self::SYMFONY, self::LARAVEL, self::PHP => $this->value,
            self::REACT, self::VUE => null,
        };
    }

    /**
     * The release "cycle" (as endoflife.date names it) a given version belongs to, e.g.
     * "7.4" for Symfony's v7.4.2, "12" for Laravel's v12.1.0.
     *
     * PHP's "version" is actually a Composer constraint (e.g. "^8.3", ">=8.1"), not a
     * resolved version — Composer never locks a concrete PHP version. Strip the leading
     * operator and keep only the first alternative before parsing.
     */
    public function extractCycle(string $version): string
    {
        $version = self::PHP === $this
            ? preg_replace('/^\D+/', '', trim(explode(' ', $version)[0]))
            : ltrim($version, 'v');

        $segments = explode('.', $version);

        return match ($this) {
            self::SYMFONY, self::PHP => implode('.', \array_slice($segments, 0, 2)),
            self::LARAVEL, self::REACT, self::VUE => $segments[0],
        };
    }
}
