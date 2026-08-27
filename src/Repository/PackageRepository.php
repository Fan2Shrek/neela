<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Package>
 */
class PackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    /**
     * @return Package[]
     */
    public function findAllWithVendor(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('v')
            ->join('p.vendor', 'v')
            ->orderBy('v.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> vendor id => package count
     */
    public function countByVendor(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.vendor) AS vendorId', 'COUNT(p.id) AS packageCount')
            ->groupBy('p.vendor')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['vendorId']] = (int) $row['packageCount'];
        }

        return $counts;
    }

    public function findOneByVendorAndName(string $vendorName, string $packageName): ?Package
    {
        return $this->createQueryBuilder('p')
            ->addSelect('v')
            ->join('p.vendor', 'v')
            ->andWhere('v.name = :vendorName')
            ->andWhere('p.name = :packageName')
            ->setParameter('vendorName', $vendorName)
            ->setParameter('packageName', $packageName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * In-memory substring match against "vendor/name", same convention as
     * ProjectRepository::findNamesMatching().
     *
     * @return string[]
     */
    public function findFullNamesMatching(string $search, int $limit = 20): array
    {
        $names = array_map(
            static fn (Package $package): string => $package->getVendor()->getName().'/'.$package->getName(),
            $this->findAllWithVendor(),
        );

        $matching = array_values(array_filter(
            $names,
            static fn (string $name): bool => false !== stripos($name, $search),
        ));

        return \array_slice($matching, 0, $limit);
    }

    /**
     * Lazily streams package ids from the database instead of hydrating every
     * Package entity into memory at once (as findAll() would).
     *
     * @return iterable<int>
     */
    public function iterateAllIds(): iterable
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id')
            ->getQuery()
            ->toIterable();

        foreach ($rows as $row) {
            yield $row['id'];
        }
    }
}
