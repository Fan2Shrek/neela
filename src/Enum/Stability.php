<?php

declare(strict_types=1);

namespace App\Enum;

enum Stability: string
{
    case STABLE = 'stable';
    case RC = 'rc';
    case BETA = 'beta';
    case ALPHA = 'alpha';
    case DEV = 'dev';

    /**
     * Reads the pre-release marker directly off the registry's raw version tag
     * (e.g. "2.0.0-beta1", "1.4.x-dev", "dev-master") — the same convention
     * Composer, npm and most semver-based registries use.
     */
    public static function fromVersionString(string $version): self
    {
        $version = strtolower($version);

        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return self::DEV;
        }

        if (1 === preg_match('/-(alpha|a|beta|b|rc|canary|next|experimental|insiders|nightly|preview|snapshot)(?:[.-]?\d+)?/', $version, $matches)) {
            return match ($matches[1]) {
                'alpha', 'a' => self::ALPHA,
                'beta', 'b' => self::BETA,
                'rc' => self::RC,
                // npm's other pre-release/canary channels have no Composer equivalent;
                // bucket them with "dev" since none of them are ever release candidates.
                default => self::DEV,
            };
        }

        return self::STABLE;
    }

    public function isPreRelease(): bool
    {
        return self::STABLE !== $this;
    }
}
