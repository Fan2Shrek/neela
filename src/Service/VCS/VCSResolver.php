<?php

declare(strict_types=1);

namespace App\Service\VCS;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class VCSResolver
{
    /**
     * @param iterable<VCSInterface> $vcs
     */
    public function __construct(
        #[AutowireIterator(VCSInterface::class)]
        private iterable $vcs,
    ) {
    }

    public function resolve(string $sshLink): VCSInterface
    {
        foreach ($this->vcs as $vcsService) {
            if ($vcsService->supports($sshLink)) {
                return $vcsService;
            }
        }

        throw new \Exception('No VCS service found for the given SSH link');
    }
}
