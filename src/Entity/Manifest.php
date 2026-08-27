<?php

namespace App\Entity;

use App\Repository\ManifestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManifestRepository::class)]
#[ORM\UniqueConstraint(name: 'project_path_unique', columns: ['project_id', 'path'])]
class Manifest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: DependencyManager::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DependencyManager $dependencyManager;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockPath = null;

    public function __construct(
        Project $project,
        DependencyManager $dependencyManager,
        string $path,
        ?string $lockPath = null,
    ) {
        $this->project = $project;
        $this->dependencyManager = $dependencyManager;
        $this->path = $path;
        $this->lockPath = $lockPath;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getDependencyManager(): DependencyManager
    {
        return $this->dependencyManager;
    }

    public function setDependencyManager(DependencyManager $dependencyManager): static
    {
        $this->dependencyManager = $dependencyManager;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getLockPath(): ?string
    {
        return $this->lockPath;
    }

    public function setLockPath(?string $lockPath): static
    {
        $this->lockPath = $lockPath;

        return $this;
    }
}
