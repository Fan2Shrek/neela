<?php

declare(strict_types=1);

namespace App\Domain\Command\Technology;

use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use App\Repository\TechnologyReleaseCycleRepository;
use App\Service\EndOfLife\EndOfLifeClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefreshTechnologySupportHandler
{
    public function __construct(
        private readonly EndOfLifeClientInterface $endOfLifeClient,
        private readonly TechnologyReleaseCycleRepository $technologyReleaseCycleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshTechnologySupportCommand $command): void
    {
        foreach (Technology::cases() as $technology) {
            $productSlug = $technology->getEndOfLifeProductSlug();

            if (null === $productSlug) {
                continue;
            }

            try {
                $cycles = $this->endOfLifeClient->getCycles($productSlug);
            } catch (\Throwable $exception) {
                // One product's data being unavailable must not block refreshing the others.
                $this->logger->warning('Unable to fetch support data for "{technology}": {message}', [
                    'technology' => $technology->value,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);

                continue;
            }

            foreach ($cycles as $cycleData) {
                $entity = $this->technologyReleaseCycleRepository->findOneBy([
                    'technology' => $technology,
                    'cycle' => $cycleData->cycle,
                ]);

                if (null === $entity) {
                    $entity = new TechnologyReleaseCycle($technology, $cycleData->cycle, $cycleData->latestVersion, $cycleData->isLts);
                    $this->entityManager->persist($entity);
                } else {
                    $entity->setLatestVersion($cycleData->latestVersion);
                    $entity->setLts($cycleData->isLts);
                }

                $entity->setReleaseDate($cycleData->releaseDate);
                $entity->setEolDate($cycleData->eolDate);
            }
        }

        $this->entityManager->flush();
    }
}
