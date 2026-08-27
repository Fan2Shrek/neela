<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Project;

use App\Domain\Command\Manifest\ScanManifestDependenciesCommand;
use App\Domain\Command\Project\RescanProjectCommand;
use App\Domain\Command\Project\RescanProjectHandler;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Entity\Scan;
use App\Repository\ManifestRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class RescanProjectHandlerTest extends TestCase
{
    public function testMissingProjectThrows(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('find')->willReturn(null);

        $handler = new RescanProjectHandler(
            $projectRepository,
            $this->createStub(ManifestRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(\RuntimeException::class);

        $handler(new RescanProjectCommand((string) Uuid::v7()));
    }

    public function testCreatesOneScanPerManifestAndDispatchesEachOne(): void
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());

        $composer = new DependencyManager('Composer');
        $npm = new DependencyManager('npm');
        $manifests = [
            new Manifest($project, $composer, 'backend/composer.json', 'backend/composer.lock'),
            new Manifest($project, $npm, 'frontend/package.json', null),
        ];

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('find')->willReturn($project);

        $manifestRepository = $this->createStub(ManifestRepository::class);
        $manifestRepository->method('findBy')->willReturn($manifests);

        $nextScanId = 1;
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$nextScanId, &$persisted): void {
            $persisted[] = $entity;

            if ($entity instanceof Scan) {
                (new \ReflectionProperty(Scan::class, 'id'))->setValue($entity, $nextScanId++);
            }
        });
        $entityManager->expects(self::once())->method('flush');

        $dispatchedScanIds = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(ScanManifestDependenciesCommand::class))
            ->willReturnCallback(function (ScanManifestDependenciesCommand $command) use (&$dispatchedScanIds): Envelope {
                $dispatchedScanIds[] = $command->scanId;

                return new Envelope($command);
            });

        $handler = new RescanProjectHandler($projectRepository, $manifestRepository, $entityManager, $bus);

        $handler(new RescanProjectCommand((string) $project->getId()));

        self::assertCount(2, $persisted);
        self::assertSame([1, 2], $dispatchedScanIds);
    }
}
