<?php

namespace App\Repository;

use App\Entity\Version;
use App\Enum\Stability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Version>
 */
class VersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Version::class);
    }

    /**
     * All stable release strings (pre-releases excluded) for a set of packages, grouped
     * by package id — the candidate pool a constraint is later checked against. Scoped to
     * the given packages only, since some have hundreds of releases.
     *
     * @param int[] $packageIds
     *
     * @return array<int, string[]> package id => version strings
     */
    public function findStableVersionsIndexedByPackageId(array $packageIds): array
    {
        if ([] === $packageIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.package) AS packageId', 'v.version')
            ->andWhere('v.package IN (:packageIds)')
            ->andWhere('v.stability = :stability')
            ->setParameter('packageIds', $packageIds)
            ->setParameter('stability', Stability::STABLE)
            ->getQuery()
            ->getArrayResult();

        $versionsByPackageId = [];
        foreach ($rows as $row) {
            $versionsByPackageId[(int) $row['packageId']][] = $row['version'];
        }

        return $versionsByPackageId;
    }
}
