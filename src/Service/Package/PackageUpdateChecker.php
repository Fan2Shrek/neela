<?php

declare(strict_types=1);

namespace App\Service\Package;

use Composer\Semver\Semver;

final class PackageUpdateChecker
{
    /**
     * Highest known version that still satisfies a dependency's own constraint (e.g.
     * "7.3.*"), i.e. what "composer update" would actually install — not necessarily the
     * package's overall latest release, which may sit on a major line the constraint
     * doesn't allow.
     *
     * @param string[] $availableVersions
     */
    public function findLatestSatisfying(array $availableVersions, string $constraint): ?string
    {
        try {
            $satisfying = Semver::satisfiedBy($availableVersions, $constraint);
        } catch (\UnexpectedValueException) {
            // Not every ecosystem's constraint syntax is one Composer's parser
            // understands (npm git/workspace/tag references, "latest", local paths...).
            return null;
        }

        if ([] === $satisfying) {
            return null;
        }

        return Semver::rsort($satisfying)[0];
    }

    /**
     * Highest known version overall, ignoring any constraint.
     *
     * @param string[] $availableVersions
     */
    public function findLatest(array $availableVersions): ?string
    {
        if ([] === $availableVersions) {
            return null;
        }

        return Semver::rsort($availableVersions)[0];
    }
}
