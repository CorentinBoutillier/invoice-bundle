<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\Entity\InvoiceSequence;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use PHPUnit\Framework\TestCase;

final class InvoiceSequenceTest extends TestCase
{
    // ========== Construction & Basic Properties ==========

    public function testConstructWithAllProperties(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertInstanceOf(InvoiceSequence::class, $sequence);
        $this->assertSame(1, $sequence->getCompanyId());
        $this->assertSame(2024, $sequence->getFiscalYear());
        $this->assertSame(InvoiceType::INVOICE, $sequence->getType());
        $this->assertSame($startDate, $sequence->getStartDate());
        $this->assertSame($endDate, $sequence->getEndDate());
        $this->assertSame(0, $sequence->getLastNumber());
    }

    public function testConstructWithNullCompanyId(): void
    {
        // Pour configuration mono-société
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertNull($sequence->getCompanyId());
    }

    public function testConstructWithCreditNoteType(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::CREDIT_NOTE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertSame(InvoiceType::CREDIT_NOTE, $sequence->getType());
    }

    public function testLastNumberStartsAtZero(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertSame(0, $sequence->getLastNumber());
    }

    // ========== Increment Logic ==========

    public function testGetNextNumberReturnsLastNumberPlusOne(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Initial state: lastNumber = 0
        $this->assertSame(1, $sequence->getNextNumber());

        // Après un incrément
        $sequence->incrementLastNumber();
        $this->assertSame(2, $sequence->getNextNumber());

        // Après plusieurs incréments
        $sequence->incrementLastNumber();
        $sequence->incrementLastNumber();
        $this->assertSame(4, $sequence->getNextNumber());
    }

    public function testIncrementLastNumberUpdatesLastNumber(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertSame(0, $sequence->getLastNumber());

        $sequence->incrementLastNumber();
        $this->assertSame(1, $sequence->getLastNumber());

        $sequence->incrementLastNumber();
        $this->assertSame(2, $sequence->getLastNumber());

        $sequence->incrementLastNumber();
        $this->assertSame(3, $sequence->getLastNumber());
    }

    public function testIncrementFromHighNumber(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Simuler une séquence déjà avancée (ex: reprise migration)
        // On va devoir incrémenter plusieurs fois pour tester
        for ($i = 0; $i < 999; ++$i) {
            $sequence->incrementLastNumber();
        }

        $this->assertSame(999, $sequence->getLastNumber());
        $this->assertSame(1000, $sequence->getNextNumber());

        $sequence->incrementLastNumber();
        $this->assertSame(1000, $sequence->getLastNumber());
    }

    // ========== Fiscal Year Period Validation ==========

    public function testFiscalYearPeriodStandardYear(): void
    {
        // Année civile standard (1er janv → 31 déc)
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertSame($startDate, $sequence->getStartDate());
        $this->assertSame($endDate, $sequence->getEndDate());
        $this->assertTrue($sequence->getStartDate() < $sequence->getEndDate());
    }

    public function testFiscalYearPeriodNonStandardYear(): void
    {
        // Exercice fiscal décalé (ex: 1er nov 2023 → 31 oct 2024, mais fiscalYear = 2024)
        $startDate = new \DateTimeImmutable('2023-11-01');
        $endDate = new \DateTimeImmutable('2024-10-31');

        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertSame(2024, $sequence->getFiscalYear());
        $this->assertSame($startDate, $sequence->getStartDate());
        $this->assertSame($endDate, $sequence->getEndDate());
        $this->assertTrue($sequence->getStartDate() < $sequence->getEndDate());
    }

