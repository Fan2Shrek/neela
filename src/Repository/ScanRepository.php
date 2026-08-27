<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Scan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scan>
 */
class ScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scan::class);
    }

    public function findLatestForProject(Project $project): ?Scan
    {
        return $this->createQueryBuilder('s')
            ->join('s.manifest', 'm')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Scan[]
     */
    public function findByProjectOrderedByMostRecent(Project $project): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('m')
            ->join('s.manifest', 'm')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Scan[]
     */
    public function findAllWithRelationsOrderedByMostRecent(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('m', 'p')
            ->join('s.manifest', 'm')
            ->join('m.project', 'p')
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
