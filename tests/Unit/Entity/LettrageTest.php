<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\Entity\Lettrage;
use CorentinBoutillier\InvoiceBundle\Entity\LettrageEntry;
use CorentinBoutillier\InvoiceBundle\Enum\LettrageEntryType;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour l'entité Lettrage.
 *
 * Le lettrage est le rapprochement entre des factures (débit) et des paiements (crédit).
 * Un lettrage est équilibré quand la somme des débits égale la somme des crédits.
 */
final class LettrageTest extends TestCase
{
    // ========== Construction ==========

    public function testConstructWithRequiredParameters(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');
        $lettrage = new Lettrage(
            code: 'A',
            date: $date,
            companyId: 123,
        );

        $this->assertSame('A', $lettrage->getCode());
        $this->assertSame($date, $lettrage->getDate());
        $this->assertSame(123, $lettrage->getCompanyId());
    }

    public function testConstructWithNullCompanyId(): void
    {
        $lettrage = new Lettrage(
            code: 'B',
            date: new \DateTimeImmutable(),
            companyId: null,
        );

        $this->assertNull($lettrage->getCompanyId());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $lettrage = new Lettrage(
            code: 'C',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $this->assertNull($lettrage->getId());
    }

    public function testEntriesCollectionIsEmptyOnConstruction(): void
    {
        $lettrage = new Lettrage(
            code: 'D',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $this->assertCount(0, $lettrage->getEntries());
    }

    // ========== Gestion des entrées ==========

    public function testAddEntry(): void
    {
        $lettrage = new Lettrage(
            code: 'E',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $entry = $this->createMock(LettrageEntry::class);
        $lettrage->addEntry($entry);

        $this->assertCount(1, $lettrage->getEntries());
        $this->assertTrue($lettrage->getEntries()->contains($entry));
    }

    public function testAddMultipleEntries(): void
    {
        $lettrage = new Lettrage(
            code: 'F',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $entry1 = $this->createMock(LettrageEntry::class);
        $entry2 = $this->createMock(LettrageEntry::class);

        $lettrage->addEntry($entry1);
        $lettrage->addEntry($entry2);

        $this->assertCount(2, $lettrage->getEntries());
    }

    public function testAddSameEntryTwiceDoesNotDuplicate(): void
    {
        $lettrage = new Lettrage(
            code: 'G',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $entry = $this->createMock(LettrageEntry::class);
        $lettrage->addEntry($entry);
        $lettrage->addEntry($entry);

        $this->assertCount(1, $lettrage->getEntries());
    }

    public function testRemoveEntry(): void
    {
        $lettrage = new Lettrage(
            code: 'H',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $entry = $this->createMock(LettrageEntry::class);
        $lettrage->addEntry($entry);
        $lettrage->removeEntry($entry);

        $this->assertCount(0, $lettrage->getEntries());
    }

    // ========== Équilibre du lettrage ==========

    public function testIsBalancedWithNoEntries(): void
    {
        $lettrage = new Lettrage(
            code: 'I',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        // Un lettrage sans entrée est considéré comme équilibré (0 = 0)
        $this->assertTrue($lettrage->isBalanced());
    }

    public function testIsBalancedWithEqualDebitAndCredit(): void
    {
        $lettrage = new Lettrage(
            code: 'J',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        // Facture de 100€ au débit
        $debitEntry = $this->createMock(LettrageEntry::class);
        $debitEntry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debitEntry->method('getAmountCents')->willReturn(10000);

        // Paiement de 100€ au crédit
        $creditEntry = $this->createMock(LettrageEntry::class);
        $creditEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $creditEntry->method('getAmountCents')->willReturn(10000);

        $lettrage->addEntry($debitEntry);
        $lettrage->addEntry($creditEntry);

        $this->assertTrue($lettrage->isBalanced());
    }

    public function testIsNotBalancedWhenDebitGreaterThanCredit(): void
    {
        $lettrage = new Lettrage(
            code: 'K',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        // Facture de 150€ au débit
        $debitEntry = $this->createMock(LettrageEntry::class);
        $debitEntry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debitEntry->method('getAmountCents')->willReturn(15000);

        // Paiement de 100€ au crédit
        $creditEntry = $this->createMock(LettrageEntry::class);
        $creditEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $creditEntry->method('getAmountCents')->willReturn(10000);

        $lettrage->addEntry($debitEntry);
        $lettrage->addEntry($creditEntry);

        $this->assertFalse($lettrage->isBalanced());
    }

    public function testIsNotBalancedWhenCreditGreaterThanDebit(): void
    {
        $lettrage = new Lettrage(
            code: 'L',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        // Facture de 100€ au débit
        $debitEntry = $this->createMock(LettrageEntry::class);
        $debitEntry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debitEntry->method('getAmountCents')->willReturn(10000);

        // Paiement de 150€ au crédit
        $creditEntry = $this->createMock(LettrageEntry::class);
        $creditEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $creditEntry->method('getAmountCents')->willReturn(15000);

        $lettrage->addEntry($debitEntry);
        $lettrage->addEntry($creditEntry);

        $this->assertFalse($lettrage->isBalanced());
    }

    public function testIsBalancedWithMultipleEntriesOnEachSide(): void
    {
        $lettrage = new Lettrage(
            code: 'M',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        // Deux factures : 60€ + 40€ = 100€
        $debit1 = $this->createMock(LettrageEntry::class);
        $debit1->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debit1->method('getAmountCents')->willReturn(6000);

        $debit2 = $this->createMock(LettrageEntry::class);
        $debit2->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debit2->method('getAmountCents')->willReturn(4000);

        // Deux paiements : 75€ + 25€ = 100€
        $credit1 = $this->createMock(LettrageEntry::class);
        $credit1->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $credit1->method('getAmountCents')->willReturn(7500);

        $credit2 = $this->createMock(LettrageEntry::class);
        $credit2->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $credit2->method('getAmountCents')->willReturn(2500);

        $lettrage->addEntry($debit1);
        $lettrage->addEntry($debit2);
        $lettrage->addEntry($credit1);
        $lettrage->addEntry($credit2);

        $this->assertTrue($lettrage->isBalanced());
    }

    // ========== Soft delete ==========

    public function testIsNotDeletedByDefault(): void
    {
        $lettrage = new Lettrage(
            code: 'N',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $this->assertFalse($lettrage->isDeleted());
        $this->assertNull($lettrage->getDeletedAt());
        $this->assertNull($lettrage->getDeletionReason());
    }

    public function testSoftDelete(): void
    {
        $lettrage = new Lettrage(
            code: 'O',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $deletedAt = new \DateTimeImmutable('2025-06-20 10:30:00');
        $reason = 'Annulation suite à erreur de saisie';

        $lettrage->softDelete($deletedAt, $reason);

        $this->assertTrue($lettrage->isDeleted());
        $this->assertSame($deletedAt, $lettrage->getDeletedAt());
        $this->assertSame($reason, $lettrage->getDeletionReason());
    }

    public function testSoftDeleteWithNullReason(): void
    {
        $lettrage = new Lettrage(
            code: 'P',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $deletedAt = new \DateTimeImmutable();
        $lettrage->softDelete($deletedAt, null);

        $this->assertTrue($lettrage->isDeleted());
        $this->assertNull($lettrage->getDeletionReason());
    }

    // ========== Calcul des totaux ==========

    public function testGetTotalDebitCents(): void
    {
        $lettrage = new Lettrage(
            code: 'Q',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $debit1 = $this->createMock(LettrageEntry::class);
        $debit1->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debit1->method('getAmountCents')->willReturn(5000);

        $debit2 = $this->createMock(LettrageEntry::class);
        $debit2->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debit2->method('getAmountCents')->willReturn(3000);

        $credit = $this->createMock(LettrageEntry::class);
        $credit->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $credit->method('getAmountCents')->willReturn(8000);

        $lettrage->addEntry($debit1);
        $lettrage->addEntry($debit2);
        $lettrage->addEntry($credit);

        $this->assertSame(8000, $lettrage->getTotalDebitCents());
    }

    public function testGetTotalCreditCents(): void
    {
        $lettrage = new Lettrage(
            code: 'R',
            date: new \DateTimeImmutable(),
            companyId: 1,
        );

        $debit = $this->createMock(LettrageEntry::class);
        $debit->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $debit->method('getAmountCents')->willReturn(10000);

        $credit1 = $this->createMock(LettrageEntry::class);
        $credit1->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $credit1->method('getAmountCents')->willReturn(6000);

        $credit2 = $this->createMock(LettrageEntry::class);
        $credit2->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $credit2->method('getAmountCents')->willReturn(4000);

        $lettrage->addEntry($debit);
        $lettrage->addEntry($credit1);
        $lettrage->addEntry($credit2);

        $this->assertSame(10000, $lettrage->getTotalCreditCents());
    }

    // ========== Scénarios réels ==========

    public function testTypicalInvoicePaymentLettrage(): void
    {
        // Scénario : Une facture de 120€ TTC payée intégralement
        $lettrage = new Lettrage(
            code: 'AA',
            date: new \DateTimeImmutable('2025-06-15'),
            companyId: 42,
        );

        $invoiceEntry = $this->createMock(LettrageEntry::class);
        $invoiceEntry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $invoiceEntry->method('getAmountCents')->willReturn(12000);

        $paymentEntry = $this->createMock(LettrageEntry::class);
        $paymentEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $paymentEntry->method('getAmountCents')->willReturn(12000);

        $lettrage->addEntry($invoiceEntry);
        $lettrage->addEntry($paymentEntry);

        $this->assertSame('AA', $lettrage->getCode());
        $this->assertTrue($lettrage->isBalanced());
        $this->assertFalse($lettrage->isDeleted());
    }

    public function testPartialPaymentLettrage(): void
    {
        // Scénario : Lettrage partiel - facture de 200€ avec paiement de 120€
        $lettrage = new Lettrage(
            code: 'AB',
            date: new \DateTimeImmutable('2025-06-15'),
            companyId: 42,
        );

        // Le montant lettré est uniquement la partie payée (120€), pas le total facture
        $invoiceEntry = $this->createMock(LettrageEntry::class);
        $invoiceEntry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $invoiceEntry->method('getAmountCents')->willReturn(12000); // Montant lettré

        $paymentEntry = $this->createMock(LettrageEntry::class);
        $paymentEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $paymentEntry->method('getAmountCents')->willReturn(12000);

        $lettrage->addEntry($invoiceEntry);
        $lettrage->addEntry($paymentEntry);

        $this->assertTrue($lettrage->isBalanced());
    }

    public function testMultipleInvoicesOnePaymentLettrage(): void
    {
        // Scénario : Deux factures (50€ + 70€) payées par un seul virement de 120€
        $lettrage = new Lettrage(
            code: 'AC',
            date: new \DateTimeImmutable('2025-06-15'),
            companyId: 42,
        );

        $invoice1Entry = $this->createMock(LettrageEntry::class);
        $invoice1Entry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $invoice1Entry->method('getAmountCents')->willReturn(5000);

        $invoice2Entry = $this->createMock(LettrageEntry::class);
        $invoice2Entry->method('getType')->willReturn(LettrageEntryType::DEBIT);
        $invoice2Entry->method('getAmountCents')->willReturn(7000);

        $paymentEntry = $this->createMock(LettrageEntry::class);
        $paymentEntry->method('getType')->willReturn(LettrageEntryType::CREDIT);
        $paymentEntry->method('getAmountCents')->willReturn(12000);

        $lettrage->addEntry($invoice1Entry);
        $lettrage->addEntry($invoice2Entry);
        $lettrage->addEntry($paymentEntry);

        $this->assertTrue($lettrage->isBalanced());
        $this->assertSame(12000, $lettrage->getTotalDebitCents());
        $this->assertSame(12000, $lettrage->getTotalCreditCents());
    }
}
