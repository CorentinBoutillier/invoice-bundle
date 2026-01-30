<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp\Connector;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Pdp\Connector\NullConnector;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpCapability;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpConnectorInterface;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;

final class NullConnectorTest extends TestCase
{
    private NullConnector $connector;

    protected function setUp(): void
    {
        $this->connector = new NullConnector();
    }

    // ========================================
    // Basic Interface Tests
    // ========================================

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(PdpConnectorInterface::class, $this->connector);
    }

    public function testGetId(): void
    {
        $this->assertSame('null', $this->connector->getId());
    }

    public function testGetName(): void
    {
        $this->assertSame('Null Connector (Test/Fallback)', $this->connector->getName());
    }

    public function testGetCapabilities(): void
    {
        $capabilities = $this->connector->getCapabilities();

        $this->assertContains(PdpCapability::TRANSMIT, $capabilities);
        $this->assertContains(PdpCapability::STATUS, $capabilities);
        $this->assertContains(PdpCapability::RECEIVE, $capabilities);
        $this->assertContains(PdpCapability::HEALTH_CHECK, $capabilities);
        $this->assertCount(4, $capabilities);
    }

    public function testSupportsCapability(): void
    {
        $this->assertTrue($this->connector->supports(PdpCapability::TRANSMIT));
        $this->assertTrue($this->connector->supports(PdpCapability::STATUS));
        $this->assertTrue($this->connector->supports(PdpCapability::RECEIVE));
        $this->assertTrue($this->connector->supports(PdpCapability::HEALTH_CHECK));
        $this->assertFalse($this->connector->supports(PdpCapability::WEBHOOKS));
        $this->assertFalse($this->connector->supports(PdpCapability::E_REPORTING));
    }

    public function testIsConfigured(): void
    {
        $this->assertTrue($this->connector->isConfigured());
    }

    // ========================================
    // Transmit Tests
    // ========================================

    public function testTransmitSuccess(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setNumber('FA-2025-0001');

        $result = $this->connector->transmit($invoice);

        $this->assertTrue($result->success);
        $this->assertSame(PdpStatusCode::SUBMITTED, $result->status);
        $this->assertNotNull($result->transmissionId);
        $this->assertStringStartsWith('NULL-', $result->transmissionId);
        $this->assertStringContainsString('FA-2025-0001', $result->transmissionId);
        $this->assertSame(['connector' => 'null', 'simulated' => true], $result->metadata);
    }

    public function testTransmitWithPdfAndXmlContent(): void
    {
        $invoice = $this->createTestInvoice();

        $result = $this->connector->transmit(
            $invoice,
            pdfContent: 'PDF_CONTENT',
            xmlContent: '<?xml version="1.0"?>',
        );

        $this->assertTrue($result->success);
    }

    public function testTransmitSimulatedFailure(): void
    {
        $invoice = $this->createTestInvoice();

        $this->connector->simulateFailure(true, 'Simulated API error');
        $result = $this->connector->transmit($invoice);

        $this->assertFalse($result->success);
        $this->assertSame(PdpStatusCode::FAILED, $result->status);
        $this->assertSame('Simulated API error', $result->message);
    }

    // ========================================
    // Status Tests
    // ========================================

    public function testGetStatusAfterTransmit(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setNumber('FA-2025-0002');

        $transmitResult = $this->connector->transmit($invoice);
        $status = $this->connector->getStatus($transmitResult->transmissionId);

        $this->assertSame($transmitResult->transmissionId, $status->transmissionId);
        $this->assertSame(PdpStatusCode::SUBMITTED, $status->status);
    }

    public function testGetStatusForUnknownTransmission(): void
    {
        $status = $this->connector->getStatus('UNKNOWN-ID');

        $this->assertSame('UNKNOWN-ID', $status->transmissionId);
        $this->assertSame(PdpStatusCode::PENDING, $status->status);
    }

    public function testSetStatusManually(): void
    {
        $customStatus = new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: PdpStatusCode::DELIVERED,
            statusAt: new \DateTimeImmutable(),
            message: 'Delivered to recipient',
        );

        $this->connector->setStatus('TX-123', $customStatus);
        $retrieved = $this->connector->getStatus('TX-123');

        $this->assertSame(PdpStatusCode::DELIVERED, $retrieved->status);
        $this->assertSame('Delivered to recipient', $retrieved->message);
    }

    // ========================================
    // Received Invoices Tests
    // ========================================

    public function testGetReceivedInvoicesEmpty(): void
    {
        $invoices = $this->connector->getReceivedInvoices();

        $this->assertSame([], $invoices);
    }

    public function testGetReceivedInvoicesWithData(): void
    {
        $invoice1 = new ReceivedInvoice(
            transmissionId: 'RX-001',
            invoiceNumber: 'SUP-001',
            invoiceDate: new \DateTimeImmutable('2025-01-01'),
            supplierName: 'Supplier 1',
            receivedAt: new \DateTimeImmutable('2025-01-02'),
        );

        $invoice2 = new ReceivedInvoice(
            transmissionId: 'RX-002',
            invoiceNumber: 'SUP-002',
            invoiceDate: new \DateTimeImmutable('2025-01-05'),
            supplierName: 'Supplier 2',
            receivedAt: new \DateTimeImmutable('2025-01-06'),
        );

        $this->connector->addReceivedInvoice($invoice1);
        $this->connector->addReceivedInvoice($invoice2);

        $invoices = $this->connector->getReceivedInvoices();

        $this->assertCount(2, $invoices);
    }

    public function testGetReceivedInvoicesWithSinceFilter(): void
    {
        $oldInvoice = new ReceivedInvoice(
            transmissionId: 'RX-OLD',
            invoiceNumber: 'OLD-001',
            invoiceDate: new \DateTimeImmutable('2025-01-01'),
            supplierName: 'Old Supplier',
            receivedAt: new \DateTimeImmutable('2025-01-02'),
        );

        $newInvoice = new ReceivedInvoice(
            transmissionId: 'RX-NEW',
            invoiceNumber: 'NEW-001',
            invoiceDate: new \DateTimeImmutable('2025-01-15'),
            supplierName: 'New Supplier',
            receivedAt: new \DateTimeImmutable('2025-01-16'),
        );

        $this->connector->addReceivedInvoice($oldInvoice);
        $this->connector->addReceivedInvoice($newInvoice);

        $since = new \DateTimeImmutable('2025-01-10');
        $invoices = $this->connector->getReceivedInvoices($since);

        $this->assertCount(1, $invoices);
        $this->assertSame('RX-NEW', $invoices[0]->transmissionId);
    }

    public function testGetReceivedInvoicesWithLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->connector->addReceivedInvoice(new ReceivedInvoice(
                transmissionId: "RX-{$i}",
                invoiceNumber: "SUP-{$i}",
                invoiceDate: new \DateTimeImmutable(),
                supplierName: "Supplier {$i}",
                receivedAt: new \DateTimeImmutable(),
            ));
        }

        $invoices = $this->connector->getReceivedInvoices(limit: 3);

        $this->assertCount(3, $invoices);
    }

    // ========================================
    // Health Check Tests
    // ========================================

    public function testHealthCheckSuccess(): void
    {
        $result = $this->connector->healthCheck();

        $this->assertTrue($result->healthy);
        $this->assertSame('null', $result->connectorId);
        $this->assertSame('Connection successful', $result->message);
        $this->assertSame('1.0.0-null', $result->version);
        $this->assertSame(['simulated' => true], $result->details);
    }

    public function testHealthCheckSimulatedFailure(): void
    {
        $this->connector->simulateFailure(true, 'Simulated outage');
        $result = $this->connector->healthCheck();

        $this->assertFalse($result->healthy);
        $this->assertSame('Simulated outage', $result->message);
    }

    // ========================================
    // Reset Tests
    // ========================================

    public function testReset(): void
    {
        // Add some state
        $invoice = $this->createTestInvoice();
        $this->connector->transmit($invoice);
        $this->connector->addReceivedInvoice(new ReceivedInvoice(
            transmissionId: 'RX-1',
            invoiceNumber: 'SUP-1',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            receivedAt: new \DateTimeImmutable(),
        ));
        $this->connector->simulateFailure(true);

        // Reset
        $this->connector->reset();

        // Verify reset
        $this->assertSame([], $this->connector->getReceivedInvoices());
        $this->assertTrue($this->connector->healthCheck()->healthy);

        // Transmit should work again
        $newInvoice = $this->createTestInvoice();
        $result = $this->connector->transmit($newInvoice);
        $this->assertTrue($result->success);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createTestInvoice(): Invoice
    {
        return new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company St',
        );
    }
}
