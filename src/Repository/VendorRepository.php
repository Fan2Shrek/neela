<?php

namespace App\Repository;

use App\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vendor>
 */
class VendorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vendor::class);
    }

    /**
     * @return Vendor[]
     */
    public function findAllWithDependencyManager(): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('dm')
            ->join('v.dependencyManager', 'dm')
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
