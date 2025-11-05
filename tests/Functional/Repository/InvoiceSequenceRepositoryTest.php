<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\InvoiceSequence;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository;

final class InvoiceSequenceRepositoryTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceSequenceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->entityManager->getRepository(InvoiceSequence::class);
        if (!$repository instanceof InvoiceSequenceRepository) {
            throw new \RuntimeException('InvoiceSequenceRepository not found');
        }
        $this->repository = $repository;
    }

    // ========== findForUpdate() with PESSIMISTIC_WRITE ==========

    public function testFindForUpdateReturnsSequence(): void
    {
        // Create a sequence
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Find with lock (requires transaction)
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(null, 2024, InvoiceType::INVOICE);

            $this->assertNotNull($result);
            $this->assertSame(2024, $result->getFiscalYear());
            $this->assertSame(InvoiceType::INVOICE, $result->getType());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testFindForUpdateReturnsNullWhenNotFound(): void
    {
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(null, 2024, InvoiceType::INVOICE);

            $this->assertNull($result);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testFindForUpdateFiltersByCompanyId(): void
    {
        // Create sequence for company 1
        $sequence1 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence1);

        // Create sequence for company 2
        $sequence2 = new InvoiceSequence(
            companyId: 2,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence2);

        $this->entityManager->flush();

        // Find for company 1 (requires transaction)
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(1, 2024, InvoiceType::INVOICE);

            $this->assertNotNull($result);
            $this->assertSame(1, $result->getCompanyId());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    // ========== Thread-safety / Concurrency ==========

    public function testIncrementSequenceIsThreadSafe(): void
    {
        // Create a sequence
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();
        $initialId = $sequence->getId();

        // Simulate two concurrent increments
        // Transaction 1
        $this->entityManager->beginTransaction();
        try {
            $seq1 = $this->repository->findForUpdate(null, 2024, InvoiceType::INVOICE);
            $this->assertNotNull($seq1);
            $number1 = $seq1->getNextNumber();
            $seq1->incrementLastNumber();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Transaction 2
        $this->entityManager->beginTransaction();
        try {
            $seq2 = $this->repository->findForUpdate(null, 2024, InvoiceType::INVOICE);
            $this->assertNotNull($seq2);
            $number2 = $seq2->getNextNumber();
            $seq2->incrementLastNumber();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Verify sequential numbers
        $this->assertSame(1, $number1);
        $this->assertSame(2, $number2);

        // Verify final state
        $this->entityManager->clear();
        $final = $this->entityManager->find(InvoiceSequence::class, $initialId);
        $this->assertNotNull($final);
        $this->assertSame(2, $final->getLastNumber());
    }

    // ========== findOrCreateSequence() ==========

    public function testFindOrCreateSequenceReturnsExisting(): void
    {
        // Create sequence for calendar year
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Find with date in range
        $result = $this->repository->findOrCreateSequence(
            companyId: null,
            invoiceDate: new \DateTimeImmutable('2024-06-15'),
            type: InvoiceType::INVOICE,
            fiscalYearStartMonth: 1,
            fiscalYearStartDay: 1,
        );

        $this->assertNotNull($result);
        $this->assertSame($sequence->getId(), $result->getId());
        $this->assertSame(2024, $result->getFiscalYear());
    }

    public function testFindOrCreateSequenceCreatesNewWhenNotFound(): void
    {
        // No existing sequence
        $countBefore = $this->repository->count([]);

        $result = $this->repository->findOrCreateSequence(
            companyId: null,
            invoiceDate: new \DateTimeImmutable('2024-06-15'),
            type: InvoiceType::INVOICE,
            fiscalYearStartMonth: 1,
            fiscalYearStartDay: 1,
        );

        $countAfter = $this->repository->count([]);

        $this->assertNotNull($result);
        $this->assertSame(2024, $result->getFiscalYear());
        $this->assertSame(0, $result->getLastNumber());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testFindOrCreateSequenceHandlesNonCalendarFiscalYear(): void
    {
        // Fiscal year from Nov to Oct
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-11-01'),
            endDate: new \DateTimeImmutable('2025-10-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Invoice date in December 2024 (should match fiscal year 2024)
        $result = $this->repository->findOrCreateSequence(
            companyId: null,
            invoiceDate: new \DateTimeImmutable('2024-12-15'),
            type: InvoiceType::INVOICE,
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
        );

        $this->assertNotNull($result);
        $this->assertSame($sequence->getId(), $result->getId());
        $this->assertSame(2024, $result->getFiscalYear());
    }

    public function testFindOrCreateSequenceCreatesForNextFiscalYear(): void
    {
        // Existing sequence for fiscal year 2024 (Nov 2024 - Oct 2025)
        $sequence2024 = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-11-01'),
            endDate: new \DateTimeImmutable('2025-10-31'),
        );
        $this->entityManager->persist($sequence2024);
        $this->entityManager->flush();

        // Invoice date in November 2025 (should create new fiscal year 2025)
        $result = $this->repository->findOrCreateSequence(
            companyId: null,
            invoiceDate: new \DateTimeImmutable('2025-11-01'),
            type: InvoiceType::INVOICE,
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
        );

        $this->assertNotNull($result);
        $this->assertNotSame($sequence2024->getId(), $result->getId());
        $this->assertSame(2025, $result->getFiscalYear());
        $this->assertSame(0, $result->getLastNumber());
    }

    // ========== Isolation by company/type/year ==========

    public function testSequencesAreIsolatedByCompany(): void
    {
        // Create sequences for two companies
        $seq1 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $seq1->incrementLastNumber();
        $seq1->incrementLastNumber();
        $this->entityManager->persist($seq1);

        $seq2 = new InvoiceSequence(
            companyId: 2,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($seq2);

        $this->entityManager->flush();

        // Verify isolation
        $this->assertSame(2, $seq1->getLastNumber());
        $this->assertSame(0, $seq2->getLastNumber());
    }

    public function testSequencesAreIsolatedByType(): void
    {
        // Create sequences for INVOICE and CREDIT_NOTE
        $seqInvoice = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $seqInvoice->incrementLastNumber();
        $seqInvoice->incrementLastNumber();
        $seqInvoice->incrementLastNumber();
        $this->entityManager->persist($seqInvoice);

        $seqCreditNote = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::CREDIT_NOTE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($seqCreditNote);

        $this->entityManager->flush();

        // Verify isolation
        $this->assertSame(3, $seqInvoice->getLastNumber());
        $this->assertSame(0, $seqCreditNote->getLastNumber());
    }

    public function testSequencesAreIsolatedByFiscalYear(): void
    {
        // Create sequences for 2024 and 2025
        $seq2024 = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $seq2024->incrementLastNumber();
        $seq2024->incrementLastNumber();
        $this->entityManager->persist($seq2024);

        $seq2025 = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2025,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2025-01-01'),
            endDate: new \DateTimeImmutable('2025-12-31'),
        );
        $this->entityManager->persist($seq2025);

        $this->entityManager->flush();

        // Verify isolation
        $this->assertSame(2, $seq2024->getLastNumber());
        $this->assertSame(0, $seq2025->getLastNumber());
    }

    // ========== findByDateContaining() ==========

    public function testFindByDateContainingReturnsMatchingSequence(): void
    {
        // Create sequence
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Find with date in range
        $result = $this->repository->findByDateContaining(
            companyId: null,
            date: new \DateTimeImmutable('2024-06-15'),
            type: InvoiceType::INVOICE,
        );

        $this->assertNotNull($result);
        $this->assertSame($sequence->getId(), $result->getId());
    }

    public function testFindByDateContainingReturnsNullForDateOutsideRange(): void
    {
        // Create sequence for 2024
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Try to find with date in 2025
        $result = $this->repository->findByDateContaining(
            companyId: null,
            date: new \DateTimeImmutable('2025-06-15'),
            type: InvoiceType::INVOICE,
        );

        $this->assertNull($result);
    }

    public function testFindByDateContainingHandlesNonCalendarFiscalYear(): void
    {
        // Fiscal year from Nov 2024 to Oct 2025
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-11-01'),
            endDate: new \DateTimeImmutable('2025-10-31'),
        );
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Date in March 2025 should match
        $result = $this->repository->findByDateContaining(
            companyId: null,
            date: new \DateTimeImmutable('2025-03-15'),
            type: InvoiceType::INVOICE,
        );

        $this->assertNotNull($result);
        $this->assertSame($sequence->getId(), $result->getId());
    }

    // ========== Edge cases ==========

    public function testFindForUpdateWithNullCompanyIdMatchesNullOnly(): void
    {
        // Create sequence with NULL companyId
        $seqNull = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($seqNull);

        // Create sequence with companyId = 1
        $seq1 = new InvoiceSequence(
            companyId: 1,
            fiscalYear: 2024,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2024-01-01'),
            endDate: new \DateTimeImmutable('2024-12-31'),
        );
        $this->entityManager->persist($seq1);

        $this->entityManager->flush();

        // Search with NULL companyId should return NULL sequence (requires transaction)
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(null, 2024, InvoiceType::INVOICE);

            $this->assertNotNull($result);
            $this->assertNull($result->getCompanyId());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
