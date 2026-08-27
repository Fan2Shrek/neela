<?php

namespace App\Repository;

use App\Entity\Dependency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dependency>
 */
class DependencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dependency::class);
    }

    /**
     * @return array<int, array{dependencyCount: int, projectCount: int}>
     */
    public function countUsageByPackage(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.package) AS packageId', 'COUNT(d.id) AS dependencyCount', 'COUNT(DISTINCT m.project) AS projectCount')
            ->join('d.manifest', 'm')
            ->groupBy('d.package')
            ->getQuery()
            ->getArrayResult();

        $usage = [];
        foreach ($rows as $row) {
            $usage[(int) $row['packageId']] = [
                'dependencyCount' => (int) $row['dependencyCount'],
                'projectCount' => (int) $row['projectCount'],
            ];
        }

        return $usage;
    }
}
