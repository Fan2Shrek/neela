<?php

namespace App\Repository;

use App\Entity\Manifest;
use App\Entity\ManifestTechnology;
use App\Enum\Technology;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ManifestTechnology>
 */
class ManifestTechnologyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManifestTechnology::class);
    }

    public function findOneByManifestAndTechnology(Manifest $manifest, Technology $technology): ?ManifestTechnology
    {
        return $this->findOneBy(['manifest' => $manifest, 'technology' => $technology]);
    }

    /**
     * @return array<int, ManifestTechnology[]> manifest id => its detected technologies
     */
    public function findAllIndexedByManifestId(): array
    {
        $rows = [];

        foreach ($this->findAll() as $manifestTechnology) {
            $rows[$manifestTechnology->getManifest()->getId()][] = $manifestTechnology;
        }

        return $rows;
    }
}
