<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\MatchResult;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class MatchResultTest extends TestCase
{
    public function testHasMatchReturnsTrueWhenProductIsSet(): void
    {
        $product = $this->createStub(Product::class);
        $result  = new MatchResult('strong', $product, 'update');

        $this->assertTrue($result->hasMatch());
    }

    public function testHasMatchReturnsFalseWhenProductIsNull(): void
    {
        $result = new MatchResult('none', null, 'create');

        $this->assertFalse($result->hasMatch());
    }

    public function testAllPropertiesAreAccessible(): void
    {
        $product = $this->createStub(Product::class);
        $result  = new MatchResult('probable', $product, 'create');

        $this->assertSame('probable', $result->confidence);
        $this->assertSame($product, $result->product);
        $this->assertSame('create', $result->defaultAction);
    }

    public function testStrongConfidenceWithUpdateAction(): void
    {
        $product = $this->createStub(Product::class);
        $result  = new MatchResult('strong', $product, 'update');

        $this->assertSame('strong', $result->confidence);
        $this->assertSame('update', $result->defaultAction);
        $this->assertTrue($result->hasMatch());
    }

    public function testNoneConfidenceWithNullProduct(): void
    {
        $result = new MatchResult('none', null, 'create');

        $this->assertSame('none', $result->confidence);
        $this->assertNull($result->product);
        $this->assertSame('create', $result->defaultAction);
        $this->assertFalse($result->hasMatch());
    }
}
