<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }

    public function getPath(): ?string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}
