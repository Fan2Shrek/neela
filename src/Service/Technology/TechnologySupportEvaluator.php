<?php

declare(strict_types=1);

namespace App\Service\Technology;

use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use App\Enum\TechnologySupportStatus;
use App\Repository\TechnologyReleaseCycleRepository;

final class TechnologySupportEvaluator
{
    public function __construct(
        private readonly TechnologyReleaseCycleRepository $technologyReleaseCycleRepository,
    ) {
    }

    /**
     * UNKNOWN when the technology has no tracked support cycles yet (no endoflife.date
     * product, or its data hasn't been fetched) or the version's cycle isn't one of them.
     */
    public function evaluate(Technology $technology, string $version, ?\DateTimeImmutable $now = null): TechnologySupportStatus
    {
        $cycles = $this->technologyReleaseCycleRepository->findByTechnology($technology);

        if ([] === $cycles) {
            return TechnologySupportStatus::UNKNOWN;
        }

        $cycleId = $technology->extractCycle($version);
        $matching = null;
        $latestCycle = null;

        foreach ($cycles as $cycle) {
            if ($cycle->getCycle() === $cycleId) {
                $matching = $cycle;
            }

            if (null === $latestCycle || $this->isNewer($cycle, $latestCycle)) {
                $latestCycle = $cycle;
            }
        }

        if (null === $matching) {
            return TechnologySupportStatus::UNKNOWN;
        }

        $now ??= new \DateTimeImmutable();

        return match (true) {
            null !== $matching->getEolDate() && $matching->getEolDate() < $now => TechnologySupportStatus::END_OF_LIFE,
            $matching === $latestCycle => TechnologySupportStatus::UP_TO_DATE,
            $matching->isLts() => TechnologySupportStatus::LTS,
            default => TechnologySupportStatus::OUTDATED,
        };
    }

    private function isNewer(TechnologyReleaseCycle $candidate, TechnologyReleaseCycle $current): bool
    {
        return ($candidate->getReleaseDate() ?? new \DateTimeImmutable('@0')) > ($current->getReleaseDate() ?? new \DateTimeImmutable('@0'));
    }
}
