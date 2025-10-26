<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use PHPUnit\Framework\TestCase;

class InvoiceTypeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = InvoiceType::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(InvoiceType::INVOICE, $cases);
        $this->assertContains(InvoiceType::CREDIT_NOTE, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('invoice', InvoiceType::INVOICE->value);
        $this->assertSame('credit_note', InvoiceType::CREDIT_NOTE->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceType::INVOICE, InvoiceType::from('invoice'));
        $this->assertSame(InvoiceType::CREDIT_NOTE, InvoiceType::from('credit_note'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        InvoiceType::from('invalid_type');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(InvoiceType::tryFrom('invalid_type'));
    }

    public function testTryFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceType::INVOICE, InvoiceType::tryFrom('invoice'));
        $this->assertSame(InvoiceType::CREDIT_NOTE, InvoiceType::tryFrom('credit_note'));
    }
}
