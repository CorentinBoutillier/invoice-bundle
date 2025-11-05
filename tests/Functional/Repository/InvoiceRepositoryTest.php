<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Entity\Payment;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository;

final class InvoiceRepositoryTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->entityManager->getRepository(Invoice::class);
        if (!$repository instanceof InvoiceRepository) {
            throw new \RuntimeException('InvoiceRepository not found');
        }
        $this->repository = $repository;
    }

    // ========== findForFecExport() ==========

    public function testFindForFecExportReturnsOnlyFinalizedInvoices(): void
    {
        // Create 3 finalized invoices
        for ($i = 1; $i <= 3; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-{$i}"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-000{$i}",
            );
            $this->entityManager->persist($invoice);
        }

        // Create 2 draft invoices
        for ($i = 4; $i <= 5; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-{$i}"),
                status: InvoiceStatus::DRAFT,
                number: null,
            );
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();

        // Export all invoices
        $result = $this->repository->findForFecExport(
            startDate: new \DateTimeImmutable('2024-06-01'),
            endDate: new \DateTimeImmutable('2024-06-30'),
            companyId: null,
        );

        // Only finalized invoices should be returned
        $this->assertCount(3, $result);

        foreach ($result as $invoice) {
            $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
        }
    }

    public function testFindForFecExportFiltersByDateRange(): void
    {
        // Create invoice in May
        $invoiceMay = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-05-15'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
        );
        $this->entityManager->persist($invoiceMay);

        // Create invoices in June
        $invoiceJune1 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-10'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0002',
        );
        $this->entityManager->persist($invoiceJune1);

        $invoiceJune2 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-20'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0003',
        );
        $this->entityManager->persist($invoiceJune2);

        // Create invoice in July
        $invoiceJuly = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-07-05'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0004',
        );
        $this->entityManager->persist($invoiceJuly);

        $this->entityManager->flush();

        // Export only June invoices
        $result = $this->repository->findForFecExport(
            startDate: new \DateTimeImmutable('2024-06-01'),
            endDate: new \DateTimeImmutable('2024-06-30'),
            companyId: null,
        );

        $this->assertCount(2, $result);

        $numbers = array_map(fn (Invoice $inv) => $inv->getNumber(), $result);
        $this->assertContains('FA-2024-0002', $numbers);
        $this->assertContains('FA-2024-0003', $numbers);
    }

    public function testFindForFecExportFiltersByCompanyId(): void
    {
        // Create invoices for company 1
        $invoice1 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
            companyId: 1,
        );
        $this->entityManager->persist($invoice1);

        // Create invoices for company 2
        $invoice2 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-02'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0002',
            companyId: 2,
        );
        $this->entityManager->persist($invoice2);

        $this->entityManager->flush();

        // Export only company 1 invoices
        $result = $this->repository->findForFecExport(
            startDate: new \DateTimeImmutable('2024-06-01'),
            endDate: new \DateTimeImmutable('2024-06-30'),
            companyId: 1,
        );

        $this->assertCount(1, $result);
        $this->assertSame('FA-2024-0001', $result[0]->getNumber());
    }

    // ========== findOverdueInvoices() ==========

    public function testFindOverdueInvoicesReturnsOnlyOverdueUnpaid(): void
    {
        $today = new \DateTimeImmutable();
        $yesterday = $today->modify('-1 day');
        $tomorrow = $today->modify('+1 day');

        // Overdue and unpaid (should be returned)
        $overdueUnpaid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $yesterday->modify('-30 days'),
            dueDate: $yesterday,
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
        );
        $this->entityManager->persist($overdueUnpaid);

        // Overdue but paid (should NOT be returned)
        $overduePaid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $yesterday->modify('-30 days'),
            dueDate: $yesterday,
            status: InvoiceStatus::PAID,
            number: 'FA-2024-0002',
        );
        $this->entityManager->persist($overduePaid);

        // Not yet due (should NOT be returned)
        $notDue = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $today,
            dueDate: $tomorrow,
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0003',
        );
        $this->entityManager->persist($notDue);

        // Cancelled (should NOT be returned)
        $cancelled = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $yesterday->modify('-30 days'),
            dueDate: $yesterday,
            status: InvoiceStatus::CANCELLED,
            number: 'FA-2024-0004',
        );
        $this->entityManager->persist($cancelled);

        $this->entityManager->flush();

        $result = $this->repository->findOverdueInvoices($today, null);

        $this->assertCount(1, $result);
        $this->assertSame('FA-2024-0001', $result[0]->getNumber());
    }

    public function testFindOverdueInvoicesSupportsCustomReferenceDate(): void
    {
        $referenceDate = new \DateTimeImmutable('2024-06-15');
        $dayBefore = $referenceDate->modify('-1 day');
        $dayAfter = $referenceDate->modify('+1 day');

        // Due before reference date
        $overdue = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $dayBefore->modify('-30 days'),
            dueDate: $dayBefore,
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
        );
        $this->entityManager->persist($overdue);

        // Due after reference date
        $notOverdue = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: $referenceDate,
            dueDate: $dayAfter,
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0002',
        );
        $this->entityManager->persist($notOverdue);

        $this->entityManager->flush();

        $result = $this->repository->findOverdueInvoices($referenceDate, null);

        $this->assertCount(1, $result);
        $this->assertSame('FA-2024-0001', $result[0]->getNumber());
    }

    // ========== findByStatus() ==========

    public function testFindByStatusReturnsCorrectInvoices(): void
    {
        // Create invoices with different statuses
        $draft = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            status: InvoiceStatus::DRAFT,
            number: null,
        );
        $this->entityManager->persist($draft);

        $finalized1 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-02'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
        );
        $this->entityManager->persist($finalized1);

        $finalized2 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-03'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0002',
        );
        $this->entityManager->persist($finalized2);

        $paid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-04'),
            status: InvoiceStatus::PAID,
            number: 'FA-2024-0003',
        );
        $this->entityManager->persist($paid);

        $this->entityManager->flush();

        // Find only finalized invoices
        $result = $this->repository->findByStatus(InvoiceStatus::FINALIZED, null, null, null);

        $this->assertCount(2, $result);

        foreach ($result as $invoice) {
            $this->assertSame(InvoiceStatus::FINALIZED, $invoice->getStatus());
        }
    }

    public function testFindByStatusSupportsLimitAndOffset(): void
    {
        // Create 5 finalized invoices
        for ($i = 1; $i <= 5; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-0{$i}"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-000{$i}",
            );
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();

        // Get first 2 invoices
        $page1 = $this->repository->findByStatus(InvoiceStatus::FINALIZED, null, 2, 0);
        $this->assertCount(2, $page1);

        // Get next 2 invoices
        $page2 = $this->repository->findByStatus(InvoiceStatus::FINALIZED, null, 2, 2);
        $this->assertCount(2, $page2);

        // Verify no overlap
        $this->assertNotSame($page1[0]->getId(), $page2[0]->getId());
    }

    // ========== findByCustomer() ==========

    public function testFindByCustomerUsesSnapshot(): void
    {
        // Create 3 invoices for same customer name
        for ($i = 1; $i <= 3; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-0{$i}"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-000{$i}",
                customerName: 'ACME Corp',
            );
            $this->entityManager->persist($invoice);
        }

        // Create invoice for different customer
        $otherInvoice = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-10'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0010',
            customerName: 'Other Company',
        );
        $this->entityManager->persist($otherInvoice);

        $this->entityManager->flush();

        // Find by customer name
        $result = $this->repository->findByCustomer('ACME Corp', null);

        $this->assertCount(3, $result);

        foreach ($result as $invoice) {
            $this->assertSame('ACME Corp', $invoice->getCustomerName());
        }
    }

    public function testFindByCustomerWithSiret(): void
    {
        // Create invoice with SIRET
        $invoice1 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
            customerName: 'ACME Corp',
            customerSiret: '12345678901234',
        );
        $this->entityManager->persist($invoice1);

        // Create invoice with same name but different SIRET
        $invoice2 = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-02'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0002',
            customerName: 'ACME Corp',
            customerSiret: '98765432109876',
        );
        $this->entityManager->persist($invoice2);

        $this->entityManager->flush();

        // Find by customer name AND SIRET
        $result = $this->repository->findByCustomer('ACME Corp', '12345678901234');

        $this->assertCount(1, $result);
        $this->assertSame('FA-2024-0001', $result[0]->getNumber());
    }

    // ========== findByDateRange() ==========

    public function testFindByDateRangeReturnsCorrectInvoices(): void
    {
        // Create invoices in different months
        $invoicesMay = [];
        for ($i = 1; $i <= 3; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-05-{$i}0"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-MAY-{$i}",
            );
            $this->entityManager->persist($invoice);
            $invoicesMay[] = $invoice;
        }

        $invoicesJune = [];
        for ($i = 1; $i <= 2; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-{$i}0"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-JUN-{$i}",
            );
            $this->entityManager->persist($invoice);
            $invoicesJune[] = $invoice;
        }

        $this->entityManager->flush();

        // Find only May invoices
        $result = $this->repository->findByDateRange(
            start: new \DateTimeImmutable('2024-05-01'),
            end: new \DateTimeImmutable('2024-05-31'),
            companyId: null,
        );

        $this->assertCount(3, $result);

        foreach ($result as $invoice) {
            $number = $invoice->getNumber();
            $this->assertNotNull($number);
            $this->assertStringContainsString('MAY', $number);
        }
    }

    // ========== findByNumber() ==========

    public function testFindByNumberReturnsCorrectInvoice(): void
    {
        $invoice = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0042',
        );
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $result = $this->repository->findByNumber('FA-2024-0042');

        $this->assertNotNull($result);
        $this->assertSame('FA-2024-0042', $result->getNumber());
    }

    public function testFindByNumberReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByNumber('NONEXISTENT');

        $this->assertNull($result);
    }

    // ========== findUnpaidInvoices() ==========

    public function testFindUnpaidInvoicesReturnsInvoicesWithBalance(): void
    {
        // Unpaid invoice
        $unpaid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-01'),
            status: InvoiceStatus::FINALIZED,
            number: 'FA-2024-0001',
        );
        $this->addLineToInvoice($unpaid, 100.00, 20.0); // 120€ TTC
        $this->entityManager->persist($unpaid);

        // Partially paid invoice
        $partiallyPaid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-02'),
            status: InvoiceStatus::PARTIALLY_PAID,
            number: 'FA-2024-0002',
        );
        $this->addLineToInvoice($partiallyPaid, 100.00, 20.0); // 120€ TTC
        $payment = new Payment(
            amount: Money::fromCents(6000), // 60€
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $partiallyPaid->addPayment($payment);
        $this->entityManager->persist($partiallyPaid);
        $this->entityManager->persist($payment);

        // Fully paid invoice (should NOT be returned)
        $fullyPaid = $this->createInvoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-03'),
            status: InvoiceStatus::PAID,
            number: 'FA-2024-0003',
        );
        $this->addLineToInvoice($fullyPaid, 100.00, 20.0); // 120€ TTC
        $paymentFull = new Payment(
            amount: Money::fromCents(12000), // 120€
            paidAt: new \DateTimeImmutable(),
            method: PaymentMethod::BANK_TRANSFER,
        );
        $fullyPaid->addPayment($paymentFull);
        $this->entityManager->persist($fullyPaid);
        $this->entityManager->persist($paymentFull);

        $this->entityManager->flush();

        $result = $this->repository->findUnpaidInvoices(null);

        $this->assertCount(2, $result);

        $numbers = array_map(fn (Invoice $inv) => $inv->getNumber(), $result);
        $this->assertContains('FA-2024-0001', $numbers);
        $this->assertContains('FA-2024-0002', $numbers);
        $this->assertNotContains('FA-2024-0003', $numbers);
    }

    // ========== countByFiscalYear() ==========

    public function testCountByFiscalYearReturnsCorrectCount(): void
    {
        // Create 3 invoices in fiscal year 2024
        for ($i = 1; $i <= 3; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2024-06-{$i}"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2024-000{$i}",
                fiscalYear: 2024,
            );
            $this->entityManager->persist($invoice);
        }

        // Create 2 invoices in fiscal year 2025
        for ($i = 1; $i <= 2; ++$i) {
            $invoice = $this->createInvoice(
                type: InvoiceType::INVOICE,
                date: new \DateTimeImmutable("2025-01-{$i}"),
                status: InvoiceStatus::FINALIZED,
                number: "FA-2025-000{$i}",
                fiscalYear: 2025,
            );
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();

        $count2024 = $this->repository->countByFiscalYear(2024, null);
        $count2025 = $this->repository->countByFiscalYear(2025, null);

        $this->assertSame(3, $count2024);
        $this->assertSame(2, $count2025);
    }

    // ========== Helper Methods ==========

    private function createInvoice(
        InvoiceType $type,
        \DateTimeImmutable $date,
        InvoiceStatus $status = InvoiceStatus::DRAFT,
        ?string $number = null,
        ?\DateTimeImmutable $dueDate = null,
        ?int $companyId = null,
        string $customerName = 'Test Customer',
        ?string $customerSiret = null,
        ?int $fiscalYear = null,
    ): Invoice {
        $invoice = new Invoice(
            type: $type,
            date: $date,
            dueDate: $dueDate ?? $date->modify('+30 days'),
            customerName: $customerName,
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        // Use setter for customerSiret
        if (null !== $customerSiret) {
            $invoice->setCustomerSiret($customerSiret);
        }

        // Use reflection to set protected properties
        $reflection = new \ReflectionClass($invoice);

        if (null !== $number) {
            $numberProperty = $reflection->getProperty('number');
            $numberProperty->setValue($invoice, $number);
        }

        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setValue($invoice, $status);

        if (null !== $companyId) {
            $companyIdProperty = $reflection->getProperty('companyId');
            $companyIdProperty->setValue($invoice, $companyId);
        }

        if (null !== $fiscalYear) {
            $fiscalYearProperty = $reflection->getProperty('fiscalYear');
            $fiscalYearProperty->setValue($invoice, $fiscalYear);
        }

        return $invoice;
    }

    private function addLineToInvoice(Invoice $invoice, float $unitPrice, float $vatRate): void
    {
        $line = new InvoiceLine(
            description: 'Test Product',
            quantity: 1.0,
            unitPrice: Money::fromCents((int) ($unitPrice * 100)),
            vatRate: $vatRate,
        );
        $invoice->addLine($line);
    }
}
