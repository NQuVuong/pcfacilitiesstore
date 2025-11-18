<?php

namespace App\Entity;

use App\Repository\VisitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisitRepository::class)]
class Visit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 100)]
    private string $routeName;

    #[ORM\Column(length: 50)]
    private string $browser;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $visitedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    public function getId(): ?int { return $this->id; }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }

    public function getRouteName(): string { return $this->routeName; }
    public function setRouteName(string $routeName): self { $this->routeName = $routeName; return $this; }

    public function getBrowser(): string { return $this->browser; }
    public function setBrowser(string $browser): self { $this->browser = $browser; return $this; }

    public function getVisitedAt(): \DateTimeImmutable { return $this->visitedAt; }
    public function setVisitedAt(\DateTimeImmutable $visitedAt): self { $this->visitedAt = $visitedAt; return $this; }

    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): self { $this->ip = $ip; return $this; }
}
