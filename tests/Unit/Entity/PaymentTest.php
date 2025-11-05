<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    // ========== Construction & Basic Properties ==========

    public function testConstructWithAllRequiredProperties(): void
    {
        $amount = Money::fromEuros('100.00');
        $paidAt = new \DateTimeImmutable('2024-06-15 14:30:00');

        $payment = new Payment(
            amount: $amount,
            paidAt: $paidAt,
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($paidAt, $payment->getPaidAt());
        $this->assertSame(PaymentMethod::BANK_TRANSFER, $payment->getMethod());
    }

    public function testAmountReturnsMoneyObject(): void
    {
        $amount = Money::fromEuros('250.50');
        $paidAt = new \DateTimeImmutable();

        $payment = new Payment(
            amount: $amount,
            paidAt: $paidAt,
            method: PaymentMethod::CREDIT_CARD,
        );

        $returnedAmount = $payment->getAmount();

        $this->assertInstanceOf(Money::class, $returnedAmount);
        $this->assertTrue($returnedAmount->equals($amount));
    }

    public function testSetAmountWithMoneyObject(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CHECK,
        );

        $newAmount = Money::fromEuros('150.00');
        $payment->setAmount($newAmount);

        $this->assertTrue($payment->getAmount()->equals($newAmount));
    }

    // ========== Payment Method Enum ==========

    public function testPaymentMethodBankTransfer(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame(PaymentMethod::BANK_TRANSFER, $payment->getMethod());
    }

    public function testPaymentMethodCreditCard(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CREDIT_CARD,
        );

        $this->assertSame(PaymentMethod::CREDIT_CARD, $payment->getMethod());
    }

    public function testPaymentMethodCheck(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CHECK,
        );

        $this->assertSame(PaymentMethod::CHECK, $payment->getMethod());
    }

    public function testPaymentMethodCash(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CASH,
        );

        $this->assertSame(PaymentMethod::CASH, $payment->getMethod());
    }

    public function testPaymentMethodDirectDebit(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::DIRECT_DEBIT,
        );

        $this->assertSame(PaymentMethod::DIRECT_DEBIT, $payment->getMethod());
    }

    public function testPaymentMethodOther(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::OTHER,
        );

        $this->assertSame(PaymentMethod::OTHER, $payment->getMethod());
    }

    public function testPaymentMethodCanBeUpdated(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CHECK,
        );

        $payment->setMethod(PaymentMethod::BANK_TRANSFER);

        $this->assertSame(PaymentMethod::BANK_TRANSFER, $payment->getMethod());
    }

    // ========== Optional Fields: Reference ==========

    public function testReferenceIsNullByDefault(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertNull($payment->getReference());
    }

    public function testReferenceCanBeSet(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $payment->setReference('TXN-123456789');

        $this->assertSame('TXN-123456789', $payment->getReference());
    }

    public function testReferenceCanBeRemoved(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $payment->setReference('TXN-123456789');
        $this->assertNotNull($payment->getReference());

        $payment->setReference(null);
        $this->assertNull($payment->getReference());
    }

    // ========== Optional Fields: Notes ==========

    public function testNotesIsNullByDefault(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CASH,
        );

        $this->assertNull($payment->getNotes());
    }

    public function testNotesCanBeSet(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CHECK,
        );

        $payment->setNotes('Chèque n°12345, Banque Populaire');

        $this->assertSame('Chèque n°12345, Banque Populaire', $payment->getNotes());
    }

    public function testNotesCanBeRemoved(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CHECK,
        );

        $payment->setNotes('Note importante');
        $this->assertNotNull($payment->getNotes());

        $payment->setNotes(null);
        $this->assertNull($payment->getNotes());
    }

    public function testNotesCanBeLong(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::OTHER,
        );

        $longNote = str_repeat('Paiement reçu. ', 50); // 750+ caractères
        $payment->setNotes($longNote);

        $this->assertSame($longNote, $payment->getNotes());
    }

    // ========== Date Management ==========

    public function testPaidAtIsImmutable(): void
    {
        $originalDate = new \DateTimeImmutable('2024-06-15 10:30:00');

        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: $originalDate,
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame($originalDate, $payment->getPaidAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $payment->getPaidAt());
    }

    public function testPaidAtCanBeUpdated(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable('2024-06-15'),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $newDate = new \DateTimeImmutable('2024-06-20');
        $payment->setPaidAt($newDate);

        $this->assertSame($newDate, $payment->getPaidAt());
    }

    public function testPaidAtWithSpecificTime(): void
    {
        $dateTime = new \DateTimeImmutable('2024-06-15 14:30:45');

        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: $dateTime,
            method: PaymentMethod::CREDIT_CARD,
        );

        $this->assertSame('2024-06-15 14:30:45', $payment->getPaidAt()->format('Y-m-d H:i:s'));
    }

    // ========== ID Management ==========

    public function testIdIsNullBeforePersistence(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('100.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertNull($payment->getId());
    }

    // ========== Amount Edge Cases ==========

    public function testPaymentWithZeroAmount(): void
    {
        $payment = new Payment(
            amount: Money::zero(),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::OTHER,
        );

        $this->assertTrue($payment->getAmount()->isZero());
        $this->assertSame('0.00', $payment->getAmount()->toEuros());
    }

    public function testPaymentWithNegativeAmount(): void
    {
        // Montant négatif possible pour remboursements
        $payment = new Payment(
            amount: Money::fromEuros('-50.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertTrue($payment->getAmount()->isNegative());
        $this->assertSame('-50.00', $payment->getAmount()->toEuros());
    }

    public function testPaymentWithLargeAmount(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('1000000.00'), // 1 million
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $this->assertSame('1000000.00', $payment->getAmount()->toEuros());
    }

    public function testPaymentWithCents(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('123.45'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CREDIT_CARD,
        );

        $this->assertSame('123.45', $payment->getAmount()->toEuros());
        $this->assertSame(12345, $payment->getAmount()->getAmount());
    }

    // ========== Money Immutability ==========

    public function testAmountDoesNotModifyOriginalMoney(): void
    {
        $originalAmount = Money::fromEuros('100.00');

        $payment = new Payment(
            amount: $originalAmount,
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $returnedAmount = $payment->getAmount();

        // L'objet Money original ne doit pas avoir changé
        $this->assertSame('100.00', $originalAmount->toEuros());
        $this->assertSame('100.00', $returnedAmount->toEuros());

        // Tester modification via setter
        $payment->setAmount(Money::fromEuros('200.00'));
        $this->assertSame('100.00', $originalAmount->toEuros()); // Original intact
        $this->assertSame('200.00', $payment->getAmount()->toEuros()); // Payment modifié
    }

    // ========== Real-World Scenarios ==========

    public function testCreditCardPaymentWithReference(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('99.99'),
            paidAt: new \DateTimeImmutable('2024-06-15 16:45:12'),
            method: PaymentMethod::CREDIT_CARD,
        );

        $payment->setReference('STRIPE-ch_3NqXYZ123456789');
        $payment->setNotes('Paiement en ligne via Stripe');

        $this->assertSame(PaymentMethod::CREDIT_CARD, $payment->getMethod());
        $this->assertSame('99.99', $payment->getAmount()->toEuros());
        $this->assertSame('STRIPE-ch_3NqXYZ123456789', $payment->getReference());
        $this->assertNotNull($payment->getNotes());
        $this->assertStringContainsString('Stripe', $payment->getNotes());
    }

    public function testBankTransferWithDetails(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('5000.00'),
            paidAt: new \DateTimeImmutable('2024-06-20'),
            method: PaymentMethod::BANK_TRANSFER,
        );

        $payment->setReference('VIREMENT-20240620-001');
        $payment->setNotes('Virement SEPA - Référence interne: FAC-2024-0042');

        $this->assertSame('5000.00', $payment->getAmount()->toEuros());
        $this->assertNotNull($payment->getNotes());
        $this->assertStringContainsString('SEPA', $payment->getNotes());
    }

    public function testCheckPaymentWithCheckNumber(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('1500.00'),
            paidAt: new \DateTimeImmutable('2024-06-18'),
            method: PaymentMethod::CHECK,
        );

        $payment->setReference('CHQ-7654321');
        $payment->setNotes('Chèque n°7654321, BNP Paribas, émis le 18/06/2024');

        $this->assertSame(PaymentMethod::CHECK, $payment->getMethod());
        $this->assertNotNull($payment->getReference());
        $this->assertStringContainsString('7654321', $payment->getReference());
    }

    public function testCashPaymentSimple(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('50.00'),
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::CASH,
        );

        // Espèces rarement avec référence
        $this->assertNull($payment->getReference());
        $this->assertNull($payment->getNotes());
    }

    public function testDirectDebitWithMandate(): void
    {
        $payment = new Payment(
            amount: Money::fromEuros('299.00'),
            paidAt: new \DateTimeImmutable('2024-06-25'),
            method: PaymentMethod::DIRECT_DEBIT,
        );

        $payment->setReference('MANDATE-FR12345678');
        $payment->setNotes('Prélèvement SEPA - Mandat n°FR12345678');

        $this->assertSame(PaymentMethod::DIRECT_DEBIT, $payment->getMethod());
        $this->assertNotNull($payment->getNotes());
        $this->assertStringContainsString('SEPA', $payment->getNotes());
    }
}
