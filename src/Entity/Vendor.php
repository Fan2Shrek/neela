<?php

namespace App\Entity;

use App\Repository\VendorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorRepository::class)]
class Vendor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: DependencyManager::class, inversedBy: 'vendors')]
    #[ORM\JoinColumn(nullable: false)]
    private DependencyManager $dependencyManager;

    /**
     * @var Collection<int, Package>
     */
    #[ORM\OneToMany(targetEntity: Package::class, mappedBy: 'vendor')]
    private Collection $packages;

    public function __construct(string $name, DependencyManager $dependencyManager)
    {
        $this->name = $name;
        $this->dependencyManager = $dependencyManager;
        $this->packages = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @return Collection<int, Package>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(Package $package): static
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
            $package->setVendor($this);
        }

        return $this;
    }

    public function removePackage(Package $package): static
    {
        $this->packages->removeElement($package);

        return $this;
    }
}
