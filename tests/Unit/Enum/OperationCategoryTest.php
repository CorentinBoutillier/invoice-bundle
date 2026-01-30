<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\OperationCategory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OperationCategory enum.
 */
class OperationCategoryTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = OperationCategory::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(OperationCategory::GOODS, $cases);
        $this->assertContains(OperationCategory::SERVICES, $cases);
        $this->assertContains(OperationCategory::MIXED, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('goods', OperationCategory::GOODS->value);
        $this->assertSame('services', OperationCategory::SERVICES->value);
        $this->assertSame('mixed', OperationCategory::MIXED->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(OperationCategory::GOODS, OperationCategory::from('goods'));
        $this->assertSame(OperationCategory::SERVICES, OperationCategory::from('services'));
        $this->assertSame(OperationCategory::MIXED, OperationCategory::from('mixed'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        OperationCategory::from('invalid');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(OperationCategory::tryFrom('invalid'));
    }

    public function testIsVatOnDebitsReturnsTrueForGoods(): void
    {
        $this->assertTrue(OperationCategory::GOODS->isVatOnDebits());
    }

    public function testIsVatOnDebitsReturnsFalseForServices(): void
    {
        $this->assertFalse(OperationCategory::SERVICES->isVatOnDebits());
    }

    public function testIsVatOnDebitsReturnsFalseForMixed(): void
    {
        // Mixed defaults to services behavior (VAT on receipt)
        $this->assertFalse(OperationCategory::MIXED->isVatOnDebits());
    }
}
