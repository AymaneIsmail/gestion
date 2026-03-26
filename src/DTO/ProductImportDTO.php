<?php

declare(strict_types=1);

namespace App\DTO;

class ProductImportDTO
{
    public string $name = '';
    public ?string $reference = null;
    public ?string $description = null;
    public ?float $purchasePrice = null;
    public ?float $sellingPrice = null;
    public int $stockQuantity = 0;
}
