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

        // Find customer line (debit 1200,00 - French FEC format with comma)
        $customerLine = $this->findLineContaining($lines, '411000');
        $this->assertNotNull($customerLine, 'Customer line not found');
        $this->assertStringContainsString('|1200,00|0,00|', $customerLine, 'Customer debit must be 1200,00');

        // Find sales line (credit 1000,00 - French FEC format with comma)
        $salesLine = $this->findLineContaining($lines, '707000');
        $this->assertNotNull($salesLine, 'Sales line not found');
        $this->assertStringContainsString('|0,00|1000,00|', $salesLine, 'Sales credit must be 1000,00');

        // Find VAT line (credit 200,00 - French FEC format with comma)
        $vatLine = $this->findLineContaining($lines, '445710');
        $this->assertNotNull($vatLine, 'VAT line not found');
        $this->assertStringContainsString('|0,00|200,00|', $vatLine, 'VAT credit must be 200,00');
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
     * Test 6: Export formats Money DTO with comma as decimal separator (French FEC format).
     */
    public function testExportFormatsMoneyWithCommaSeparator(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Must contain comma as decimal separator (French FEC format)
        $this->assertStringContainsString(',00|', $csv, 'Must use comma as decimal separator');

        // Must contain amounts formatted with comma
        $this->assertMatchesRegularExpression('/\|\d+,\d{2}\|/', $csv, 'Must use comma as decimal separator with 2 decimals');

        // Must NOT contain period as decimal separator (except in date fields)
        $this->assertStringNotContainsString('.00|', $csv, 'Must not use period as decimal separator');
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

        // Credit note: customer is CREDITED (not debited) - French FEC format with comma
        $customerLine = $this->findLineContaining($lines, '411000');
        $this->assertNotNull($customerLine, 'Customer line not found');
        $this->assertMatchesRegularExpression('/\|0,00\|\d+,\d{2}\|/', $customerLine, 'Credit note: customer must be credited');

        // Sales account is DEBITED (not credited) - French FEC format with comma
        $salesLine = $this->findLineContaining($lines, '707000');
        $this->assertNotNull($salesLine, 'Sales line not found');
        $this->assertMatchesRegularExpression('/\|\d+,\d{2}\|0,00\|/', $salesLine, 'Credit note: sales must be debited');
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
     * Test 12: Export uses same EcritureNum for all lines of a single invoice (double-entry).
     */
    public function testExportUsesSameEcritureNumPerInvoice(): void
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

        // All lines from single invoice should have SAME EcritureNum (double-entry accounting)
        $this->assertCount(3, $ecritureNums, 'Should have 3 FEC lines (customer + sales + VAT)');
        $this->assertNotEmpty($ecritureNums[0], 'First EcritureNum should not be empty');
        $this->assertSame($ecritureNums[0], $ecritureNums[1], 'All lines of same invoice must have same EcritureNum');
        $this->assertSame($ecritureNums[0], $ecritureNums[2], 'All lines of same invoice must have same EcritureNum');
    }

    /**
     * Test 13: Export uses different EcritureNum for different invoices.
     */
    public function testExportUsesDifferentEcritureNumPerInvoice(): void
    {
        $invoice1 = $this->createFinalizedInvoice();
        $invoice1->setNumber('FA-2024-100');
        $this->entityManager->persist($invoice1);

        $invoice2 = $this->createFinalizedInvoiceWithDate(new \DateTimeImmutable('2024-03-20'));
        $invoice2->setNumber('FA-2024-101');
        $this->entityManager->persist($invoice2);

        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $dataLines = \array_slice($lines, 1); // Skip header

        $ecritureNumsByInvoice = [];
        foreach ($dataLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode('|', $line);
            $ecritureNum = $columns[2] ?? '';
            $pieceRef = $columns[8] ?? ''; // Invoice number

            if (!empty($pieceRef)) {
                $ecritureNumsByInvoice[$pieceRef] = $ecritureNum;
            }
        }

        // Different invoices should have different EcritureNums
        $this->assertCount(2, $ecritureNumsByInvoice, 'Should have 2 distinct invoices');
        $uniqueNums = array_unique(array_values($ecritureNumsByInvoice));
        $this->assertCount(2, $uniqueNums, 'Different invoices must have different EcritureNums');
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
     * Test 14: Export generates payment lines with bank journal.
     */
    public function testExportGeneratesPaymentLinesWithBankJournal(): void
    {
        $invoice = $this->createFinalizedInvoiceWithPayment();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should contain bank journal (BQ) for payment
        $this->assertStringContainsString('BQ|', $csv, 'Must contain bank journal code BQ');
        $this->assertStringContainsString('|512000|', $csv, 'Must contain bank account 512000');
        $this->assertStringContainsString('|Banque|', $csv, 'Must contain bank journal label');
    }

    /**
     * Test 15: Export generates lettrage code for invoices with payments.
     */
    public function testExportGeneratesLettrageCodeForPaidInvoices(): void
    {
        $invoice = $this->createFinalizedInvoiceWithPayment();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);

        // Find customer lines (411000) - both invoice and payment should have lettrage
        $customerLines = array_filter($lines, fn ($line) => str_contains($line, '|411000|'));

        // Should have 2 customer lines: one for invoice (debit), one for payment (credit)
        $this->assertCount(2, $customerLines, 'Should have 2 customer lines (invoice + payment)');

        // Both should have the same lettrage code (A for first invoice)
        foreach ($customerLines as $line) {
            $this->assertMatchesRegularExpression('/\|A\|/', $line, 'Customer lines must have lettrage code A');
        }
    }

    /**
     * Test 16: Export generates sequential lettrage codes for multiple paid invoices.
     */
    public function testExportGeneratesSequentialLettrageCodesForMultiplePaidInvoices(): void
    {
        $invoice1 = $this->createFinalizedInvoiceWithPayment();
        $invoice1->setNumber('FA-2024-010');
        $this->entityManager->persist($invoice1);

        $invoice2 = $this->createFinalizedInvoiceWithPaymentAndDate(new \DateTimeImmutable('2024-03-20'));
        $invoice2->setNumber('FA-2024-011');
        $this->entityManager->persist($invoice2);

        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should have lettrage codes A and B
        $this->assertStringContainsString('|A|', $csv, 'First invoice must have lettrage code A');
        $this->assertStringContainsString('|B|', $csv, 'Second invoice must have lettrage code B');
    }

    /**
     * Test 17: Export handles VAT rate 5.5%.
     */
    public function testExportHandlesVatRate55(): void
    {
        $invoice = $this->createInvoiceWithVatRate(5.5);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should use account 445711 for 5.5% VAT
        $this->assertStringContainsString('|445711|', $csv, 'Must use VAT account 445711 for 5.5%');
        $this->assertStringContainsString('TVA 5.5', $csv, 'Must mention TVA 5.5% in description');
    }

    /**
     * Test 18: Export handles VAT rate 2.1%.
     */
    public function testExportHandlesVatRate21(): void
    {
        $invoice = $this->createInvoiceWithVatRate(2.1);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should use account 445713 for 2.1% VAT
        $this->assertStringContainsString('|445713|', $csv, 'Must use VAT account 445713 for 2.1%');
        $this->assertStringContainsString('TVA 2.1', $csv, 'Must mention TVA 2.1% in description');
    }

    /**
     * Test 19: Export filters by companyId.
     */
    public function testExportFiltersByCompanyId(): void
    {
        $invoice1 = $this->createFinalizedInvoice();
        $invoice1->setNumber('FA-2024-C1');
        $invoice1->setCompanyId(1);
        $this->entityManager->persist($invoice1);

        $invoice2 = $this->createFinalizedInvoiceWithDate(new \DateTimeImmutable('2024-03-20'));
        $invoice2->setNumber('FA-2024-C2');
        $invoice2->setCompanyId(2);
        $this->entityManager->persist($invoice2);

        $this->entityManager->flush();

        // Export only company 1
        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
            companyId: 1,
        );

        $this->assertStringContainsString('FA-2024-C1', $csv, 'Must contain company 1 invoice');
        $this->assertStringNotContainsString('FA-2024-C2', $csv, 'Must not contain company 2 invoice');
    }

    /**
     * Test 20: Export uses customer SIRET as CompAuxNum when available.
     */
    public function testExportUsesCustomerSiretAsCompAuxNum(): void
    {
        $invoice = $this->createFinalizedInvoice();
        $invoice->setCustomerSiret('12345678901234');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertStringContainsString('|12345678901234|', $csv, 'Must use customer SIRET as CompAuxNum');
    }

    /**
     * Test 21: Export generates CompAuxNum from customer name when SIRET not available.
     */
    public function testExportGeneratesCompAuxNumFromCustomerName(): void
    {
        $invoice = $this->createFinalizedInvoice();
        // No SIRET set, should generate from name "Acme Corporation"
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should contain sanitized name (uppercase, no special chars)
        $this->assertStringContainsString('|ACMECORPORATION|', $csv, 'Must generate CompAuxNum from customer name');
    }

    /**
     * Test 22: Export handles customer name with accents (removes accents for CompAuxNum).
     */
    public function testExportRemovesAccentsFromCustomerName(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Société Générale',
            customerAddress: '123 Rue de Paris, 75001 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2024-ACC');

        $line = new InvoiceLine(
            description: 'Service test',
            unitPrice: Money::fromEuros('100.00'),
            quantity: 1,
            vatRate: 20.0,
        );
        $invoice->addLine($line);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        // Should contain sanitized name without accents in CompAuxNum
        $this->assertStringContainsString('|SOCIETEGENERALE|', $csv, 'Must remove accents from customer name in CompAuxNum');
        // CompAuxLib should still contain the original name with accents
        $this->assertStringContainsString('|Société Générale|', $csv, 'CompAuxLib should keep original name with accents');
    }

    /**
     * Test 23: Export maintains double-entry balance with payments.
     */
    public function testExportMaintainsDoubleEntryBalanceWithPayments(): void
    {
        $invoice = $this->createFinalizedInvoiceWithPayment();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $dataLines = \array_slice($lines, 1);

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($dataLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode('|', $line);
            // French format uses comma as decimal separator
            $debit = (float) str_replace(',', '.', $columns[11] ?? '0');
            $credit = (float) str_replace(',', '.', $columns[12] ?? '0');

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $this->assertEqualsWithDelta(
            $totalDebit,
            $totalCredit,
            0.01,
            'Total debits must equal total credits with payments',
        );
    }

    /**
     * Test 24: Export handles paid and partially paid invoices.
     */
    public function testExportHandlesPaidAndPartiallyPaidStatuses(): void
    {
        // Create PAID invoice
        $paidInvoice = $this->createFinalizedInvoiceWithPayment();
        $paidInvoice->setNumber('FA-2024-PAID');
        $paidInvoice->setStatus(InvoiceStatus::PAID);
        $this->entityManager->persist($paidInvoice);

        // Create PARTIALLY_PAID invoice
        $partialInvoice = $this->createFinalizedInvoiceWithPaymentAndDate(new \DateTimeImmutable('2024-03-20'));
        $partialInvoice->setNumber('FA-2024-PARTIAL');
        $partialInvoice->setStatus(InvoiceStatus::PARTIALLY_PAID);
        $this->entityManager->persist($partialInvoice);

        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $this->assertStringContainsString('FA-2024-PAID', $csv, 'Must include PAID invoice');
        $this->assertStringContainsString('FA-2024-PARTIAL', $csv, 'Must include PARTIALLY_PAID invoice');
    }

    /**
     * Test 25: Export uses different EcritureNum for invoice and payment entries.
     */
    public function testExportUsesDifferentEcritureNumForInvoiceAndPayment(): void
    {
        $invoice = $this->createFinalizedInvoiceWithPayment();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $csv = $this->fecExporter->export(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-12-31'),
        );

        $lines = explode("\n", $csv);
        $dataLines = \array_slice($lines, 1);

        $ecritureNums = [];
        foreach ($dataLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode('|', $line);
            $ecritureNum = $columns[2] ?? '';
            if (!empty($ecritureNum) && !\in_array($ecritureNum, $ecritureNums, true)) {
                $ecritureNums[] = $ecritureNum;
            }
        }

        // Should have 2 different EcritureNums: one for invoice, one for payment
        $this->assertCount(2, $ecritureNums, 'Should have 2 different EcritureNums (invoice + payment)');
    }

    // ========================================
    // Additional Helper Methods
    // ========================================

    private function createFinalizedInvoiceWithPayment(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'Paid Customer Corp',
            customerAddress: '555 Payment Street, 75008 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::PAID);
        $invoice->setNumber('FA-2024-PAY');

        $line = new InvoiceLine(
            description: 'Service with payment',
            unitPrice: Money::fromEuros('500.00'),
            quantity: 2,
            vatRate: 20.0,
        );
        $invoice->addLine($line);

        // Add payment with proper bidirectional relation
        $payment = new \CorentinBoutillier\InvoiceBundle\Entity\Payment(
            amount: Money::fromEuros('1200.00'),
            paidAt: new \DateTimeImmutable('2024-03-20'),
            method: \CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod::BANK_TRANSFER,
        );
        $payment->setInvoice($invoice);
        $invoice->addPayment($payment);

        return $invoice;
    }

    private function createFinalizedInvoiceWithPaymentAndDate(\DateTimeImmutable $date): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $date,
            dueDate: $date->modify('+30 days'),
            customerName: 'Another Paid Customer',
            customerAddress: '666 Payment Avenue, 75009 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::PAID);
        $invoice->setNumber('FA-'.$date->format('Y').'-PAY2');

        $line = new InvoiceLine(
            description: 'Another service with payment',
            unitPrice: Money::fromEuros('300.00'),
            quantity: 1,
            vatRate: 20.0,
        );
        $invoice->addLine($line);

        // Add payment with proper bidirectional relation
        $payment = new \CorentinBoutillier\InvoiceBundle\Entity\Payment(
            amount: Money::fromEuros('360.00'),
            paidAt: $date->modify('+5 days'),
            method: \CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod::BANK_TRANSFER,
        );
        $payment->setInvoice($invoice);
        $invoice->addPayment($payment);

        return $invoice;
    }

    private function createInvoiceWithVatRate(float $vatRate): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-03-15'),
            dueDate: new \DateTimeImmutable('2024-04-15'),
            customerName: 'VAT Rate Test Corp',
            customerAddress: '777 VAT Street, 75010 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '456 Company Avenue, 75002 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2024-VAT'.str_replace('.', '', (string) $vatRate));

        $line = new InvoiceLine(
            description: \sprintf('Service at %.1f%% VAT', $vatRate),
            unitPrice: Money::fromEuros('100.00'),
            quantity: 1,
            vatRate: $vatRate,
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
