<?php

declare(strict_types=1);

namespace App\Enum;

enum Technology: string
{
    case SYMFONY = 'symfony';
    case LARAVEL = 'laravel';
    case REACT = 'react';
    case VUE = 'vue';

    public function getLabel(): string
    {
        return match ($this) {
            self::SYMFONY => 'Symfony',
            self::LARAVEL => 'Laravel',
            self::REACT => 'React',
            self::VUE => 'Vue',
        };
    }

    /**
     * The package whose presence in a manifest marks it as using this technology.
     *
     * @return array{0: string, 1: string} vendor, name
     */
    public function getSignalPackage(): array
    {
        return match ($this) {
            self::SYMFONY => ['symfony', 'framework-bundle'],
            self::LARAVEL => ['laravel', 'framework'],
            self::REACT => ['react', 'react'],
            self::VUE => ['vue', 'vue'],
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
            self::SYMFONY, self::LARAVEL => $this->value,
            self::REACT, self::VUE => null,
        };
    }

    /**
     * The release "cycle" (as endoflife.date names it) a given version belongs to, e.g.
     * "7.4" for Symfony's v7.4.2, "12" for Laravel's v12.1.0.
     */
    public function extractCycle(string $version): string
    {
        $segments = explode('.', ltrim($version, 'v'));

        return match ($this) {
            self::SYMFONY => implode('.', \array_slice($segments, 0, 2)),
            self::LARAVEL, self::REACT, self::VUE => $segments[0],
        };
    }
}
