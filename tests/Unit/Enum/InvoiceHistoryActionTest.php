<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\InvoiceHistoryAction;
use PHPUnit\Framework\TestCase;

class InvoiceHistoryActionTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = InvoiceHistoryAction::cases();

        $this->assertCount(8, $cases);
        $this->assertContains(InvoiceHistoryAction::CREATED, $cases);
        $this->assertContains(InvoiceHistoryAction::FINALIZED, $cases);
        $this->assertContains(InvoiceHistoryAction::SENT, $cases);
        $this->assertContains(InvoiceHistoryAction::PAID, $cases);
        $this->assertContains(InvoiceHistoryAction::PAYMENT_RECEIVED, $cases);
        $this->assertContains(InvoiceHistoryAction::CANCELLED, $cases);
        $this->assertContains(InvoiceHistoryAction::STATUS_CHANGED, $cases);
        $this->assertContains(InvoiceHistoryAction::EDITED, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('created', InvoiceHistoryAction::CREATED->value);
        $this->assertSame('finalized', InvoiceHistoryAction::FINALIZED->value);
        $this->assertSame('sent', InvoiceHistoryAction::SENT->value);
        $this->assertSame('paid', InvoiceHistoryAction::PAID->value);
        $this->assertSame('payment_received', InvoiceHistoryAction::PAYMENT_RECEIVED->value);
        $this->assertSame('cancelled', InvoiceHistoryAction::CANCELLED->value);
        $this->assertSame('status_changed', InvoiceHistoryAction::STATUS_CHANGED->value);
        $this->assertSame('edited', InvoiceHistoryAction::EDITED->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceHistoryAction::CREATED, InvoiceHistoryAction::from('created'));
        $this->assertSame(InvoiceHistoryAction::FINALIZED, InvoiceHistoryAction::from('finalized'));
        $this->assertSame(InvoiceHistoryAction::SENT, InvoiceHistoryAction::from('sent'));
        $this->assertSame(InvoiceHistoryAction::PAYMENT_RECEIVED, InvoiceHistoryAction::from('payment_received'));
        $this->assertSame(InvoiceHistoryAction::CANCELLED, InvoiceHistoryAction::from('cancelled'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        InvoiceHistoryAction::from('invalid_action');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(InvoiceHistoryAction::tryFrom('invalid_action'));
    }

    public function testTryFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceHistoryAction::CREATED, InvoiceHistoryAction::tryFrom('created'));
        $this->assertSame(InvoiceHistoryAction::PAID, InvoiceHistoryAction::tryFrom('paid'));
    }
}
