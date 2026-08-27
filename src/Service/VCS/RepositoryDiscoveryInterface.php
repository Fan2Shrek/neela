<?php

declare(strict_types=1);

namespace App\Service\VCS;

/**
 * Listing every repository of an account/organization is inherently provider-specific
 * (GitHub's API shape has nothing in common with GitLab's), unlike VCSInterface's
 * per-repository operations — so this doesn't try to be a generic, resolver-picked
 * abstraction the way VCSInterface is.
 */
interface RepositoryDiscoveryInterface
{
    /**
     * @return DiscoveredRepository[]
     */
    public function discoverRepositories(string $account): array;
}
