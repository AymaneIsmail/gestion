<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MatchResult;
use App\Entity\Product;
use App\Entity\ProductPrice;

class ProductMatcher
{
    /**
     * @param array{name: string, reference: ?string, sellingPrice: ?float} $extracted
     * @param Product[] $existing
     */
    public function findMatch(array $extracted, array $existing): MatchResult
    {
        if (empty($existing)) {
            return new MatchResult('none', null, 'create');
        }

        // 1. Référence exacte → match fort
        if (!empty($extracted['reference'])) {
            $ref = mb_strtolower(trim($extracted['reference']));
            foreach ($existing as $product) {
                if ($product->getReference() !== null
                    && mb_strtolower(trim($product->getReference())) === $ref) {
                    return new MatchResult('strong', $product, 'update');
                }
            }
        }

        // 2. Nom + prix → score combiné
        $bestProduct = null;
        $bestScore   = 0.0;

        foreach ($existing as $product) {
            $nameScore  = $this->nameSimilarity($extracted['name'], $product->getName());
            $priceScore = $this->priceScore($extracted['sellingPrice'] ?? null, $product->getPrice());
            $score      = $nameScore * 0.75 + $priceScore * 0.25;

            if ($score > $bestScore) {
                $bestScore   = $score;
                $bestProduct = $product;
            }
        }

        if ($bestProduct !== null && $bestScore >= 0.8) {
            return new MatchResult('strong', $bestProduct, 'update');
        }

        if ($bestProduct !== null && $bestScore >= 0.55) {
            return new MatchResult('probable', $bestProduct, 'create');
        }

        return new MatchResult('none', null, 'create');
    }

    private function nameSimilarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));

        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        return max(0.0, 1.0 - levenshtein($a, $b) / $maxLen);
    }

    private function priceScore(?float $imported, ?ProductPrice $existingPrice): float
    {
        if ($imported === null || $existingPrice === null) {
            return 0.5;
        }

        $existing = $existingPrice->getSellingPriceDecimal();
        if ($existing === null) {
            return 0.5;
        }

        $avg = ($imported + $existing) / 2;
        if ($avg <= 0) {
            return $imported === $existing ? 1.0 : 0.0;
        }

        $ratio = abs($imported - $existing) / $avg;

        return match (true) {
            $ratio <= 0.05 => 1.0,
            $ratio <= 0.15 => 0.7,
            $ratio <= 0.30 => 0.3,
            default        => 0.0,
        };
    }
}
