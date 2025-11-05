<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceHistory;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceHistoryAction;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use PHPUnit\Framework\TestCase;

final class InvoiceHistoryTest extends TestCase
{
    // ========== Construction & Basic Properties ==========

    public function testConstructWithAllRequiredProperties(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $executedAt = new \DateTimeImmutable('2024-06-15 10:30:00');

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: $executedAt,
        );

        $this->assertInstanceOf(InvoiceHistory::class, $history);
        $this->assertSame($invoice, $history->getInvoice());
        $this->assertSame(InvoiceHistoryAction::CREATED, $history->getAction());
        $this->assertSame($executedAt, $history->getExecutedAt());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertNull($history->getId());
    }

    // ========== Actions Enum ==========

    public function testActionCreated(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(InvoiceHistoryAction::CREATED, $history->getAction());
    }

    public function testActionFinalized(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::FINALIZED,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(InvoiceHistoryAction::FINALIZED, $history->getAction());
    }

    public function testActionPaid(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::PAID,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(InvoiceHistoryAction::PAID, $history->getAction());
    }

    // ========== Optional Fields: User ID ==========

    public function testUserIdIsNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertNull($history->getUserId());
    }

    public function testUserIdCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $history->setUserId(42);

        $this->assertSame(42, $history->getUserId());
    }

    // ========== Optional Fields: Metadata ==========

    public function testMetadataIsNullByDefault(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertNull($history->getMetadata());
    }

    public function testMetadataCanBeSet(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::PAYMENT_RECEIVED,
            executedAt: new \DateTimeImmutable(),
        );

        $metadata = [
            'amount' => '100.00',
            'method' => 'BANK_TRANSFER',
            'reference' => 'TXN-123456',
        ];
        $history->setMetadata($metadata);

        $this->assertSame($metadata, $history->getMetadata());
    }

    public function testMetadataCanBeEmpty(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable(),
        );

        $history->setMetadata([]);

        $this->assertSame([], $history->getMetadata());
    }

    // ========== Real-World Scenarios ==========

    public function testCreatedActionWithUserAndMetadata(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::CREATED,
            executedAt: new \DateTimeImmutable('2024-06-15 10:00:00'),
        );

        $history->setUserId(1);
        $history->setMetadata([
            'ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame(InvoiceHistoryAction::CREATED, $history->getAction());
        $this->assertSame(1, $history->getUserId());
        $this->assertIsArray($history->getMetadata());
        $this->assertArrayHasKey('ip', $history->getMetadata());
    }

    public function testFinalizedActionWithInvoiceNumber(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::FINALIZED,
            executedAt: new \DateTimeImmutable('2024-06-15 14:30:00'),
        );

        $history->setUserId(1);
        $history->setMetadata([
            'invoice_number' => 'FA-2024-0042',
            'pdf_path' => '/var/invoices/2024/06/FA-2024-0042.pdf',
        ]);

        $this->assertSame(InvoiceHistoryAction::FINALIZED, $history->getAction());
        $metadata = $history->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertSame('FA-2024-0042', $metadata['invoice_number']);
    }

    public function testPaymentReceivedActionWithDetails(): void
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2024-06-15'),
            dueDate: new \DateTimeImmutable('2024-07-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company Ave',
        );

        $history = new InvoiceHistory(
            invoice: $invoice,
            action: InvoiceHistoryAction::PAYMENT_RECEIVED,
            executedAt: new \DateTimeImmutable('2024-06-20 09:15:00'),
        );

        $history->setMetadata([
            'payment_id' => 123,
            'amount_cents' => 12000,
            'method' => 'BANK_TRANSFER',
            'reference' => 'VIREMENT-20240620-001',
        ]);

        $this->assertSame(InvoiceHistoryAction::PAYMENT_RECEIVED, $history->getAction());
        $metadata = $history->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertSame(12000, $metadata['amount_cents']);
    }
}
