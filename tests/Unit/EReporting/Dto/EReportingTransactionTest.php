<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Dto;

use CorentinBoutillier\InvoiceBundle\EReporting\Dto\EReportingTransaction;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\EReportingPaymentStatus;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\TransactionType;
use PHPUnit\Framework\TestCase;

final class EReportingTransactionTest extends TestCase
{
    public function testConstructorWithRequiredFields(): void
    {
        $transaction = new EReportingTransaction(
            invoiceNumber: 'FA-2025-0001',
            invoiceDate: new \DateTimeImmutable('2025-01-15'),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );

        $this->assertSame('FA-2025-0001', $transaction->invoiceNumber);
        $this->assertSame('2025-01-15', $transaction->invoiceDate->format('Y-m-d'));
        $this->assertSame(TransactionType::B2C_FRANCE, $transaction->transactionType);
        $this->assertSame('100.00', $transaction->totalExcludingVat);
        $this->assertSame('20.00', $transaction->totalVat);
        $this->assertSame('120.00', $transaction->totalIncludingVat);
        $this->assertNull($transaction->customerCountry);
        $this->assertNull($transaction->customerVatNumber);
        $this->assertSame(EReportingPaymentStatus::NOT_PAID, $transaction->paymentStatus);
        $this->assertNull($transaction->paymentDate);
        $this->assertNull($transaction->paymentMethod);
        $this->assertSame([], $transaction->vatBreakdown);
        $this->assertNull($transaction->invoiceId);
    }

    public function testConstructorWithAllFields(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');
        $paymentDate = new \DateTimeImmutable('2025-02-01');

        $transaction = new EReportingTransaction(
            invoiceNumber: 'FA-2025-0002',
            invoiceDate: $invoiceDate,
            transactionType: TransactionType::B2C_INTRA_EU,
            totalExcludingVat: '500.00',
            totalVat: '100.00',
            totalIncludingVat: '600.00',
            customerCountry: 'DE',
            customerVatNumber: 'DE123456789',
            paymentStatus: EReportingPaymentStatus::FULLY_PAID,
            paymentDate: $paymentDate,
            paymentMethod: 'virement',
            vatBreakdown: ['20.00' => '100.00'],
            invoiceId: '12345',
        );

        $this->assertSame('FA-2025-0002', $transaction->invoiceNumber);
        $this->assertSame('DE', $transaction->customerCountry);
        $this->assertSame('DE123456789', $transaction->customerVatNumber);
        $this->assertSame(EReportingPaymentStatus::FULLY_PAID, $transaction->paymentStatus);
        $this->assertSame($paymentDate, $transaction->paymentDate);
        $this->assertSame('virement', $transaction->paymentMethod);
        $this->assertSame(['20.00' => '100.00'], $transaction->vatBreakdown);
        $this->assertSame('12345', $transaction->invoiceId);
    }

    public function testRequiresEReporting(): void
    {
        // B2C France requires e-reporting
        $b2c = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertTrue($b2c->requiresEReporting());

        // B2B France does not require e-reporting (uses e-invoicing)
        $b2b = new EReportingTransaction(
            invoiceNumber: 'FA-002',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2B_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertFalse($b2b->requiresEReporting());
    }

    public function testIsExport(): void
    {
        $export = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_EXPORT,
            totalExcludingVat: '100.00',
            totalVat: '0.00',
            totalIncludingVat: '100.00',
        );
        $this->assertTrue($export->isExport());

        $domestic = new EReportingTransaction(
            invoiceNumber: 'FA-002',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertFalse($domestic->isExport());
    }

    public function testIsIntraEU(): void
    {
        $intraEU = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_INTRA_EU,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertTrue($intraEU->isIntraEU());
    }

    public function testIsDomestic(): void
    {
        $domestic = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertTrue($domestic->isDomestic());
    }

    public function testIsPaid(): void
    {
        $unpaid = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );
        $this->assertFalse($unpaid->isPaid());

        $paid = new EReportingTransaction(
            invoiceNumber: 'FA-002',
            invoiceDate: new \DateTimeImmutable(),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
            paymentStatus: EReportingPaymentStatus::FULLY_PAID,
        );
        $this->assertTrue($paid->isPaid());
    }

    public function testWithPayment(): void
    {
        $original = new EReportingTransaction(
            invoiceNumber: 'FA-001',
            invoiceDate: new \DateTimeImmutable('2025-01-15'),
            transactionType: TransactionType::B2C_FRANCE,
            totalExcludingVat: '100.00',
            totalVat: '20.00',
            totalIncludingVat: '120.00',
        );

        $paymentDate = new \DateTimeImmutable('2025-02-01');
        $paid = $original->withPayment(
            status: EReportingPaymentStatus::FULLY_PAID,
            date: $paymentDate,
            method: 'carte bancaire',
        );

        // Original unchanged
        $this->assertFalse($original->isPaid());
        $this->assertNull($original->paymentDate);

        // New instance has payment info
        $this->assertTrue($paid->isPaid());
        $this->assertSame($paymentDate, $paid->paymentDate);
        $this->assertSame('carte bancaire', $paid->paymentMethod);

        // Other fields preserved
        $this->assertSame('FA-001', $paid->invoiceNumber);
        $this->assertSame(TransactionType::B2C_FRANCE, $paid->transactionType);
    }
}
