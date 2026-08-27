<?php

declare(strict_types=1);

namespace App\Tests\Service\ManifestDiscovery;

use App\Entity\DependencyManager;
use App\Entity\Project;
use App\Repository\DependencyManagerRepository;
use App\Repository\ManifestRepository;
use App\Service\DependencyManager\ComposerDependencyManager;
use App\Service\DependencyManager\NpmDependencyManager;
use App\Service\ManifestDiscovery\Exception\TruncatedTreeException;
use App\Service\ManifestDiscovery\ManifestDiscoveryService;
use App\Service\ManifestDiscovery\ManifestMatcher;
use App\Service\VCS\GitTree;
use App\Service\VCS\GitTreeEntry;
use App\Service\VCS\VCSInterface;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ManifestDiscoveryServiceTest extends TestCase
{
    public function testTruncatedTreeIsRejectedExplicitly(): void
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');

        $vcsResolver = new VCSResolver([$this->stubVcs(new GitTree([], true))]);

        $manifestRepository = $this->createMock(ManifestRepository::class);
        $manifestRepository->expects(self::never())->method('findOneBy');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $service = new ManifestDiscoveryService(
            $vcsResolver,
            new ManifestMatcher([new ComposerDependencyManager()]),
            $this->createStub(DependencyManagerRepository::class),
            $manifestRepository,
            $entityManager,
        );

        $this->expectException(TruncatedTreeException::class);

        $service->discover($project);
    }

    public function testDiscoverPersistsManifestsAndReusesExistingDependencyManagers(): void
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');

        $tree = new GitTree([
            new GitTreeEntry('app/back/composer.json', 'blob'),
            new GitTreeEntry('app/back/composer.lock', 'blob'),
            new GitTreeEntry('app/front/package.json', 'blob'),
        ], false);

        $vcsResolver = new VCSResolver([$this->stubVcs($tree)]);

        $composerManager = new DependencyManager('Composer');

        $manifestRepository = $this->createStub(ManifestRepository::class);
        $manifestRepository->method('findOneBy')->willReturn(null);

        $dependencyManagerRepository = $this->createStub(DependencyManagerRepository::class);
        $dependencyManagerRepository->method('findOneBy')
            ->willReturnCallback(static fn (array $criteria) => 'Composer' === $criteria['name'] ? $composerManager : null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new ManifestDiscoveryService(
            $vcsResolver,
            new ManifestMatcher([new ComposerDependencyManager(), new NpmDependencyManager()]),
            $dependencyManagerRepository,
            $manifestRepository,
            $entityManager,
        );

        $manifests = $service->discover($project);

        self::assertCount(2, $manifests);

        $byPath = [];
        foreach ($manifests as $manifest) {
            $byPath[$manifest->getPath()] = $manifest;
        }

        self::assertSame('app/back/composer.lock', $byPath['app/back/composer.json']->getLockPath());
        self::assertSame($composerManager, $byPath['app/back/composer.json']->getDependencyManager());
        self::assertNull($byPath['app/front/package.json']->getLockPath());
        self::assertSame('npm', $byPath['app/front/package.json']->getDependencyManager()->getName());
    }

    private function stubVcs(GitTree $tree): VCSInterface
    {
        return new class($tree) implements VCSInterface {
            public function __construct(private readonly GitTree $tree)
            {
            }

            public function supports(string $sshLink): bool
            {
                return true;
            }

            public function getVCSInfo(string $projectPath): array
            {
                return [];
            }

            public function getTree(string $sshLink): GitTree
            {
                return $this->tree;
            }
        };
    }
}
