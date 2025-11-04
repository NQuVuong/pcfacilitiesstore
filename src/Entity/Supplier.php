<?php

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: Product::class)]
    private Collection $products;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): self { $this->email = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): self { $this->address = $v; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): self { $this->note = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setSupplier($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getSupplier() === $this) {
                $product->setSupplier(null);
            }
        }

        return $this;
    }
}
