<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\WarmupTechnologySupportCommand;
use App\Domain\Command\Technology\RefreshTechnologySupportCommand;
use App\Repository\TechnologyReleaseCycleRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class WarmupTechnologySupportCommandTest extends TestCase
{
    public function testDispatchesARefreshWhenNoDataExistsYet(): void
    {
        $repository = $this->createStub(TechnologyReleaseCycleRepository::class);
        $repository->method('count')->willReturn(0);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RefreshTechnologySupportCommand::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $tester = new CommandTester(new WarmupTechnologySupportCommand($repository, $bus));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testSkipsWhenDataAlreadyExists(): void
    {
        $repository = $this->createStub(TechnologyReleaseCycleRepository::class);
        $repository->method('count')->willReturn(42);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $tester = new CommandTester(new WarmupTechnologySupportCommand($repository, $bus));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('skipping', $tester->getDisplay());
    }
}
