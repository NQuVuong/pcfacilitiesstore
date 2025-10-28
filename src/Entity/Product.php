<?php

namespace App\Entity;
use App\Entity\Category;
use App\Entity\Brand;
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

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $created = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Brand $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?int $Quantity = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Price::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])] 
    private Collection $prices;
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;
     #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $slug = '';

    public function __construct()
    {
        $this->prices = new ArrayCollection();
        $this->images = new ArrayCollection();
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

    public function getCategory(): ?Category 
    { 
        return $this->category; 
    }
    public function setCategory(?Category $category): static 
    { 
        $this->category = $category; 
        return $this; 
    }

    public function getBrand(): ?Brand 
    { 
        return $this->brand; 
    }
    public function setBrand(?Brand $brand): static 
    { 
        $this->brand = $brand; 
        return $this; 
    }
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

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection { return $this->images; }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }
    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }
        return $this;
    }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
}
