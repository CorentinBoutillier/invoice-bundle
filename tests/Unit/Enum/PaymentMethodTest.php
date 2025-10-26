<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Enum;

use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

class PaymentMethodTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = PaymentMethod::cases();

        $this->assertCount(6, $cases);
        $this->assertContains(PaymentMethod::BANK_TRANSFER, $cases);
        $this->assertContains(PaymentMethod::CREDIT_CARD, $cases);
        $this->assertContains(PaymentMethod::CHECK, $cases);
        $this->assertContains(PaymentMethod::CASH, $cases);
        $this->assertContains(PaymentMethod::DIRECT_DEBIT, $cases);
        $this->assertContains(PaymentMethod::OTHER, $cases);
    }

    public function testValuesAreCorrect(): void
    {
        $this->assertSame('bank_transfer', PaymentMethod::BANK_TRANSFER->value);
        $this->assertSame('credit_card', PaymentMethod::CREDIT_CARD->value);
        $this->assertSame('check', PaymentMethod::CHECK->value);
        $this->assertSame('cash', PaymentMethod::CASH->value);
        $this->assertSame('direct_debit', PaymentMethod::DIRECT_DEBIT->value);
        $this->assertSame('other', PaymentMethod::OTHER->value);
    }

    public function testFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(PaymentMethod::BANK_TRANSFER, PaymentMethod::from('bank_transfer'));
        $this->assertSame(PaymentMethod::CREDIT_CARD, PaymentMethod::from('credit_card'));
        $this->assertSame(PaymentMethod::CHECK, PaymentMethod::from('check'));
        $this->assertSame(PaymentMethod::CASH, PaymentMethod::from('cash'));
        $this->assertSame(PaymentMethod::DIRECT_DEBIT, PaymentMethod::from('direct_debit'));
        $this->assertSame(PaymentMethod::OTHER, PaymentMethod::from('other'));
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        PaymentMethod::from('invalid_method');
    }

    public function testTryFromMethodReturnsNullForInvalidValue(): void
    {
        $this->assertNull(PaymentMethod::tryFrom('invalid_method'));
    }

    public function testTryFromMethodReturnsCorrectCase(): void
    {
        $this->assertSame(PaymentMethod::BANK_TRANSFER, PaymentMethod::tryFrom('bank_transfer'));
        $this->assertSame(PaymentMethod::CASH, PaymentMethod::tryFrom('cash'));
    }
}