    public function testFiscalYearPeriodShortYear(): void
    {
        // Exercice court (5 mois, ex: création société en cours d'année)
        $startDate = new \DateTimeImmutable('2024-08-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertSame($startDate, $sequence->getStartDate());
        $this->assertSame($endDate, $sequence->getEndDate());

        $interval = $startDate->diff($endDate);
        $this->assertLessThan(12, $interval->m + ($interval->y * 12));
    }

    public function testFiscalYearPeriodLongYear(): void
    {
        // Exercice long (18 mois, rare mais légal)
        $startDate = new \DateTimeImmutable('2023-07-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertSame($startDate, $sequence->getStartDate());
        $this->assertSame($endDate, $sequence->getEndDate());
        $this->assertTrue($sequence->getStartDate() < $sequence->getEndDate());
    }

    public function testContainsDate(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Dates dans la période
        $this->assertTrue($sequence->containsDate(new \DateTimeImmutable('2024-01-01')));
        $this->assertTrue($sequence->containsDate(new \DateTimeImmutable('2024-06-15')));
        $this->assertTrue($sequence->containsDate(new \DateTimeImmutable('2024-12-31')));

        // Dates hors période
        $this->assertFalse($sequence->containsDate(new \DateTimeImmutable('2023-12-31')));
        $this->assertFalse($sequence->containsDate(new \DateTimeImmutable('2025-01-01')));
    }

    // ========== Multi-Company & Multi-Type Scenarios ==========

    public function testSeparateSequencesForDifferentCompanies(): void
    {
        // Société 1
        $sequence1 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Société 2
        $sequence2 = new InvoiceSequence(
            companyId: 2,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Chaque société a sa propre séquence
        $this->assertNotSame($sequence1->getCompanyId(), $sequence2->getCompanyId());
        $this->assertSame(0, $sequence1->getLastNumber());
        $this->assertSame(0, $sequence2->getLastNumber());

        // Incrémenter séquence 1
        $sequence1->incrementLastNumber();
        $sequence1->incrementLastNumber();

        // Séquence 2 reste à 0
        $this->assertSame(2, $sequence1->getLastNumber());
        $this->assertSame(0, $sequence2->getLastNumber());
    }

    public function testSeparateSequencesForDifferentTypes(): void
    {
        // Factures
        $invoiceSequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Avoirs
        $creditNoteSequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::CREDIT_NOTE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Même companyId et fiscalYear, mais types différents = séquences séparées
        $this->assertSame($invoiceSequence->getCompanyId(), $creditNoteSequence->getCompanyId());
        $this->assertSame($invoiceSequence->getFiscalYear(), $creditNoteSequence->getFiscalYear());
        $this->assertNotSame($invoiceSequence->getType(), $creditNoteSequence->getType());

        // Incrémenter factures
        $invoiceSequence->incrementLastNumber();
        $invoiceSequence->incrementLastNumber();
        $invoiceSequence->incrementLastNumber();

        // Incrémenter avoirs
        $creditNoteSequence->incrementLastNumber();

        // Séquences indépendantes
        $this->assertSame(3, $invoiceSequence->getLastNumber());
        $this->assertSame(1, $creditNoteSequence->getLastNumber());
    }

    public function testSeparateSequencesForDifferentFiscalYears(): void
    {
        // Exercice 2023
        $sequence2023 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2023,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-12-31'),
        );

        // Exercice 2024
        $sequence2024 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // Même companyId et type, mais années différentes = séquences séparées
        $this->assertSame($sequence2023->getCompanyId(), $sequence2024->getCompanyId());
        $this->assertSame($sequence2023->getType(), $sequence2024->getType());
        $this->assertNotSame($sequence2023->getFiscalYear(), $sequence2024->getFiscalYear());

        // Exercice 2023 arrive à 542
        for ($i = 0; $i < 542; ++$i) {
            $sequence2023->incrementLastNumber();
        }

        // Exercice 2024 commence à 0
        $this->assertSame(542, $sequence2023->getLastNumber());
        $this->assertSame(0, $sequence2024->getLastNumber());
        $this->assertSame(1, $sequence2024->getNextNumber());
    }

    // ========== Edge Cases ==========

    public function testMonoCompanyConfiguration(): void
    {
        // Configuration mono-société: companyId = NULL
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertNull($sequence->getCompanyId());
        $this->assertSame(0, $sequence->getLastNumber());
        $this->assertSame(1, $sequence->getNextNumber());

        $sequence->incrementLastNumber();
        $this->assertSame(1, $sequence->getLastNumber());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $sequence = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );

        // L'ID est null avant persistence Doctrine
        $this->assertNull($sequence->getId());
    }
}
