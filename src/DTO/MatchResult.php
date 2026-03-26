<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Product;

final class MatchResult
{
    public function __construct(
        /** 'strong' | 'probable' | 'none' */
        public readonly string $confidence,
        public readonly ?Product $product,
        /** 'update' | 'create' */
        public readonly string $defaultAction,
    ) {}

    public function hasMatch(): bool
    {
        return $this->product !== null;
    }
}
