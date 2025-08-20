<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Dùng type cũ 'date' (hợp với DBAL 2.x) và DateTimeInterface cho PHP type
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?int $Quantity = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Price::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])] // phần tử đầu là bản ghi mới nhất
    private Collection $prices;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    // Đồng bộ kiểu với property: DateTimeInterface
    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->Quantity;
    }

    public function setQuantity(int $Quantity): static
    {
        $this->Quantity = $Quantity;
        return $this;
    }

    /** @return Collection<int, Price> */
    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function addPrice(Price $price): self
    {
        if (!$this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setProduct($this);
        }
        return $this;
    }

    public function removePrice(Price $price): self
    {
        if ($this->prices->removeElement($price)) {
            if ($price->getProduct() === $this) {
                $price->setProduct(null);
            }
        }
        return $this;
    }

    /** Giá xuất hiện tại (bản ghi Price mới nhất) */
    public function getCurrentExportPrice(): ?float
    {
        $latest = $this->prices->first(); // nhờ OrderBy DESC
        return $latest ? $latest->getExportPrice() : null;
    }

    /** Giá nhập gốc (bản ghi Price cũ nhất) */
    public function getOriginalImportPrice(): ?float
    {
        $oldest = $this->prices->last(); // nhờ OrderBy DESC
        return $oldest ? $oldest->getImportPrice() : null;
    }
}
