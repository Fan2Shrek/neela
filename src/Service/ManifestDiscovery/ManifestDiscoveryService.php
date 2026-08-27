<?php

declare(strict_types=1);

namespace App\Service\ManifestDiscovery;

use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Repository\DependencyManagerRepository;
use App\Repository\ManifestRepository;
use App\Service\DependencyManager\DependencyManagerInterface;
use App\Service\ManifestDiscovery\Exception\TruncatedTreeException;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;

final class ManifestDiscoveryService
{
    public function __construct(
        private readonly VCSResolver $vcsResolver,
        private readonly ManifestMatcher $manifestMatcher,
        private readonly DependencyManagerRepository $dependencyManagerRepository,
        private readonly ManifestRepository $manifestRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Manifest[]
     */
    public function discover(Project $project): array
    {
        $vcs = $this->vcsResolver->resolve($project->getSshLink());
        $tree = $vcs->getTree($project->getSshLink());

        if ($tree->truncated) {
            throw new TruncatedTreeException($project);
        }

        /** @var array<string, DependencyManager> $dependencyManagerCache */
        $dependencyManagerCache = [];
        $manifests = [];

        foreach ($this->manifestMatcher->match($tree) as $discovered) {
            $definition = $discovered->dependencyManager;
            $dependencyManagerCache[$definition->getName()] ??= $this->resolveDependencyManager($definition);

            $manifest = $this->manifestRepository->findOneBy(['project' => $project, 'path' => $discovered->path]);

            if (null === $manifest) {
                $manifest = new Manifest($project, $dependencyManagerCache[$definition->getName()], $discovered->path, $discovered->lockPath);
            } else {
                $manifest->setLockPath($discovered->lockPath);
            }

            $this->entityManager->persist($manifest);
            $manifests[] = $manifest;
        }

        $this->entityManager->flush();

        return $manifests;
    }

    private function resolveDependencyManager(DependencyManagerInterface $definition): DependencyManager
    {
        $dependencyManager = $this->dependencyManagerRepository->findOneBy(['name' => $definition->getName()]);

        if (null === $dependencyManager) {
            $dependencyManager = new DependencyManager($definition->getName());
            $this->entityManager->persist($dependencyManager);
        }

        return $dependencyManager;
    }
}
