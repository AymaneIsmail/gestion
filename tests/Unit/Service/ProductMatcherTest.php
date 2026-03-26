<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Service\ProductMatcher;
use PHPUnit\Framework\TestCase;

class ProductMatcherTest extends TestCase
{
    private ProductMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ProductMatcher();
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function makeProduct(string $name, ?string $reference = null, ?float $sellingPrice = null): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setReference($reference);

        if ($sellingPrice !== null) {
            $price = new ProductPrice();
            $price->setSellingPriceCents((int) round($sellingPrice * 100));
            $product->setPrice($price);
        }

        return $product;
    }

    private function extracted(string $name, ?string $reference = null, ?float $sellingPrice = null): array
    {
        return ['name' => $name, 'reference' => $reference, 'sellingPrice' => $sellingPrice];
    }

    // ── Aucun produit existant ─────────────────────────────────────

    public function testNoMatchWhenExistingListIsEmpty(): void
    {
        $result = $this->matcher->findMatch($this->extracted('Produit X', null, 10.00), []);

        $this->assertSame('none', $result->confidence);
        $this->assertNull($result->product);
        $this->assertSame('create', $result->defaultAction);
        $this->assertFalse($result->hasMatch());
    }

    // ── Matching par référence ─────────────────────────────────────

    public function testStrongMatchByExactReference(): void
    {
        $existing = [$this->makeProduct('Cable USB-C', 'REF-001')];

        $result = $this->matcher->findMatch(
            $this->extracted('Câble USB C 2m', 'REF-001'),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
        $this->assertSame($existing[0], $result->product);
        $this->assertSame('update', $result->defaultAction);
        $this->assertTrue($result->hasMatch());
    }

    public function testReferenceMatchIsCaseInsensitive(): void
    {
        $existing = [$this->makeProduct('Produit A', 'ref-42')];

        $result = $this->matcher->findMatch(
            $this->extracted('Produit B', 'REF-42'),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
        $this->assertSame($existing[0], $result->product);
    }

    public function testReferenceMatchHandlesWhitespace(): void
    {
        $existing = [$this->makeProduct('Produit A', '  REF-001  ')];

        $result = $this->matcher->findMatch(
            $this->extracted('Produit B', 'REF-001'),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
    }

    public function testReferenceMatchTakesPriorityOverNameMatch(): void
    {
        $matchByName = $this->makeProduct('Cable USB-C 2m', 'OTHER', 19.99);
        $matchByRef  = $this->makeProduct('Produit inconnu', 'REF-001', 5.00);

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', 'REF-001', 19.99),
            [$matchByName, $matchByRef]
        );

        $this->assertSame($matchByRef, $result->product);
    }

    public function testNoReferenceMatchWhenExtractedHasNullReference(): void
    {
        $existing = [$this->makeProduct('Cable USB-C', 'REF-001')];

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C', null),
            $existing
        );

        // La référence du produit extrait est null → pas de match par référence
        // Tombe sur le matching nom : noms identiques (score 1.0) + prix neutre (0.5)
        // Score combiné = 1.0 × 0.75 + 0.5 × 0.25 = 0.875 ≥ 0.8 → 'strong'
        $this->assertSame('strong', $result->confidence);
        $this->assertSame($existing[0], $result->product);
    }

    // ── Matching par nom + prix ────────────────────────────────────

    public function testStrongMatchByIdenticalNameAndExactPrice(): void
    {
        $existing = [$this->makeProduct('Cable USB-C 2m', null, 19.99)];

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', null, 19.99),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
        $this->assertSame('update', $result->defaultAction);
    }

    public function testStrongMatchByVeryCloseNameAndSimilarPrice(): void
    {
        // Légère variation de ponctuation + prix à ±3%
        $existing = [$this->makeProduct('Cable USB C 2m', null, 20.00)];

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', null, 19.50),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
    }

    public function testNoMatchForCompletelyDifferentProducts(): void
    {
        $existing = [
            $this->makeProduct('Clavier mécanique', null, 89.99),
            $this->makeProduct('Souris sans fil', null, 45.00),
        ];

        $result = $this->matcher->findMatch(
            $this->extracted('Écran 4K 32 pouces', null, 499.99),
            $existing
        );

        $this->assertSame('none', $result->confidence);
        $this->assertNull($result->product);
        $this->assertSame('create', $result->defaultAction);
    }

    public function testBestCandidateSelectedWhenMultipleExist(): void
    {
        $close   = $this->makeProduct('Cable USB-C 2m', null, 19.99);
        $distant = $this->makeProduct('Adaptateur HDMI', null, 15.00);

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', null, 19.99),
            [$distant, $close]
        );

        $this->assertSame($close, $result->product);
    }

    // ── Comportement avec prix null ───────────────────────────────

    public function testNullImportedPriceIsNeutralAndDoesNotPreventNameMatch(): void
    {
        $existing = [$this->makeProduct('Cable USB-C 2m', null, 19.99)];

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', null, null),
            $existing
        );

        // Nom identique + prix neutre (0.5) → score > 0.55
        $this->assertNotSame('none', $result->confidence);
    }

    public function testNullExistingPriceIsNeutral(): void
    {
        $existing = [$this->makeProduct('Cable USB-C 2m')]; // sans prix

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C 2m', null, 19.99),
            $existing
        );

        $this->assertNotSame('none', $result->confidence);
    }

    public function testVeryDifferentPriceDegrades_ScoreToNonStrong(): void
    {
        // Même nom mais prix 10× différent
        $existing = [$this->makeProduct('Cable USB-C', null, 200.00)];

        $result = $this->matcher->findMatch(
            $this->extracted('Cable USB-C', null, 20.00),
            $existing
        );

        $this->assertNotSame('strong', $result->confidence);
    }

    // ── defaultAction selon la confidence ─────────────────────────

    public function testStrongMatchDefaultsToUpdate(): void
    {
        $existing = [$this->makeProduct('Produit A', 'REF-X')];

        $result = $this->matcher->findMatch(
            $this->extracted('Produit A', 'REF-X'),
            $existing
        );

        $this->assertSame('strong', $result->confidence);
        $this->assertSame('update', $result->defaultAction);
    }

    public function testNoneMatchDefaultsToCreate(): void
    {
        $existing = [$this->makeProduct('Produit totalement différent', null, 999.99)];

        $result = $this->matcher->findMatch(
            $this->extracted('Autre chose', null, 1.00),
            $existing
        );

        $this->assertSame('create', $result->defaultAction);
    }
}
