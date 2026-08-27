<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Project;

use App\Domain\Command\Manifest\ScanManifestDependenciesCommand;
use App\Domain\Command\Project\ImportProjectCommand;
use App\Domain\Command\Project\ImportProjectHandler;
use App\Domain\Command\Project\RescanProjectCommand;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Entity\Scan;
use App\Repository\ProjectRepository;
use App\Service\ManifestDiscovery\ManifestDiscoveryInterface;
use App\Service\VCS\GitTree;
use App\Service\VCS\VCSInterface;
use App\Service\VCS\VCSProject;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class ImportProjectHandlerTest extends TestCase
{
    public function testImportPersistsProjectAndDiscoversManifestsWithoutScanning(): void
    {
        $vcsResolver = new VCSResolver([$this->stubVcs()]);

        $manifestDiscoveryService = $this->createMock(ManifestDiscoveryInterface::class);
        $manifestDiscoveryService->expects(self::once())
            ->method('discover')
            ->with(self::isInstanceOf(Project::class))
            ->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findOneBySshLink')->willReturn(null);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $handler = new ImportProjectHandler($entityManager, $projectRepository, $vcsResolver, $manifestDiscoveryService, $bus);

        $handler(new ImportProjectCommand('git@github.com:acme/my-project.git', scanNow: false));
    }

    public function testImportOfAnAlreadyKnownSshLinkDispatchesARescanInsteadOfDuplicating(): void
    {
        $vcsResolver = new VCSResolver([$this->stubVcs()]);

        $existingProject = new Project('my-project', 'git@github.com:acme/my-project.git');
        $property = new \ReflectionProperty(Project::class, 'id');
        $property->setValue($existingProject, Uuid::v7());

        $manifestDiscoveryService = $this->createMock(ManifestDiscoveryInterface::class);
        $manifestDiscoveryService->expects(self::never())->method('discover');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findOneBySshLink')->willReturn($existingProject);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RescanProjectCommand::class))
            ->willReturnCallback(static fn (RescanProjectCommand $command): Envelope => new Envelope($command));

        $handler = new ImportProjectHandler($entityManager, $projectRepository, $vcsResolver, $manifestDiscoveryService, $bus);

        $handler(new ImportProjectCommand('git@github.com:acme/my-project.git', scanNow: true));
    }

    public function testImportCreatesOneScanPerDiscoveredManifestWhenScanNowIsTrue(): void
    {
        $vcsResolver = new VCSResolver([$this->stubVcs()]);

        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $npm = new DependencyManager('npm');
        $manifests = [
            new Manifest($project, $composer, 'backend/composer.json', 'backend/composer.lock'),
            new Manifest($project, $npm, 'frontend/package.json', null),
        ];

        $manifestDiscoveryService = $this->createStub(ManifestDiscoveryInterface::class);
        $manifestDiscoveryService->method('discover')->willReturn($manifests);

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findOneBySshLink')->willReturn(null);

        $nextScanId = 1;
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$nextScanId): void {
            if ($entity instanceof Scan) {
                // Simulate Doctrine assigning the auto-generated id on persist.
                $property = new \ReflectionProperty(Scan::class, 'id');
                $property->setValue($entity, $nextScanId++);
            }
        });

        $dispatchedScanIds = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(ScanManifestDependenciesCommand::class))
            ->willReturnCallback(function (ScanManifestDependenciesCommand $command) use (&$dispatchedScanIds): Envelope {
                $dispatchedScanIds[] = $command->scanId;

                return new Envelope($command);
            });

        $handler = new ImportProjectHandler($entityManager, $projectRepository, $vcsResolver, $manifestDiscoveryService, $bus);

        $handler(new ImportProjectCommand('git@github.com:acme/my-project.git', scanNow: true));

        self::assertSame([1, 2], $dispatchedScanIds);
    }

    private function stubVcs(): VCSInterface
    {
        return new class implements VCSInterface {
            public function supports(string $sshLink): bool
            {
                return true;
            }

            public function getVCSInfo(string $projectPath): VCSProject
            {
                return new VCSProject(name: 'my-project', owner: 'acme');
            }

            public function getTree(string $sshLink): GitTree
            {
                return new GitTree([], false);
            }

            public function getFileContent(string $sshLink, string $path): ?string
            {
                return null;
            }
        };
    }
}
