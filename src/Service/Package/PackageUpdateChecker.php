<?php

declare(strict_types=1);

namespace App\Service\Package;

use Composer\Semver\Comparator;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

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
        $versionParser = new VersionParser();

        try {
            // Semver::satisfiedBy() re-parses the constraint string on every single
            // version it checks; parsing it once here matters a lot for packages with
            // hundreds of releases (e.g. symfony/framework-bundle) checked across many
            // projects.
            $parsedConstraint = $versionParser->parseConstraints($constraint);

            $satisfying = [];
            foreach ($availableVersions as $version) {
                if ($parsedConstraint->matches(new Constraint('==', $versionParser->normalize($version)))) {
                    $satisfying[] = $version;
                }
            }
        } catch (\UnexpectedValueException) {
            // Not every ecosystem's constraint syntax is one Composer's parser
            // understands (npm git/workspace/tag references, "latest", local paths...).
            return null;
        }

        if ([] === $satisfying) {
            return null;
        }

        // A full Semver::rsort() is O(k log k) comparisons, each constructing its own
        // Constraint objects; a single linear max-scan needs only k-1.
        $latest = array_shift($satisfying);
        foreach ($satisfying as $version) {
            if (Comparator::greaterThan($version, $latest)) {
                $latest = $version;
            }
        }

        return $latest;
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
