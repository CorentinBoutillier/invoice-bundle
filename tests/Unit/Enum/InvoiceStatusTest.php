<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = InvoiceStatus::cases();

        $this->assertCount(7, $cases);
        $this->assertContains(InvoiceStatus::DRAFT, $cases);
        $this->assertContains(InvoiceStatus::FINALIZED, $cases);
        $this->assertContains(InvoiceStatus::SENT, $cases);
        $this->assertContains(InvoiceStatus::PAID, $cases);
        $this->assertContains(InvoiceStatus::PARTIALLY_PAID, $cases);
        $this->assertContains(InvoiceStatus::OVERDUE, $cases);
        $this->assertContains(InvoiceStatus::CANCELLED, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('draft', InvoiceStatus::DRAFT->value);
        $this->assertSame('finalized', InvoiceStatus::FINALIZED->value);
        $this->assertSame('sent', InvoiceStatus::SENT->value);
        $this->assertSame('paid', InvoiceStatus::PAID->value);
        $this->assertSame('partially_paid', InvoiceStatus::PARTIALLY_PAID->value);
        $this->assertSame('overdue', InvoiceStatus::OVERDUE->value);
        $this->assertSame('cancelled', InvoiceStatus::CANCELLED->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceStatus::DRAFT, InvoiceStatus::from('draft'));
        $this->assertSame(InvoiceStatus::FINALIZED, InvoiceStatus::from('finalized'));
        $this->assertSame(InvoiceStatus::SENT, InvoiceStatus::from('sent'));
        $this->assertSame(InvoiceStatus::PAID, InvoiceStatus::from('paid'));
        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::from('partially_paid'));
        $this->assertSame(InvoiceStatus::OVERDUE, InvoiceStatus::from('overdue'));
        $this->assertSame(InvoiceStatus::CANCELLED, InvoiceStatus::from('cancelled'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        InvoiceStatus::from('invalid_status');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(InvoiceStatus::tryFrom('invalid_status'));
    }

    public function testTryFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceStatus::DRAFT, InvoiceStatus::tryFrom('draft'));
        $this->assertSame(InvoiceStatus::PAID, InvoiceStatus::tryFrom('paid'));
    }
}
