<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\EReporting\Dto\EReportingTransaction;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\EReportingPaymentStatus;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\TransactionType;
use CorentinBoutillier\InvoiceBundle\EReporting\EReportingScheduler;
use CorentinBoutillier\InvoiceBundle\EReporting\EReportingService;
use CorentinBoutillier\InvoiceBundle\EReporting\EReportingServiceInterface;
use PHPUnit\Framework\TestCase;

final class EReportingServiceTest extends TestCase
{
    private EReportingService $service;

    protected function setUp(): void
    {
        $scheduler = new EReportingScheduler();
        $this->service = new EReportingService($scheduler);
    }

    // ========================================
    // Interface Tests
    // ========================================

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(EReportingServiceInterface::class, $this->service);
    }

    // ========================================
    // Create Transaction From Invoice Tests
    // ========================================

    public function testCreateTransactionFromInvoiceB2CFrance(): void
    {
        $invoice = $this->createB2CInvoice();
        $invoice->setNumber('FA-2025-0001');

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame('FA-2025-0001', $transaction->invoiceNumber);
        $this->assertSame(TransactionType::B2C_FRANCE, $transaction->transactionType);
        $this->assertSame('FR', $transaction->customerCountry);
        $this->assertSame(EReportingPaymentStatus::NOT_PAID, $transaction->paymentStatus);
    }

    public function testCreateTransactionFromInvoiceWithLines(): void
    {
        $invoice = $this->createB2CInvoice();
        $invoice->addLine(new InvoiceLine(
            description: 'Service A',
            quantity: 2.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        ));
        $invoice->addLine(new InvoiceLine(
            description: 'Service B',
            quantity: 1.0,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 10.0,
        ));

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame('250.00', $transaction->totalExcludingVat);
        $this->assertSame('45.00', $transaction->totalVat);
        $this->assertSame('295.00', $transaction->totalIncludingVat);
        $this->assertArrayHasKey('20.00', $transaction->vatBreakdown);
        $this->assertArrayHasKey('10.00', $transaction->vatBreakdown);
    }

    public function testCreateTransactionFromPaidInvoice(): void
    {
        $invoice = $this->createB2CInvoice();
        $invoice->setStatus(InvoiceStatus::PAID);

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame(EReportingPaymentStatus::FULLY_PAID, $transaction->paymentStatus);
    }

    public function testCreateTransactionFromPartiallyPaidInvoice(): void
    {
        $invoice = $this->createB2CInvoice();
        $invoice->setStatus(InvoiceStatus::PARTIALLY_PAID);

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame(EReportingPaymentStatus::PARTIALLY_PAID, $transaction->paymentStatus);
    }

    public function testCreateTransactionFromInvoiceB2BExport(): void
    {
        $invoice = $this->createB2BExportInvoice();

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame(TransactionType::B2B_EXPORT, $transaction->transactionType);
        $this->assertSame('US', $transaction->customerCountry);
    }

    public function testCreateTransactionFromInvoiceB2CIntraEU(): void
    {
        $invoice = $this->createB2CIntraEUInvoice();

        $transaction = $this->service->createTransactionFromInvoice($invoice);

        $this->assertSame(TransactionType::B2C_INTRA_EU, $transaction->transactionType);
        $this->assertSame('DE', $transaction->customerCountry);
    }

    // ========================================
    // Get Summary Tests
    // ========================================

    public function testGetSummary(): void
    {
        $periodStart = new \DateTimeImmutable('2025-01-01');
        $periodEnd = new \DateTimeImmutable('2025-01-31');

        $transactions = [
            new EReportingTransaction(
                invoiceNumber: 'FA-001',
                invoiceDate: new \DateTimeImmutable('2025-01-10'),
                transactionType: TransactionType::B2C_FRANCE,
                totalExcludingVat: '100.00',
                totalVat: '20.00',
                totalIncludingVat: '120.00',
            ),
            new EReportingTransaction(
                invoiceNumber: 'FA-002',
                invoiceDate: new \DateTimeImmutable('2025-01-15'),
                transactionType: TransactionType::B2C_FRANCE,
                totalExcludingVat: '200.00',
                totalVat: '40.00',
                totalIncludingVat: '240.00',
            ),
        ];

        $summary = $this->service->getSummary(
            $periodStart,
            $periodEnd,
            ReportingFrequency::MONTHLY,
            $transactions,
        );

        $this->assertSame($periodStart, $summary->periodStart);
        $this->assertSame($periodEnd, $summary->periodEnd);
        $this->assertSame(ReportingFrequency::MONTHLY, $summary->frequency);
        $this->assertSame(2, $summary->transactionCount);
        $this->assertSame('300.00', $summary->totalExcludingVat);
        $this->assertSame('60.00', $summary->totalVat);
        $this->assertSame('360.00', $summary->totalIncludingVat);
        $this->assertArrayHasKey('b2c_france', $summary->transactionsByType);
        $this->assertSame(2, $summary->transactionsByType['b2c_france']);
    }

    public function testGetSummaryWithMixedTypes(): void
    {
        $periodStart = new \DateTimeImmutable('2025-01-01');
        $periodEnd = new \DateTimeImmutable('2025-01-31');

        $transactions = [
            new EReportingTransaction(
                invoiceNumber: 'FA-001',
                invoiceDate: new \DateTimeImmutable('2025-01-10'),
                transactionType: TransactionType::B2C_FRANCE,
                totalExcludingVat: '100.00',
                totalVat: '20.00',
                totalIncludingVat: '120.00',
            ),
            new EReportingTransaction(
                invoiceNumber: 'FA-002',
                invoiceDate: new \DateTimeImmutable('2025-01-15'),
                transactionType: TransactionType::B2C_EXPORT,
                totalExcludingVat: '500.00',
                totalVat: '0.00',
                totalIncludingVat: '500.00',
            ),
        ];

        $summary = $this->service->getSummary(
            $periodStart,
            $periodEnd,
            ReportingFrequency::MONTHLY,
            $transactions,
        );

        $this->assertSame(2, $summary->transactionCount);
        $this->assertSame(1, $summary->transactionsByType['b2c_france']);
        $this->assertSame(1, $summary->transactionsByType['b2c_export']);
    }

    public function testGetSummaryEmpty(): void
    {
        $periodStart = new \DateTimeImmutable('2025-01-01');
        $periodEnd = new \DateTimeImmutable('2025-01-31');

        $summary = $this->service->getSummary(
            $periodStart,
            $periodEnd,
            ReportingFrequency::MONTHLY,
            [],
        );

        $this->assertSame(0, $summary->transactionCount);
        $this->assertSame('0.00', $summary->totalExcludingVat);
        $this->assertFalse($summary->hasTransactions());
    }

    // ========================================
    // Submit Tests
    // ========================================

    public function testSubmitReturnsSuccessResult(): void
    {
        $transactions = [
            new EReportingTransaction(
                invoiceNumber: 'FA-001',
                invoiceDate: new \DateTimeImmutable('2025-01-10'),
                transactionType: TransactionType::B2C_FRANCE,
                totalExcludingVat: '100.00',
                totalVat: '20.00',
                totalIncludingVat: '120.00',
            ),
        ];

        $result = $this->service->submit($transactions);

        // Default implementation returns success (actual submission is PDP-specific)
        $this->assertTrue($result->success);
        $this->assertSame(1, $result->transactions);
        $this->assertNotNull($result->reportId);
    }

    public function testSubmitWithEmptyTransactions(): void
    {
        $result = $this->service->submit([]);

        $this->assertFalse($result->success);
        $this->assertTrue($result->hasErrors());
    }

    // ========================================
    // Requires E-Reporting Tests
    // ========================================

    public function testRequiresEReportingB2CFrance(): void
    {
        $invoice = $this->createB2CInvoice();

        $this->assertTrue($this->service->requiresEReporting($invoice));
    }

    public function testRequiresEReportingB2BFrance(): void
    {
        $invoice = $this->createB2BInvoice();

        // B2B France uses e-invoicing, not e-reporting
        $this->assertFalse($this->service->requiresEReporting($invoice));
    }

    public function testRequiresEReportingB2BExport(): void
    {
        $invoice = $this->createB2BExportInvoice();

        $this->assertTrue($this->service->requiresEReporting($invoice));
    }

    public function testRequiresEReportingDraft(): void
    {
        $invoice = $this->createB2CInvoice();
        $invoice->setStatus(InvoiceStatus::DRAFT);

        // Draft invoices don't require e-reporting yet
        $this->assertFalse($this->service->requiresEReporting($invoice));
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createB2CInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'Jean Dupont',
            customerAddress: '123 Rue de Paris, 75001 Paris',
            companyName: 'Ma Société SAS',
            companyAddress: '456 Avenue des Champs, 75008 Paris',
        );
        $invoice->setCustomerCountryCode('FR');
        $invoice->setStatus(InvoiceStatus::FINALIZED);

        return $invoice;
    }

    private function createB2BInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'Entreprise Client SARL',
            customerAddress: '789 Boulevard de Lyon, 69001 Lyon',
            companyName: 'Ma Société SAS',
            companyAddress: '456 Avenue des Champs, 75008 Paris',
        );
        $invoice->setCustomerCountryCode('FR');
        $invoice->setCustomerVatNumber('FR12345678901');
        $invoice->setStatus(InvoiceStatus::FINALIZED);

        return $invoice;
    }

    private function createB2BExportInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'US Company Inc',
            customerAddress: '100 Main Street, New York, NY 10001',
            companyName: 'Ma Société SAS',
            companyAddress: '456 Avenue des Champs, 75008 Paris',
        );
        $invoice->setCustomerCountryCode('US');
        $invoice->setCustomerVatNumber('123456789');
        $invoice->setStatus(InvoiceStatus::FINALIZED);

        return $invoice;
    }

    private function createB2CIntraEUInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'Hans Schmidt',
            customerAddress: 'Hauptstraße 1, 10117 Berlin',
            companyName: 'Ma Société SAS',
            companyAddress: '456 Avenue des Champs, 75008 Paris',
        );
        $invoice->setCustomerCountryCode('DE');
        $invoice->setStatus(InvoiceStatus::FINALIZED);

        return $invoice;
    }
}
