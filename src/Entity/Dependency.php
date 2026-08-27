<?php

namespace App\Entity;

use App\Repository\DependencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DependencyRepository::class)]
class Dependency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Manifest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Manifest $manifest;

    #[ORM\ManyToOne(targetEntity: Package::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Package $package;

    #[ORM\Column(length: 255)]
    private string $constraint;

    #[ORM\Column(length: 255)]
    private string $lockedVersion;

    #[ORM\Column(length: 255)]
    private string $dependencyType;

    public function __construct(
        Manifest $manifest,
        Package $package,
        string $constraint,
        string $lockedVersion,
        string $dependencyType,
    ) {
        $this->manifest = $manifest;
        $this->package = $package;
        $this->constraint = $constraint;
        $this->lockedVersion = $lockedVersion;
        $this->dependencyType = $dependencyType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function setManifest(Manifest $manifest): static
    {
        $this->manifest = $manifest;

        return $this;
    }

    public function getPackage(): Package
    {
        return $this->package;
    }

    public function setPackage(Package $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getConstraint(): string
    {
        return $this->constraint;
    }

    public function setConstraint(string $constraint): static
    {
        $this->constraint = $constraint;

        return $this;
    }

    public function getLockedVersion(): string
    {
        return $this->lockedVersion;
    }

    public function setLockedVersion(string $lockedVersion): static
    {
        $this->lockedVersion = $lockedVersion;

        return $this;
    }

    public function getDependencyType(): string
    {
        return $this->dependencyType;
    }

    public function setDependencyType(string $dependencyType): static
    {
        $this->dependencyType = $dependencyType;

        return $this;
    }
}
