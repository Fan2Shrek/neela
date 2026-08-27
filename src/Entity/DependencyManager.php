<?php

namespace App\Entity;

use App\Repository\DependencyManagerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DependencyManagerRepository::class)]
class DependencyManager
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /**
     * @var Collection<int, Vendor>
     */
    #[ORM\OneToMany(targetEntity: Vendor::class, mappedBy: 'dependencyManager')]
    private Collection $vendors;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->vendors = new ArrayCollection();
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

    /**
     * @return Collection<int, Vendor>
     */
    public function getVendors(): Collection
    {
        return $this->vendors;
    }

    public function addVendor(Vendor $vendor): static
    {
        if (!$this->vendors->contains($vendor)) {
            $this->vendors->add($vendor);
            $vendor->setDependencyManager($this);
        }

        return $this;
    }

    public function removeVendor(Vendor $vendor): static
    {
        $this->vendors->removeElement($vendor);

        return $this;
    }
}
