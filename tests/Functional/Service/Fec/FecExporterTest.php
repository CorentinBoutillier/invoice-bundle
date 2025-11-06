<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service\Fec;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Service\Fec\FecExporterInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;

/**
 * Integration tests for FEC (Fichier des Écritures Comptables) exporter.
 *
 * Tests verify conformance with French legal requirements (Article A.47 A-1 LPF):
 * - 18 mandatory columns with pipe separator
 * - Double-entry accounting balance (debits = credits)
 * - Date format YYYYMMDD
 * - Amount format with period separator
 * - Multi-VAT rate handling (one line per rate)
 * - Credit note handling (reversed amounts)
 */
final class FecExporterTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private FecExporterInterface $fecExporter;

    protected function setUp(): void
    {
        parent::setUp();

        $fecExporter = $this->kernel->getContainer()->get(FecExporterInterface::class);
        if (!$fecExporter instanceof FecExporterInterface) {
            throw new \RuntimeException('FecExporterInterface not found');
        }
        $this->fecExporter = $fecExporter;
    }

    /**
     * Test 1: Export returns correct header format with 18 columns.
     */
    public function testExportReturnsCorrectHeaderFormat(): void
    {
        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $header = $lines[0];

        // Must start with JournalCode and end with Idevise
        $this->assertStringStartsWith('JournalCode|', $header);
        $this->assertStringContainsString('|Idevise', $header);

        // Must contain all 18 required columns
        $expectedColumns = [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
            'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit',
            'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString($column, $header, "Header must contain column: {$column}");
        }
    }

    /**
     * Test 2: Each data line has exactly 18 columns.
     */
    public function testExportReturnsCorrect18ColumnsPerLine(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        // Skip header (line 0) and check data lines
        $dataLines = \array_slice($lines, 1);

        foreach ($dataLines as $lineNumber => $line) {
            if (empty(trim($line))) {
                continue; // Skip empty lines
            }

            $columns = explode('|', $line);
            $this->assertCount(
                18,
                $columns,
                'Data line '.($lineNumber + 2).' must have exactly 18 columns, got '.\count($columns),
            );
        }
    }

    /**
     * Test 3: Export uses configured account numbers from YAML.
     */
    public function testExportUsesConfiguredAccountNumbers(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should contain configured accounts: 411000 (customer), 707000 (sales), 445710 (VAT)
        $this->assertStringContainsString('|411000|', $csv, 'Must use customer account 411000');
        $this->assertStringContainsString('|707000|', $csv, 'Must use sales account 707000');
        $this->assertStringContainsString('|445710|', $csv, 'Must use VAT collected account 445710');
    }

    /**
     * Test 4: Export calculates correct amounts from Money DTO.
     */
    public function testExportCalculatesCorrectAmounts(): void
    {
        // Create invoice with known amounts
        $invoice = $this->createInvoiceWithKnownAmounts();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);

        // Find customer line (debit 1200.00)
        $customerLine = $this->findLineContaining($lines, '411000');
        $this->assertNotNull($customerLine, 'Customer line not found');
        $this->assertStringContainsString('|1200.00|0.00|', $customerLine, 'Customer debit must be 1200.00');

        // Find sales line (credit 1000.00)
        $salesLine = $this->findLineContaining($lines, '707000');
        $this->assertNotNull($salesLine, 'Sales line not found');
        $this->assertStringContainsString('|0.00|1000.00|', $salesLine, 'Sales credit must be 1000.00');

        // Find VAT line (credit 200.00)
        $vatLine = $this->findLineContaining($lines, '445710');
        $this->assertNotNull($vatLine, 'VAT line not found');
        $this->assertStringContainsString('|0.00|200.00|', $vatLine, 'VAT credit must be 200.00');
    }

    /**
     * Test 5: Export maintains double-entry accounting balance.
     */
    public function testExportMaintainsDoubleEntryBalance(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $dataLines = \array_slice($lines, 1); // Skip header

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($dataLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode('|', $line);
            $debit = (float) ($columns[11] ?? '0');
            $credit = (float) ($columns[12] ?? '0');

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $this->assertEqualsWithDelta(
            $totalDebit,
            $totalCredit,
            0.01,
            'Total debits must equal total credits (double-entry balance)',
        );
    }

    /**
     * Test 6: Export formats Money DTO with period as decimal separator.
     */
    public function testExportFormatsMoneyWithPeriodSeparator(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Must NOT contain comma as decimal separator
        $this->assertStringNotContainsString(',00|', $csv, 'Must not use comma as decimal separator');

        // Must contain period as decimal separator
        $this->assertMatchesRegularExpression('/\|\d+\.\d{2}\|/', $csv, 'Must use period as decimal separator with 2 decimals');
    }

    /**
     * Test 7: Export formats dates as YYYYMMDD.
     */
    public function testExportFormatsDateAsYYYYMMDD(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Must contain dates in YYYYMMDD format (e.g., 20240315)
        $this->assertMatchesRegularExpression('/\|20240315\|/', $csv, 'Dates must be in YYYYMMDD format');

        // Must NOT contain dates with dashes
        $this->assertStringNotContainsString('|2024-03-15|', $csv, 'Dates must not contain dashes');
    }

    /**
     * Test 8: Export creates multiple VAT lines for different VAT rates.
     */
    public function testExportCreatesMultipleLinesForMultipleVatRates(): void
    {
        $invoice = $this->createInvoiceWithMultipleVatRates();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);

        // Should have 1 customer line + 1 sales line + 2 VAT lines (20% and 10%)
        $dataLines = array_filter($lines, fn ($line) => !empty(trim($line)));
        $this->assertCount(5, $dataLines, 'Should have header + 4 data lines (customer + sales + VAT 20% + VAT 10%)');

        // Check for VAT rate mentions in descriptions
        $csv = implode("\n", $lines);
        $this->assertStringContainsString('TVA 20', $csv, 'Must have VAT 20% line');
        $this->assertStringContainsString('TVA 10', $csv, 'Must have VAT 10% line');
    }

    /**
     * Test 9: Export handles credit notes with reversed amounts.
     */
    public function testExportHandlesCreditNotes(): void
    {
        $creditNote = $this->createFinalizedCreditNote();
        $this->entityManager->persist($creditNote);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);

        // Credit note: customer is CREDITED (not debited)
        $customerLine = $this->findLineContaining($lines, '411000');
        $this->assertNotNull($customerLine, 'Customer line not found');
        $this->assertMatchesRegularExpression('/\|0\.00\|\d+\.\d{2}\|/', $customerLine, 'Credit note: customer must be credited');

        // Sales account is DEBITED (not credited)
        $salesLine = $this->findLineContaining($lines, '707000');
        $this->assertNotNull($salesLine, 'Sales line not found');
        $this->assertMatchesRegularExpression('/\|\d+\.\d{2}\|0\.00\|/', $salesLine, 'Credit note: sales must be debited');
    }

    /**
     * Test 10: Export filters only FINALIZED invoices.
     */
    public function testExportFiltersOnlyFinalizedInvoices(): void
    {
        // Create DRAFT invoice
        $draft = $this->createDraftInvoice();
        $this->entityManager->persist($draft);

        // Create FINALIZED invoice
        $finalized = $this->createFinalizedInvoice();
        $this->entityManager->persist($finalized);

        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);

        // Should only contain finalized invoice
        $this->assertStringContainsString($finalized->getNumber() ?? '', $csv, 'Must contain finalized invoice');
        $this->assertStringNotContainsString('DRAFT', $csv, 'Must not contain draft invoice');
    }

    /**
     * Test 11: Export filters invoices by date range.
     */
    public function testExportFiltersDateRange(): void
    {
        // Create invoice in 2023
        $invoice2023 = $this->createFinalizedInvoiceWithDate(new \DateTimeImmutable('2023-06-15'));
        $this->entityManager->persist($invoice2023);

        // Create invoice in 2024
        $invoice2024 = $this->createFinalizedInvoiceWithDate(new \DateTimeImmutable('2024-03-15'));
        $this->entityManager->persist($invoice2024);

        $this->entityManager->flush();

        // Export only 2024
        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should only contain 2024 invoice
        $this->assertStringContainsString('20240315', $csv, 'Must contain 2024 invoice');
        $this->assertStringNotContainsString('20230615', $csv, 'Must not contain 2023 invoice');
    }

    /**
     * Test 12: Export uses sequential EcritureNum per invoice.
     */
    public function testExportUsesSequentialEcritureNum(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $dataLines = \array_slice($lines, 1); // Skip header

        $ecritureNums = [];
        foreach ($dataLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode('|', $line);
            $ecritureNums[] = $columns[2] ?? ''; // EcritureNum is column 3
        }

        // Should have sequential numbers (001, 002, 003)
        $this->assertCount(3, $ecritureNums, 'Should have 3 EcritureNum entries');
        $this->assertNotEmpty($ecritureNums[0], 'First EcritureNum should not be empty');
        $this->assertNotSame($ecritureNums[0], $ecritureNums[1], 'EcritureNums should be different');
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createFinalizedInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Acme Corporation',
            customerAddress: '123 Business Street, 75001 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2024-001');

        $line = new InvoiceLine(
            description: 'Service de développement',
            unitPrice: Money::fromEuros('100.00'),
            quantity: 10,
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        return $invoice;
    }

    private function createInvoiceWithKnownAmounts(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Known Amounts Corp',
            customerAddress: '789 Test Street, 75003 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2024-002');

        // Total HT: 1000.00, VAT 20%: 200.00, Total TTC: 1200.00
        $line = new InvoiceLine(
            description: 'Service with known amounts',
            unitPrice: Money::fromEuros('1000.00'),
            quantity: 1,
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        return $invoice;
    }

    private function createInvoiceWithMultipleVatRates(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Multi VAT Corp',
            customerAddress: '111 VAT Street, 75004 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2024-003');

        // Line with 20% VAT
        $line1 = new InvoiceLine(
            description: 'Service at 20% VAT',
            unitPrice: Money::fromEuros('100.00'),
            quantity: 5,
            vatRate: 20.0,
        );

        // Line with 10% VAT
        $line2 = new InvoiceLine(
            description: 'Service at 10% VAT',
            unitPrice: Money::fromEuros('50.00'),
            quantity: 4,
            vatRate: 10.0,
        );

        $invoice->addLine($line1);
        $invoice->addLine($line2);

        return $invoice;
    }

    private function createFinalizedCreditNote(): Invoice
    {
        $creditNote = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: new \DateTimeImmutable('2024-03-20'),
            dueDate: new \DateTimeImmutable('2024-04-20'),
            customerName: 'Refund Customer',
            customerAddress: '222 Refund Avenue, 75005 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $creditNote->setStatus(InvoiceStatus::FINALIZED);
        $creditNote->setNumber('AV-2024-001');

        $line = new InvoiceLine(
            description: 'Refund for service',
            unitPrice: Money::fromEuros('50.00'),
            quantity: 2,
            vatRate: 20.0,
        );

        $creditNote->addLine($line);

        return $creditNote;
    }

    private function createDraftInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Draft Customer',
            customerAddress: '333 Draft Street, 75006 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        // Leave as DRAFT (default status)
        $line = new InvoiceLine(
            description: 'Draft service',
            unitPrice: Money::fromEuros('100.00'),
            quantity: 1,
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        return $invoice;
    }

    private function createFinalizedInvoiceWithDate(\DateTimeImmutable $date): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $date,
            dueDate: $date->modify('+30 days'),
            customerName: 'Date Test Customer',
            customerAddress: '444 Date Avenue, 75007 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-'.$date->format('Y').'-999');

        $line = new InvoiceLine(
            description: 'Service for date test',
            unitPrice: Money::fromEuros('100.00'),
            quantity: 1,
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        return $invoice;
    }

    /**
     * Find first line containing a specific substring.
     *
     * @param string[] $lines
     */
    private function findLineContaining(array $lines, string $needle): ?string
    {
        foreach ($lines as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }

        return null;
    }
}
