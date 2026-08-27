<?php

declare(strict_types=1);

namespace App\Tests\Service\Technology;

use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use App\Enum\TechnologySupportStatus;
use App\Repository\TechnologyReleaseCycleRepository;
use App\Service\Technology\TechnologySupportEvaluator;
use PHPUnit\Framework\TestCase;

final class TechnologySupportEvaluatorTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-08-27');
    }

    public function testUnknownWhenNoCyclesAreTracked(): void
    {
        $evaluator = $this->evaluatorWithCycles([]);

        self::assertSame(
            TechnologySupportStatus::UNKNOWN,
            $evaluator->evaluate(Technology::SYMFONY, 'v7.3.3', $this->now),
        );
    }

    public function testUnknownWhenTheVersionsCycleIsNotTracked(): void
    {
        $evaluator = $this->evaluatorWithCycles([
            $this->cycle('7.4', '7.4.17', true, '2025-11-27', null),
        ]);

        self::assertSame(
            TechnologySupportStatus::UNKNOWN,
            $evaluator->evaluate(Technology::SYMFONY, 'v6.0.0', $this->now),
        );
    }

    public function testUpToDateOnTheLatestCycle(): void
    {
        $evaluator = $this->evaluatorWithCycles([
            $this->cycle('6.4', '6.4.44', true, '2023-11-29', null),
            $this->cycle('7.4', '7.4.17', true, '2025-11-27', null),
        ]);

        self::assertSame(
            TechnologySupportStatus::UP_TO_DATE,
            $evaluator->evaluate(Technology::SYMFONY, 'v7.4.2', $this->now),
        );
    }

    public function testLtsWhenBehindButOnADesignatedLtsCycle(): void
    {
        $evaluator = $this->evaluatorWithCycles([
            $this->cycle('6.4', '6.4.44', true, '2023-11-29', '2027-11-30'),
            $this->cycle('7.4', '7.4.17', true, '2025-11-27', null),
        ]);

        self::assertSame(
            TechnologySupportStatus::LTS,
            $evaluator->evaluate(Technology::SYMFONY, 'v6.4.10', $this->now),
        );
    }

    public function testOutdatedWhenBehindAndNotLts(): void
    {
        $evaluator = $this->evaluatorWithCycles([
            $this->cycle('7.3', '7.3.11', false, '2025-05-29', '2027-01-31'),
            $this->cycle('7.4', '7.4.17', true, '2025-11-27', null),
        ]);

        self::assertSame(
            TechnologySupportStatus::OUTDATED,
            $evaluator->evaluate(Technology::SYMFONY, 'v7.3.3', $this->now),
        );
    }

    public function testEndOfLifeTakesPrecedenceEvenOverAnLtsCycle(): void
    {
        $evaluator = $this->evaluatorWithCycles([
            $this->cycle('5.4', '5.4.50', true, '2021-11-30', '2025-11-26'),
            $this->cycle('7.4', '7.4.17', true, '2025-11-27', null),
        ]);

        self::assertSame(
            TechnologySupportStatus::END_OF_LIFE,
            $evaluator->evaluate(Technology::SYMFONY, 'v5.4.40', $this->now),
        );
    }

    /**
     * @param TechnologyReleaseCycle[] $cycles
     */
    private function evaluatorWithCycles(array $cycles): TechnologySupportEvaluator
    {
        $repository = $this->createStub(TechnologyReleaseCycleRepository::class);
        $repository->method('findByTechnology')->willReturn($cycles);

        return new TechnologySupportEvaluator($repository);
    }

    private function cycle(string $cycle, string $latestVersion, bool $lts, string $releaseDate, ?string $eolDate): TechnologyReleaseCycle
    {
        $entity = new TechnologyReleaseCycle(Technology::SYMFONY, $cycle, $latestVersion, $lts);
        $entity->setReleaseDate(new \DateTimeImmutable($releaseDate));
        $entity->setEolDate(null !== $eolDate ? new \DateTimeImmutable($eolDate) : null);

        return $entity;
    }
}
