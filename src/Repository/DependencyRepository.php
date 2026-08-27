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

    /**
     * @return array<int, int> manifest id => dependency count
     */
    public function countByManifest(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.manifest) AS manifestId', 'COUNT(d.id) AS dependencyCount')
            ->groupBy('d.manifest')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['manifestId']] = (int) $row['dependencyCount'];
        }

        return $counts;
    }

    /**
     * @return array<int, int> vendor id => distinct project count
     */
    public function countDistinctProjectsByVendor(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(pkg.vendor) AS vendorId', 'COUNT(DISTINCT m.project) AS projectCount')
            ->join('d.package', 'pkg')
            ->join('d.manifest', 'm')
            ->groupBy('pkg.vendor')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['vendorId']] = (int) $row['projectCount'];
        }

        return $counts;
    }

    /**
     * @return Dependency[]
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('m', 'p', 'pkg', 'v')
            ->join('d.manifest', 'm')
            ->join('m.project', 'p')
            ->join('d.package', 'pkg')
            ->join('pkg.vendor', 'v')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.name', 'ASC')
            ->addOrderBy('pkg.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
