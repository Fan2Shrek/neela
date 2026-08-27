<?php

namespace App\Entity;

use App\Repository\VersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VersionRepository::class)]
class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Package::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    private Package $package;

    #[ORM\Column(length: 255)]
    private string $version;

    #[ORM\Column(length: 255)]
    private string $normalizedVersion;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $runtimeConstraint = null;

    public function __construct(Package $package, string $version, string $normalizedVersion)
    {
        $this->package = $package;
        $this->version = $version;
        $this->normalizedVersion = $normalizedVersion;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getNormalizedVersion(): string
    {
        return $this->normalizedVersion;
    }

    public function setNormalizedVersion(string $normalizedVersion): static
    {
        $this->normalizedVersion = $normalizedVersion;

        return $this;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): static
    {
        $this->releasedAt = $releasedAt;

        return $this;
    }

    public function getRuntimeConstraint(): ?string
    {
        return $this->runtimeConstraint;
    }

    public function setRuntimeConstraint(?string $runtimeConstraint): static
    {
        $this->runtimeConstraint = $runtimeConstraint;

        return $this;
    }
}
