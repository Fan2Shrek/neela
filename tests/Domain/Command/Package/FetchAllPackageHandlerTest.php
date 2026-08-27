<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Package;

use App\Domain\Command\Package\FetchAllPackageCommand;
use App\Domain\Command\Package\FetchAllPackageHandler;
use App\Domain\Command\Package\GetPackageVersionCommand;
use App\Repository\PackageRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class FetchAllPackageHandlerTest extends TestCase
{
    public function testDispatchesOneCommandPerPackageId(): void
    {
        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('iterateAllIds')->willReturn([1, 2, 3]);

        $dispatchedIds = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (GetPackageVersionCommand $command) use (&$dispatchedIds): Envelope {
                $dispatchedIds[] = $command->packageId;

                return new Envelope($command);
            });

        $handler = new FetchAllPackageHandler($packageRepository, $bus, $this->createStub(LoggerInterface::class));

        $handler(new FetchAllPackageCommand());

        self::assertSame([1, 2, 3], $dispatchedIds);
    }

    public function testAFailingPackageDoesNotStopTheRestOfTheBatch(): void
    {
        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('iterateAllIds')->willReturn([1, 2, 3]);

        $dispatchedIds = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (GetPackageVersionCommand $command) use (&$dispatchedIds): Envelope {
                $dispatchedIds[] = $command->packageId;

                if (2 === $command->packageId) {
                    throw new HandlerFailedException(new Envelope($command), [new \RuntimeException('Packagist is down.')]);
                }

                return new Envelope($command);
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $handler = new FetchAllPackageHandler($packageRepository, $bus, $logger);

        $handler(new FetchAllPackageCommand());

        self::assertSame([1, 2, 3], $dispatchedIds);
    }
}
