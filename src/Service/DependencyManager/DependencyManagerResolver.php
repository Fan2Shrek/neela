<?php

declare(strict_types=1);

namespace App\Service\DependencyManager;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class DependencyManagerResolver
{
    /**
     * @param iterable<DependencyManagerInterface> $dependencyManagers
     */
    public function __construct(
        #[AutowireIterator(DependencyManagerInterface::class)]
        private readonly iterable $dependencyManagers,
    ) {
    }

    public function resolve(string $name): DependencyManagerInterface
    {
        foreach ($this->dependencyManagers as $dependencyManager) {
            if ($dependencyManager->getName() === $name) {
                return $dependencyManager;
            }
        }

        throw new \RuntimeException(\sprintf('No dependency manager definition found for "%s".', $name));
    }
}
