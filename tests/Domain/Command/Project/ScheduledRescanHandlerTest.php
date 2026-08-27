<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Project;

use App\Domain\Command\Project\RescanProjectCommand;
use App\Domain\Command\Project\ScheduledRescanCommand;
use App\Domain\Command\Project\ScheduledRescanHandler;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class ScheduledRescanHandlerTest extends TestCase
{
    public function testDispatchesOneRescanPerKnownProject(): void
    {
        $projects = [
            $this->projectWithId('git@github.com:acme/one.git'),
            $this->projectWithId('git@github.com:acme/two.git'),
        ];

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findAll')->willReturn($projects);

        $dispatchedProjectIds = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(RescanProjectCommand::class))
            ->willReturnCallback(function (RescanProjectCommand $command) use (&$dispatchedProjectIds): Envelope {
                $dispatchedProjectIds[] = $command->projectId;

                return new Envelope($command);
            });

        $handler = new ScheduledRescanHandler($projectRepository, $bus);

        $handler(new ScheduledRescanCommand());

        self::assertSame(
            array_map(static fn (Project $p): string => (string) $p->getId(), $projects),
            $dispatchedProjectIds,
        );
    }

    private function projectWithId(string $sshLink): Project
    {
        $project = new Project('name', $sshLink);
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, Uuid::v7());

        return $project;
    }
}
