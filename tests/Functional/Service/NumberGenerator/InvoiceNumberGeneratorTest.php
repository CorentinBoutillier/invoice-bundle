<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service\NumberGenerator;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceSequence;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository;
use CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;

final class InvoiceNumberGeneratorTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceNumberGeneratorInterface $generator;

    /** @phpstan-ignore property.uninitialized */
    private InvoiceSequenceRepository $sequenceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->entityManager->getRepository(InvoiceSequence::class);
        if (!$repository instanceof InvoiceSequenceRepository) {
            throw new \RuntimeException('InvoiceSequenceRepository not found');
        }
        $this->sequenceRepository = $repository;

        // The generator will be instantiated once the class exists
        $container = $this->kernel->getContainer();
        $generator = $container->get(InvoiceNumberGeneratorInterface::class);
        if (!$generator instanceof InvoiceNumberGeneratorInterface) {
            throw new \RuntimeException('InvoiceNumberGeneratorInterface not found');
        }
        $this->generator = $generator;
    }

    // ========== Format Tests ==========

    public function testGenerateInvoiceNumberWithDefaultFormat(): void
    {
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');
        $company = $this->createCompanyData();

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);

            $this->assertSame('FA-2025-0001', $number);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testGenerateCreditNoteNumberWithDefaultFormat(): void
    {
        $invoice = $this->createInvoice(InvoiceType::CREDIT_NOTE, '2025-05-15');
        $company = $this->createCompanyData();

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);

            $this->assertSame('AV-2025-0001', $number);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testSequencePaddingWith4Digits(): void
    {
        $company = $this->createCompanyData();

        // Create sequence with different lastNumber values
        $testCases = [
            ['lastNumber' => 0, 'expected' => 'FA-2025-0001'],
            ['lastNumber' => 41, 'expected' => 'FA-2025-0042'],
            ['lastNumber' => 122, 'expected' => 'FA-2025-0123'],
            ['lastNumber' => 1233, 'expected' => 'FA-2025-1234'],
            ['lastNumber' => 9999, 'expected' => 'FA-2025-10000'], // No padding beyond 4 digits
        ];

        foreach ($testCases as $testCase) {
            $sequence = new InvoiceSequence(
                companyId: null,
                fiscalYear: 2025,
                type: InvoiceType::INVOICE,
                startDate: new \DateTimeImmutable('2025-01-01'),
                endDate: new \DateTimeImmutable('2025-12-31'),
            );
            $sequence->setLastNumber($testCase['lastNumber']);
            $this->entityManager->persist($sequence);
            $this->entityManager->flush();

            $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $this->assertSame($testCase['expected'], $number);
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

            // Clean up for next iteration
            $this->entityManager->remove($sequence);
            $this->entityManager->flush();
        }
    }

    public function testSequenceIncrementsCorrectly(): void
    {
        $company = $this->createCompanyData();

        $invoice1 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');
        $invoice2 = $this->createInvoice(InvoiceType::INVOICE, '2025-06-10');
        $invoice3 = $this->createInvoice(InvoiceType::INVOICE, '2025-07-20');

        $this->entityManager->beginTransaction();
        try {
            $number1 = $this->generator->generate($invoice1, $company);
            $this->assertSame('FA-2025-0001', $number1);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $this->entityManager->beginTransaction();
        try {
            $number2 = $this->generator->generate($invoice2, $company);
            $this->assertSame('FA-2025-0002', $number2);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $this->entityManager->beginTransaction();
        try {
            $number3 = $this->generator->generate($invoice3, $company);
            $this->assertSame('FA-2025-0003', $number3);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    // ========== Fiscal Year Tests ==========

    public function testStandardCalendarYearJanuaryToDecember(): void
    {
        $company = $this->createCompanyData(
            fiscalYearStartMonth: 1,
            fiscalYearStartDay: 1,
        );

        $testCases = [
            ['date' => '2025-01-01', 'expectedYear' => 2025],
            ['date' => '2025-06-15', 'expectedYear' => 2025],
            ['date' => '2025-12-31', 'expectedYear' => 2025],
        ];

        foreach ($testCases as $testCase) {
            $invoice = $this->createInvoice(InvoiceType::INVOICE, $testCase['date']);

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $expectedNumber = \sprintf('FA-%d-0001', $testCase['expectedYear']);
                $this->assertStringStartsWith($expectedNumber, $number);
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

            // Clean up sequences for next test
            $sequences = $this->sequenceRepository->findAll();
            foreach ($sequences as $seq) {
                $this->entityManager->remove($seq);
            }
            $this->entityManager->flush();
        }
    }

    public function testNonStandardFiscalYearNovemberToOctober(): void
    {
        $company = $this->createCompanyData(
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
        );

        $testCases = [
            ['date' => '2024-11-01', 'expectedYear' => 2024], // Exactly on start
            ['date' => '2024-11-15', 'expectedYear' => 2024], // After start
            ['date' => '2025-01-15', 'expectedYear' => 2024], // Still in FY 2024
            ['date' => '2025-10-31', 'expectedYear' => 2024], // End of FY 2024
        ];

        foreach ($testCases as $testCase) {
            $invoice = $this->createInvoice(InvoiceType::INVOICE, $testCase['date']);

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $expectedNumber = \sprintf('FA-%d-', $testCase['expectedYear']);
                $this->assertStringStartsWith($expectedNumber, $number);
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

            // Clean up sequences
            $sequences = $this->sequenceRepository->findAll();
            foreach ($sequences as $seq) {
                $this->entityManager->remove($seq);
            }
            $this->entityManager->flush();
        }
    }

    public function testInvoiceDateBeforeFiscalYearStartBelongsToPreviousYear(): void
    {
        $company = $this->createCompanyData(
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
        );

        // Invoice dated Oct 31, 2024 (before Nov 1) should belong to FY 2023
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2024-10-31');

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);
            $this->assertStringStartsWith('FA-2023-', $number);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testInvoiceDateAfterFiscalYearStartBelongsToCurrentYear(): void
    {
        $company = $this->createCompanyData(
            fiscalYearStartMonth: 11,
            fiscalYearStartDay: 1,
        );

        // Invoice dated Nov 1, 2024 (exactly on start) should belong to FY 2024
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2024-11-01');

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);
            $this->assertStringStartsWith('FA-2024-', $number);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testFiscalYearBoundaryDateExactlyOnStartDate(): void
    {
        $company = $this->createCompanyData(
            fiscalYearStartMonth: 4,
            fiscalYearStartDay: 1,
        );

        $testCases = [
            ['date' => '2025-03-31', 'expectedYear' => 2024], // Day before FY start
            ['date' => '2025-04-01', 'expectedYear' => 2025], // Exactly on FY start
            ['date' => '2025-04-02', 'expectedYear' => 2025], // Day after FY start
        ];

        foreach ($testCases as $testCase) {
            $invoice = $this->createInvoice(InvoiceType::INVOICE, $testCase['date']);

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $expectedNumber = \sprintf('FA-%d-', $testCase['expectedYear']);
                $this->assertStringStartsWith($expectedNumber, $number);
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

            // Clean up
            $sequences = $this->sequenceRepository->findAll();
            foreach ($sequences as $seq) {
                $this->entityManager->remove($seq);
            }
            $this->entityManager->flush();
        }
    }

    // ========== Multi-Company Tests ==========

    public function testSequencesSeparatedByCompanyId(): void
    {
        $company = $this->createCompanyData();

        $invoice1 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15', companyId: 1);
        $invoice2 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15', companyId: 2);
        $invoice3 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15', companyId: 1);

        $this->entityManager->beginTransaction();
        try {
            $number1 = $this->generator->generate($invoice1, $company);
            $this->assertSame('FA-2025-0001', $number1);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $this->entityManager->beginTransaction();
        try {
            $number2 = $this->generator->generate($invoice2, $company);
            $this->assertSame('FA-2025-0001', $number2); // Company 2 starts at 0001
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $this->entityManager->beginTransaction();
        try {
            $number3 = $this->generator->generate($invoice3, $company);
            $this->assertSame('FA-2025-0002', $number3); // Company 1 continues to 0002
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testNullCompanyIdForMonoCompany(): void
    {
        $company = $this->createCompanyData();

        $invoice1 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15', companyId: null);
        $invoice2 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-20', companyId: null);

        $this->entityManager->beginTransaction();
        try {
            $number1 = $this->generator->generate($invoice1, $company);
            $this->assertSame('FA-2025-0001', $number1);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $this->entityManager->beginTransaction();
        try {
            $number2 = $this->generator->generate($invoice2, $company);
            $this->assertSame('FA-2025-0002', $number2);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testSameYearDifferentCompaniesIndependentSequences(): void
    {
        $company = $this->createCompanyData();

        // Create invoices for 3 different companies in same fiscal year
        $companies = [1, 2, 3];
        foreach ($companies as $companyId) {
            $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-06-15', companyId: $companyId);

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $this->assertSame('FA-2025-0001', $number); // Each starts at 0001
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        }
    }

    // ========== Thread-Safety Tests ==========

    public function testSequentialGenerationNoDuplicateNumbers(): void
    {
        $company = $this->createCompanyData();
        $generatedNumbers = [];

        // Generate 10 numbers sequentially
        for ($i = 1; $i <= 10; ++$i) {
            $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');

            $this->entityManager->beginTransaction();
            try {
                $number = $this->generator->generate($invoice, $company);
                $generatedNumbers[] = $number;
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        }

        // Verify no duplicates
        $this->assertCount(10, array_unique($generatedNumbers));

        // Verify sequential numbering
        for ($i = 1; $i <= 10; ++$i) {
            $expected = \sprintf('FA-2025-%04d', $i);
            $this->assertSame($expected, $generatedNumbers[$i - 1]);
        }
    }

    public function testPessimisticLockUsedForSequenceRetrieval(): void
    {
        $company = $this->createCompanyData();
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');

        // First generation creates sequence
        $this->entityManager->beginTransaction();
        try {
            $this->generator->generate($invoice, $company);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Verify sequence exists and has correct lastNumber (must be in transaction)
        $this->entityManager->beginTransaction();
        try {
            $sequence = $this->sequenceRepository->findForUpdate(null, 2025, InvoiceType::INVOICE);
            $this->assertNotNull($sequence);
            $this->assertSame(1, $sequence->getLastNumber());
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    // ========== Integration Tests ==========

    public function testAutoCreatesInvoiceSequenceWhenMissing(): void
    {
        $company = $this->createCompanyData();
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');

        // Verify no sequence exists initially
        $this->assertCount(0, $this->sequenceRepository->findAll());

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);
            $this->assertSame('FA-2025-0001', $number);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Verify sequence was created
        $sequences = $this->sequenceRepository->findAll();
        $this->assertCount(1, $sequences);
        $this->assertSame(2025, $sequences[0]->getFiscalYear());
        $this->assertSame(InvoiceType::INVOICE, $sequences[0]->getType());
    }

    public function testUsesExistingInvoiceSequence(): void
    {
        // Pre-create sequence
        $sequence = new InvoiceSequence(
            companyId: null,
            fiscalYear: 2025,
            type: InvoiceType::INVOICE,
            startDate: new \DateTimeImmutable('2025-01-01'),
            endDate: new \DateTimeImmutable('2025-12-31'),
        );
        $sequence->setLastNumber(42);
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        $company = $this->createCompanyData();
        $invoice = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');

        $this->entityManager->beginTransaction();
        try {
            $number = $this->generator->generate($invoice, $company);
            $this->assertSame('FA-2025-0043', $number); // Continues from 42
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Verify lastNumber was incremented
        $this->entityManager->refresh($sequence);
        $this->assertSame(43, $sequence->getLastNumber());
    }

    public function testSequenceIncrementsPersistsToDatabase(): void
    {
        $company = $this->createCompanyData();

        $invoice1 = $this->createInvoice(InvoiceType::INVOICE, '2025-05-15');
        $invoice2 = $this->createInvoice(InvoiceType::INVOICE, '2025-06-10');

        $this->entityManager->beginTransaction();
        try {
            $this->generator->generate($invoice1, $company);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Check lastNumber after first generation
        $sequence = $this->sequenceRepository->findAll()[0];
        $this->assertSame(1, $sequence->getLastNumber());

        $this->entityManager->beginTransaction();
        try {
            $this->generator->generate($invoice2, $company);
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Check lastNumber after second generation
        $this->entityManager->refresh($sequence);
        $this->assertSame(2, $sequence->getLastNumber());
    }

    // ========== Helper Methods ==========

    private function createInvoice(InvoiceType $type, string $date, ?int $companyId = null): Invoice
    {
        $invoice = new Invoice(
            type: $type,
            date: new \DateTimeImmutable($date),
            dueDate: new \DateTimeImmutable($date),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        if (null !== $companyId) {
            $invoice->setCompanyId($companyId);
        }

        return $invoice;
    }

    private function createCompanyData(
        int $fiscalYearStartMonth = 1,
        int $fiscalYearStartDay = 1,
    ): CompanyData {
        return new CompanyData(
            name: 'Test Company',
            address: '456 Company Ave, 75001 Paris, France',
            fiscalYearStartMonth: $fiscalYearStartMonth,
            fiscalYearStartDay: $fiscalYearStartDay,
        );
    }
}
