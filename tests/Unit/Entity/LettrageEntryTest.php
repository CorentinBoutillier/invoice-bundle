<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\Lettrage;
use CorentinBoutillier\InvoiceBundle\Entity\LettrageEntry;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour l'entité LettrageEntry.
 *
 * Une entrée de lettrage représente un lien entre un lettrage et soit
 * une facture (débit) soit un paiement (crédit), avec le montant lettré.
 */
final class LettrageEntryTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    private Lettrage $lettrage;

    protected function setUp(): void
    {
        $this->lettrage = new Lettrage(
            code: 'A',
            date: new \DateTimeImmutable('2025-06-15'),
            companyId: 1,
        );
    }

    // ========== Construction avec Invoice (débit) ==========

    public function testConstructWithInvoice(): void
    {
        $invoice = $this->createMock(Invoice::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 10000,
            type: LettrageEntryType::DEBIT,
        );

        $this->assertSame($this->lettrage, $entry->getLettrage());
        $this->assertSame($invoice, $entry->getInvoice());
        $this->assertNull($entry->getPayment());
        $this->assertSame(10000, $entry->getAmountCents());
        $this->assertSame(LettrageEntryType::DEBIT, $entry->getType());
    }

    public function testConstructWithInvoiceReturnsMoneyObject(): void
    {
        $invoice = $this->createMock(Invoice::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 12050,
            type: LettrageEntryType::DEBIT,
        );

        $amount = $entry->getAmount();
        $this->assertInstanceOf(Money::class, $amount);
        $this->assertSame('120.50', $amount->toEuros());
    }

    // ========== Construction avec Payment (crédit) ==========

    public function testConstructWithPayment(): void
    {
        $payment = $this->createMock(Payment::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: null,
            payment: $payment,
            amountCents: 7500,
            type: LettrageEntryType::CREDIT,
        );

        $this->assertSame($this->lettrage, $entry->getLettrage());
        $this->assertNull($entry->getInvoice());
        $this->assertSame($payment, $entry->getPayment());
        $this->assertSame(7500, $entry->getAmountCents());
        $this->assertSame(LettrageEntryType::CREDIT, $entry->getType());
    }

    // ========== Validation de l'exclusivité Invoice/Payment ==========

    public function testConstructWithBothInvoiceAndPaymentThrowsException(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $payment = $this->createMock(Payment::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LettrageEntry must have either an invoice OR a payment, not both');

        new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: $payment,
            amountCents: 10000,
            type: LettrageEntryType::DEBIT,
        );
    }

    public function testConstructWithNeitherInvoiceNorPaymentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LettrageEntry must have either an invoice OR a payment');

        new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: null,
            payment: null,
            amountCents: 10000,
            type: LettrageEntryType::DEBIT,
        );
    }

    // ========== Validation du montant ==========

    public function testConstructWithZeroAmountThrowsException(): void
    {
        $invoice = $this->createMock(Invoice::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 0,
            type: LettrageEntryType::DEBIT,
        );
    }

    public function testConstructWithNegativeAmountThrowsException(): void
    {
        $invoice = $this->createMock(Invoice::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: -5000,
            type: LettrageEntryType::DEBIT,
        );
    }

    // ========== ID ==========

    public function testIdIsNullBeforePersistence(): void
    {
        $invoice = $this->createMock(Invoice::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 10000,
            type: LettrageEntryType::DEBIT,
        );

        $this->assertNull($entry->getId());
    }

    // ========== Scénarios réels ==========

    public function testInvoiceDebitEntry(): void
    {
        // Scénario : Facture de 150€ TTC à lettrer au débit
        $invoice = $this->createMock(Invoice::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 15000,
            type: LettrageEntryType::DEBIT,
        );

        $this->assertSame(LettrageEntryType::DEBIT, $entry->getType());
        $this->assertSame('150.00', $entry->getAmount()->toEuros());
        $this->assertNotNull($entry->getInvoice());
    }

    public function testPaymentCreditEntry(): void
    {
        // Scénario : Paiement par virement de 150€ à lettrer au crédit
        $payment = $this->createMock(Payment::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: null,
            payment: $payment,
            amountCents: 15000,
            type: LettrageEntryType::CREDIT,
        );

        $this->assertSame(LettrageEntryType::CREDIT, $entry->getType());
        $this->assertSame('150.00', $entry->getAmount()->toEuros());
        $this->assertNotNull($entry->getPayment());
    }

    public function testPartialLettrageEntry(): void
    {
        // Scénario : Lettrage partiel - facture de 200€ mais seulement 75€ lettrés
        $invoice = $this->createMock(Invoice::class);

        $entry = new LettrageEntry(
            lettrage: $this->lettrage,
            invoice: $invoice,
            payment: null,
            amountCents: 7500, // Montant lettré, pas le total facture
            type: LettrageEntryType::DEBIT,
        );

        $this->assertSame(7500, $entry->getAmountCents());
        $this->assertSame('75.00', $entry->getAmount()->toEuros());
    }
}
