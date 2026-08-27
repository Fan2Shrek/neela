<?php

namespace App\Entity;

use App\Repository\AppSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Single-row table for app-wide configuration editable from the UI at runtime,
 * as opposed to env vars which need a redeploy/restart to change.
 */
#[ORM\Entity(repositoryClass: AppSettingsRepository::class)]
class AppSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $githubToken = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getGithubToken(): ?string
    {
        return $this->githubToken;
    }

    public function setGithubToken(?string $githubToken): static
    {
        $this->githubToken = $githubToken;

        return $this;
    }
}
