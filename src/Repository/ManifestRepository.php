<?php

namespace App\Repository;

use App\Entity\Manifest;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Manifest>
 */
class ManifestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manifest::class);
    }

    /**
     * @return Manifest[]
     */
    public function findAllWithProjectAndDependencyManager(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('p', 'dm')
            ->join('m.project', 'p')
            ->join('m.dependencyManager', 'dm')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('m.path', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Manifest[]
     */
    public function findByProjectWithDependencyManager(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('dm')
            ->join('m.dependencyManager', 'dm')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('m.path', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
