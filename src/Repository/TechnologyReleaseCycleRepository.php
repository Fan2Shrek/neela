<?php

namespace App\Repository;

use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechnologyReleaseCycle>
 */
class TechnologyReleaseCycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechnologyReleaseCycle::class);
    }

    /**
     * @return TechnologyReleaseCycle[]
     */
    public function findByTechnology(Technology $technology): array
    {
        return $this->findBy(['technology' => $technology]);
    }
}
