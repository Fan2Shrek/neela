<?php

namespace App\Entity;

use App\Enum\Technology;
use App\Repository\ManifestTechnologyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A technology detected from a manifest's own declaration rather than from one of its
 * dependencies (e.g. PHP's version constraint in composer.json's require.php) — see
 * TechnologyDetector for the dependency-signal-based kind (Symfony, Laravel, ...).
 */
#[ORM\Entity(repositoryClass: ManifestTechnologyRepository::class)]
#[ORM\UniqueConstraint(name: 'manifest_technology_unique', columns: ['manifest_id', 'technology'])]
class ManifestTechnology
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Manifest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Manifest $manifest;

    #[ORM\Column(length: 20, enumType: Technology::class)]
    private Technology $technology;

    #[ORM\Column(length: 255)]
    private string $version;

    #[ORM\Column(length: 255)]
    private string $source;

    public function __construct(Manifest $manifest, Technology $technology, string $version, string $source)
    {
        $this->manifest = $manifest;
        $this->technology = $technology;
        $this->version = $version;
        $this->source = $source;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getTechnology(): Technology
    {
        return $this->technology;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
