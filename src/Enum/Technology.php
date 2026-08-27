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
    case NODE = 'node';

    public function getLabel(): string
    {
        return match ($this) {
            self::SYMFONY => 'Symfony',
            self::LARAVEL => 'Laravel',
            self::REACT => 'React',
            self::VUE => 'Vue',
            self::PHP => 'PHP',
            self::NODE => 'Node.js',
        };
    }

    /**
     * The package whose presence in a manifest marks it as using this technology. Null
     * for runtimes like PHP and Node, which aren't detected from a dependency but from a
     * manifest's own declared constraint (Composer's require.php, npm's engines.node) —
     * see ManifestScanner instead.
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
            self::PHP, self::NODE => null,
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
            self::SYMFONY => 'symfony',
            self::LARAVEL => 'laravel',
            self::PHP => 'php',
            self::NODE => 'nodejs',
            self::REACT, self::VUE => null,
        };
    }

    /**
     * Runtimes (PHP, Node) store the raw declared constraint (e.g. "^8.3", ">=20") rather
     * than a resolved version — neither Composer nor npm ever lock a concrete runtime
     * version. Their cycle needs the leading operator stripped and only the first
     * alternative of a compound constraint kept before parsing.
     */
    public function isRuntimeConstraint(): bool
    {
        return match ($this) {
            self::PHP, self::NODE => true,
            self::SYMFONY, self::LARAVEL, self::REACT, self::VUE => false,
        };
    }

    /**
     * The release "cycle" (as endoflife.date names it) a given version belongs to, e.g.
     * "7.4" for Symfony's v7.4.2, "12" for Laravel's v12.1.0, "20" for Node's "^20.11".
     */
    public function extractCycle(string $version): string
    {
        $version = $this->isRuntimeConstraint()
            ? preg_replace('/^\D+/', '', trim(explode(' ', $version)[0]))
            : ltrim($version, 'v');

        $segments = explode('.', $version);

        return match ($this) {
            self::SYMFONY, self::PHP => implode('.', \array_slice($segments, 0, 2)),
            self::LARAVEL, self::REACT, self::VUE, self::NODE => $segments[0],
        };
    }
}
