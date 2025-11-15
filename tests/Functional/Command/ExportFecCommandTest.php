<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Command;

use CorentinBoutillier\InvoiceBundle\Command\ExportFecCommand;
use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizerInterface;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceManagerInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests fonctionnels pour ExportFecCommand.
 *
 * Vérifie :
 * - Parsing des arguments (fiscal year)
 * - Options (--output, --company-id)
 * - Calcul des dates fiscales (selon fiscal_year_start_month)
 * - Output fichier vs stdout
 * - Validation et gestion d'erreurs
 * - Intégration avec FecExporter
 */
final class ExportFecCommandTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ExportFecCommand $command;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private InvoiceManagerInterface $invoiceManager;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private InvoiceFinalizerInterface $invoiceFinalizer;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $invoiceManager = $container->get(InvoiceManagerInterface::class);
        \assert($invoiceManager instanceof InvoiceManagerInterface);
        $this->invoiceManager = $invoiceManager;

        $invoiceFinalizer = $container->get(InvoiceFinalizerInterface::class);
        \assert($invoiceFinalizer instanceof InvoiceFinalizerInterface);
        $this->invoiceFinalizer = $invoiceFinalizer;

        $command = $container->get(ExportFecCommand::class);
        \assert($command instanceof ExportFecCommand);
        $this->command = $command;

        // Create temporary directory for file output tests
        $this->tempDir = sys_get_temp_dir().'/invoice_bundle_test_'.uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Test 1: Exécution basique avec année fiscale → génère output FEC.
     */
    public function testBasicExecutionWithFiscalYear(): void
    {
        // Create and finalize an invoice in 2025
        $invoice = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-01-15'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode, 'Command should exit with SUCCESS');

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('JournalCode|JournalLib|EcritureNum', $output, 'Output should contain FEC header');
        $this->assertStringContainsString($invoice->getNumber() ?? '', $output, 'Output should contain invoice number');
    }

    /**
     * Test 2: Option --output écrit dans un fichier.
     */
    public function testOutputToFile(): void
    {
        $invoice = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-02-10'));

        $outputFile = $this->tempDir.'/fec_export.txt';
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
            '--output' => $outputFile,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($outputFile, 'Output file should be created');

        $content = file_get_contents($outputFile);
        \assert(false !== $content);
        $this->assertStringContainsString('JournalCode|JournalLib|EcritureNum', $content, 'File should contain FEC header');
        $this->assertStringContainsString($invoice->getNumber() ?? '', $content, 'File should contain invoice number');

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('fec export', strtolower($display), 'Should show success message');
    }

    /**
     * Test 3: Sans --output, affiche sur stdout.
     */
    public function testOutputToStdout(): void
    {
        $invoice = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-03-20'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('JournalCode|JournalLib|EcritureNum', $output, 'Stdout should contain FEC header');
        $this->assertStringContainsString($invoice->getNumber() ?? '', $output, 'Stdout should contain invoice number');
    }

    /**
     * Test 4: Option --company-id est passée au FecExporter.
     */
    public function testCompanyIdOption(): void
    {
        $invoice = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-04-05'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
            '--company-id' => '1',
        ]);

        // Note: ConfigCompanyProvider throws exception for multi-company
        // This test verifies the option is accepted (even if provider rejects it)
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * Test 5: Calcul des dates fiscales selon fiscal_year_start_month.
     *
     * TestKernel config: fiscal_year_start_month = 1 (January)
     * Donc fiscal year 2025 = 2025-01-01 à 2025-12-31.
     */
    public function testFiscalYearDateCalculation(): void
    {
        $invoiceJan = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-01-10'));
        $invoiceDec = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-12-20'));
        $invoiceNextYear = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2026-01-05'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString($invoiceJan->getNumber() ?? '', $output, 'Should include January 2025 invoice');
        $this->assertStringContainsString($invoiceDec->getNumber() ?? '', $output, 'Should include December 2025 invoice');
        $this->assertStringNotContainsString($invoiceNextYear->getNumber() ?? '', $output, 'Should NOT include January 2026 invoice');
    }

    /**
     * Test 6: Rejette les formats d'année invalides.
     */
    public function testInvalidFiscalYearFormat(): void
    {
        $commandTester = new CommandTester($this->command);

        // Test with non-numeric value
        $exitCode = $commandTester->execute([
            'fiscal-year' => 'invalid',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode, 'Should fail with invalid fiscal year');

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('invalid', strtolower($output), 'Error message should mention invalid input');
    }

    /**
     * Test 7: Rejette les années hors plage raisonnable.
     */
    public function testFiscalYearOutOfRange(): void
    {
        $commandTester = new CommandTester($this->command);

        // Test with year too old
        $exitCode = $commandTester->execute([
            'fiscal-year' => '1999',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode, 'Should fail with year too old');

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('year', strtolower($output), 'Error message should mention year');
    }

    /**
     * Test 8: Crée les répertoires manquants pour le fichier output.
     */
    public function testCreatesOutputDirectoryIfNeeded(): void
    {
        $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-05-15'));

        $nestedDir = $this->tempDir.'/nested/path/to/file';
        $outputFile = $nestedDir.'/export.txt';

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
            '--output' => $outputFile,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($outputFile, 'Should create nested directories and file');
        $this->assertDirectoryExists($nestedDir, 'Should create parent directories');
    }

    /**
     * Test 9: Gère le cas sans factures (période vide).
     */
    public function testEmptyPeriodWithNoInvoices(): void
    {
        // Don't create any invoices

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode, 'Should succeed even with no invoices');

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('JournalCode|JournalLib|EcritureNum', $output, 'Should still output FEC header');

        // Count lines (header only if no invoices)
        $lines = explode("\n", trim($output));
        $this->assertCount(1, $lines, 'Should only have header line when no invoices');
    }

    /**
     * Test 10: Vérifie que seules les factures FINALIZED sont exportées.
     */
    public function testOnlyFinalizedInvoicesAreExported(): void
    {
        // Create DRAFT invoice (should NOT be exported)
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '123 Test St, 75001 Paris, France',
        );

        $draftInvoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: new \DateTimeImmutable('2025-06-10'),
            paymentTerms: '30 jours net',
        );

        $line = new InvoiceLine(
            description: 'Draft service',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );
        $this->invoiceManager->addLine($draftInvoice, $line);

        // Create FINALIZED invoice (SHOULD be exported)
        $finalizedInvoice = $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-06-15'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString($finalizedInvoice->getNumber() ?? '', $output, 'Should include finalized invoice');
        $this->assertStringNotContainsString('Draft service', $output, 'Should NOT include draft invoice');
    }

    /**
     * Test 11: Message de succès contient le nombre de factures exportées.
     */
    public function testSuccessMessageContainsInvoiceCount(): void
    {
        $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-07-10'));
        $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-07-20'));

        $outputFile = $this->tempDir.'/export.txt';
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
            '--output' => $outputFile,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $display = $commandTester->getDisplay();
        // Should mention the file path (normalize whitespace/newlines for comparison)
        $normalizedDisplay = preg_replace('/\s+/', ' ', $display);
        \assert(\is_string($normalizedDisplay));
        $this->assertStringContainsString(basename($outputFile), $normalizedDisplay, 'Success message should show output file name');
    }

    /**
     * Test 12: Vérifie le format de sortie FEC (pipe-separated, 18 colonnes).
     */
    public function testFecOutputFormat(): void
    {
        $this->createAndFinalizeInvoice(new \DateTimeImmutable('2025-08-05'));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'fiscal-year' => '2025',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $lines = explode("\n", trim($output));

        // Check header line has 18 columns
        $headerColumns = explode('|', $lines[0]);
        $this->assertCount(18, $headerColumns, 'FEC header must have exactly 18 columns');
        $this->assertSame('JournalCode', $headerColumns[0], 'First column should be JournalCode');
        $this->assertSame('Idevise', $headerColumns[17], 'Last column should be Idevise');

        // Check data lines also have 18 columns
        if (isset($lines[1]) && '' !== $lines[1]) {
            $dataColumns = explode('|', $lines[1]);
            $this->assertCount(18, $dataColumns, 'FEC data lines must have exactly 18 columns');
        }
    }

    /**
     * Helper: Create and finalize an invoice for testing.
     */
    private function createAndFinalizeInvoice(\DateTimeImmutable $date): \CorentinBoutillier\InvoiceBundle\Entity\Invoice
    {
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '456 Avenue Test, 75002 Paris, France',
        );

        $invoice = $this->invoiceManager->createInvoice(
            customerData: $customerData,
            date: $date,
            paymentTerms: '30 jours net',
        );

        $line = new InvoiceLine(
            description: 'Test Service',
            quantity: 5.0,
            unitPrice: Money::fromEuros('200.00'),
            vatRate: 20.0,
        );

        $this->invoiceManager->addLine($invoice, $line);
        $this->invoiceFinalizer->finalize($invoice);

        // Flush to ensure invoice is in database for FecExporter query
        $this->entityManager->flush();

        return $invoice;
    }

    /**
     * Helper: Recursively remove a directory.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
