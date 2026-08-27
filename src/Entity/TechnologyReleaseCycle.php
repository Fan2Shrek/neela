<?php

namespace App\Entity;

use App\Enum\Technology;
use App\Repository\TechnologyReleaseCycleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TechnologyReleaseCycleRepository::class)]
#[ORM\UniqueConstraint(name: 'technology_cycle_unique', columns: ['technology', 'cycle'])]
class TechnologyReleaseCycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 20, enumType: Technology::class)]
    private Technology $technology;

    #[ORM\Column(length: 20)]
    private string $cycle;

    #[ORM\Column(length: 255)]
    private string $latestVersion;

    #[ORM\Column]
    private bool $lts;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $eolDate = null;

    public function __construct(Technology $technology, string $cycle, string $latestVersion, bool $lts)
    {
        $this->technology = $technology;
        $this->cycle = $cycle;
        $this->latestVersion = $latestVersion;
        $this->lts = $lts;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTechnology(): Technology
    {
        return $this->technology;
    }

    public function getCycle(): string
    {
        return $this->cycle;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function setLatestVersion(string $latestVersion): static
    {
        $this->latestVersion = $latestVersion;

        return $this;
    }

    public function isLts(): bool
    {
        return $this->lts;
    }

    public function setLts(bool $lts): static
    {
        $this->lts = $lts;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getEolDate(): ?\DateTimeImmutable
    {
        return $this->eolDate;
    }

    public function setEolDate(?\DateTimeImmutable $eolDate): static
    {
        $this->eolDate = $eolDate;

        return $this;
    }
}
