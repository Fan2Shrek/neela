<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Technology;

use App\Domain\Command\Technology\RefreshTechnologySupportCommand;
use App\Domain\Command\Technology\RefreshTechnologySupportHandler;
use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use App\Repository\TechnologyReleaseCycleRepository;
use App\Service\EndOfLife\EndOfLifeClientInterface;
use App\Service\EndOfLife\EndOfLifeCycleData;
use App\Service\EndOfLife\Exception\EndOfLifeClientException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RefreshTechnologySupportHandlerTest extends TestCase
{
    public function testCreatesNewCyclesAndUpdatesExistingOnes(): void
    {
        $existingCycle = new TechnologyReleaseCycle(Technology::SYMFONY, '7.3', '7.3.10', false);

        $repository = $this->createStub(TechnologyReleaseCycleRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => '7.3' === $criteria['cycle'] && Technology::SYMFONY === $criteria['technology']
                ? $existingCycle
                : null,
        );

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $endOfLifeClient = $this->createStub(EndOfLifeClientInterface::class);
        $endOfLifeClient->method('getCycles')->willReturnCallback(
            static fn (string $slug) => match ($slug) {
                'symfony' => [
                    new EndOfLifeCycleData('7.4', '7.4.17', true, new \DateTimeImmutable('2025-11-27'), null),
                    new EndOfLifeCycleData('7.3', '7.3.11', false, new \DateTimeImmutable('2025-05-29'), new \DateTimeImmutable('2026-01-31')),
                ],
                'laravel' => [],
                default => [],
            },
        );

        $handler = new RefreshTechnologySupportHandler(
            $endOfLifeClient,
            $repository,
            $entityManager,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new RefreshTechnologySupportCommand());

        self::assertCount(1, $persisted);
        self::assertInstanceOf(TechnologyReleaseCycle::class, $persisted[0]);
        self::assertSame('7.4', $persisted[0]->getCycle());
        self::assertSame('7.4.17', $persisted[0]->getLatestVersion());

        self::assertSame('7.3.11', $existingCycle->getLatestVersion());
        self::assertSame('2026-01-31', $existingCycle->getEolDate()->format('Y-m-d'));
    }

    public function testOneProductFailingDoesNotBlockTheOthers(): void
    {
        $repository = $this->createStub(TechnologyReleaseCycleRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $endOfLifeClient = $this->createStub(EndOfLifeClientInterface::class);
        $endOfLifeClient->method('getCycles')->willReturnCallback(
            static function (string $slug) {
                if ('symfony' === $slug) {
                    throw new EndOfLifeClientException('boom');
                }

                return [new EndOfLifeCycleData('12', '12.68.0', false, new \DateTimeImmutable('2025-02-24'), null)];
            },
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $handler = new RefreshTechnologySupportHandler($endOfLifeClient, $repository, $entityManager, $logger);

        $handler(new RefreshTechnologySupportCommand());

        // Every technology except Symfony (which fails above) has an EOL product today
        // (Laravel, PHP) and the stub returns the same cycle for either of them.
        self::assertCount(2, $persisted);
        self::assertSame('12', $persisted[0]->getCycle());
        self::assertSame('12', $persisted[1]->getCycle());
    }
}
