<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prices are stored as integers in cents (EUR).
 * Example: 1999 = 19,99 €
 */
#[ORM\Entity(repositoryClass: ProductPriceRepository::class)]
class ProductPrice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Product::class, inversedBy: 'price')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /**
     * Purchase price in cents (EUR).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $purchasePriceCents = null;

    /**
     * Selling price in cents (EUR).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $sellingPriceCents = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getPurchasePriceCents(): ?int
    {
        return $this->purchasePriceCents;
    }

    public function setPurchasePriceCents(?int $purchasePriceCents): static
    {
        $this->purchasePriceCents = $purchasePriceCents;

        return $this;
    }

    public function getSellingPriceCents(): ?int
    {
        return $this->sellingPriceCents;
    }

    public function setSellingPriceCents(?int $sellingPriceCents): static
    {
        $this->sellingPriceCents = $sellingPriceCents;

        return $this;
    }

    /**
     * Helper: purchase price as a decimal float for display purposes only.
     */
    public function getPurchasePriceDecimal(): ?float
    {
        return $this->purchasePriceCents !== null ? $this->purchasePriceCents / 100 : null;
    }

    /**
     * Helper: selling price as a decimal float for display purposes only.
     */
    public function getSellingPriceDecimal(): ?float
    {
        return $this->sellingPriceCents !== null ? $this->sellingPriceCents / 100 : null;
    }
}
