<?php

declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Shared cache tag names: producers (cached computations) tag their entries with
 * whichever kind(s) of underlying data they depend on; consumers (the handlers that
 * change that data) invalidate by tag instead of needing to know every cache key that
 * happens to depend on it.
 */
final class CacheTags
{
    /** A project's manifests, dependencies or locked versions changed (a scan ran). */
    public const SCAN_DATA = 'scan-data';

    /** New package release data was fetched from a registry. */
    public const PACKAGE_VERSIONS = 'package-versions';

    private function __construct()
    {
    }
}
