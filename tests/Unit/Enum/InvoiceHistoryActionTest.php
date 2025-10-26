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

        $this->assertCount(9, $cases);
        $this->assertContains(InvoiceHistoryAction::CREATED, $cases);
        $this->assertContains(InvoiceHistoryAction::UPDATED, $cases);
        $this->assertContains(InvoiceHistoryAction::FINALIZED, $cases);
        $this->assertContains(InvoiceHistoryAction::SENT, $cases);
        $this->assertContains(InvoiceHistoryAction::PAYMENT_RECORDED, $cases);
        $this->assertContains(InvoiceHistoryAction::STATUS_CHANGED, $cases);
        $this->assertContains(InvoiceHistoryAction::CANCELLED, $cases);
        $this->assertContains(InvoiceHistoryAction::PDF_GENERATED, $cases);
        $this->assertContains(InvoiceHistoryAction::PDF_DOWNLOADED, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('created', InvoiceHistoryAction::CREATED->value);
        $this->assertSame('updated', InvoiceHistoryAction::UPDATED->value);
        $this->assertSame('finalized', InvoiceHistoryAction::FINALIZED->value);
        $this->assertSame('sent', InvoiceHistoryAction::SENT->value);
        $this->assertSame('payment_recorded', InvoiceHistoryAction::PAYMENT_RECORDED->value);
        $this->assertSame('status_changed', InvoiceHistoryAction::STATUS_CHANGED->value);
        $this->assertSame('cancelled', InvoiceHistoryAction::CANCELLED->value);
        $this->assertSame('pdf_generated', InvoiceHistoryAction::PDF_GENERATED->value);
        $this->assertSame('pdf_downloaded', InvoiceHistoryAction::PDF_DOWNLOADED->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(InvoiceHistoryAction::CREATED, InvoiceHistoryAction::from('created'));
        $this->assertSame(InvoiceHistoryAction::FINALIZED, InvoiceHistoryAction::from('finalized'));
        $this->assertSame(InvoiceHistoryAction::SENT, InvoiceHistoryAction::from('sent'));
        $this->assertSame(InvoiceHistoryAction::PAYMENT_RECORDED, InvoiceHistoryAction::from('payment_recorded'));
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
        $this->assertSame(InvoiceHistoryAction::PDF_GENERATED, InvoiceHistoryAction::tryFrom('pdf_generated'));
    }
}
