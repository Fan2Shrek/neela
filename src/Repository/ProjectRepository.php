<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findOneBySshLink(string $sshLink): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.sshLink = :sshLink')
            ->setParameter('sshLink', $sshLink)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * In-memory substring match, same as the rest of the app's list-page filters
     * (PackageController et al.) — the fleet sizes this targets don't warrant a DB-level
     * LIKE query, and this sidesteps escaping LIKE wildcards in the search term.
     *
     * @return string[]
     */
    public function findNamesMatching(string $search, int $limit = 20): array
    {
        $names = array_column(
            $this->createQueryBuilder('p')
                ->select('p.name')
                ->orderBy('p.name', 'ASC')
                ->getQuery()
                ->getArrayResult(),
            'name',
        );

        $matching = array_values(array_filter(
            $names,
            static fn (string $name): bool => false !== stripos($name, $search),
        ));

        return \array_slice($matching, 0, $limit);
    }
}
