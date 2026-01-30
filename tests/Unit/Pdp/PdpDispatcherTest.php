<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Pdp\Connector\NullConnector;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\HealthCheckResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\PdpInvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\ReceivedInvoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpConnectorNotFoundException;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpCapability;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpConnectorInterface;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpDispatcher;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;

final class PdpDispatcherTest extends TestCase
{
    // ========================================
    // Constructor Tests
    // ========================================

    public function testConstructorWithEmptyConnectors(): void
    {
        $dispatcher = new PdpDispatcher();

        $this->assertSame([], $dispatcher->getConnectorIds());
        $this->assertNull($dispatcher->getDefaultConnectorId());
    }

    public function testConstructorWithConnectors(): void
    {
        $connector1 = new NullConnector();
        $connector2 = $this->createMockConnector('test_connector');

        $dispatcher = new PdpDispatcher([$connector1, $connector2]);

        $this->assertCount(2, $dispatcher->getConnectorIds());
        $this->assertContains('null', $dispatcher->getConnectorIds());
        $this->assertContains('test_connector', $dispatcher->getConnectorIds());
    }

    public function testConstructorWithDefaultConnector(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $this->assertSame('null', $dispatcher->getDefaultConnectorId());
    }

    // ========================================
    // Registration Tests
    // ========================================

    public function testRegisterConnector(): void
    {
        $dispatcher = new PdpDispatcher();
        $connector = new NullConnector();

        $result = $dispatcher->registerConnector($connector);

        $this->assertSame($dispatcher, $result); // Fluent interface
        $this->assertTrue($dispatcher->hasConnector('null'));
    }

    public function testRegisterConnectorReplacesExisting(): void
    {
        $connector1 = new NullConnector();
        $connector2 = new NullConnector();

        $dispatcher = new PdpDispatcher([$connector1]);
        $dispatcher->registerConnector($connector2);

        $this->assertSame($connector2, $dispatcher->getConnector('null'));
    }

    // ========================================
    // Get Connector Tests
    // ========================================

    public function testGetConnector(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector]);

        $retrieved = $dispatcher->getConnector('null');

        $this->assertSame($connector, $retrieved);
    }

    public function testGetConnectorThrowsForUnknown(): void
    {
        $dispatcher = new PdpDispatcher();

        $this->expectException(PdpConnectorNotFoundException::class);
        $this->expectExceptionMessage('PDP connector "unknown" not found');

        $dispatcher->getConnector('unknown');
    }

    public function testGetDefaultConnector(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $default = $dispatcher->getDefaultConnector();

        $this->assertSame($connector, $default);
    }

    public function testGetDefaultConnectorThrowsWhenNotConfigured(): void
    {
        $dispatcher = new PdpDispatcher();

        $this->expectException(PdpConnectorNotFoundException::class);

        $dispatcher->getDefaultConnector();
    }

    // ========================================
    // Has/Get Connectors Tests
    // ========================================

    public function testHasConnector(): void
    {
        $dispatcher = new PdpDispatcher([new NullConnector()]);

        $this->assertTrue($dispatcher->hasConnector('null'));
        $this->assertFalse($dispatcher->hasConnector('unknown'));
    }

    public function testGetConnectors(): void
    {
        $connector1 = new NullConnector();
        $connector2 = $this->createMockConnector('test');

        $dispatcher = new PdpDispatcher([$connector1, $connector2]);

        $connectors = $dispatcher->getConnectors();

        $this->assertCount(2, $connectors);
        $this->assertSame($connector1, $connectors['null']);
        $this->assertSame($connector2, $connectors['test']);
    }

    public function testGetConfiguredConnectors(): void
    {
        $configured = new NullConnector(); // Always configured

        $unconfigured = $this->createMock(PdpConnectorInterface::class);
        $unconfigured->method('getId')->willReturn('unconfigured');
        $unconfigured->method('isConfigured')->willReturn(false);

        $dispatcher = new PdpDispatcher([$configured, $unconfigured]);

        $result = $dispatcher->getConfiguredConnectors();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('null', $result);
        $this->assertArrayNotHasKey('unconfigured', $result);
    }

    // ========================================
    // Transmit Tests
    // ========================================

    public function testTransmitUsesDefaultConnector(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $invoice = $this->createTestInvoice();
        $result = $dispatcher->transmit($invoice);

        $this->assertTrue($result->success);
    }

    public function testTransmitUsesSpecifiedConnector(): void
    {
        $defaultConnector = new NullConnector();
        $specificConnector = $this->createMockConnector('specific');
        $specificConnector->method('transmit')->willReturn(
            TransmissionResult::success('TX-SPECIFIC'),
        );

        $dispatcher = new PdpDispatcher([$defaultConnector, $specificConnector], 'null');

        $invoice = $this->createTestInvoice();
        $result = $dispatcher->transmit($invoice, 'specific');

        $this->assertSame('TX-SPECIFIC', $result->transmissionId);
    }

    public function testTransmitWithContentParams(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $invoice = $this->createTestInvoice();
        $result = $dispatcher->transmit(
            $invoice,
            pdfContent: 'PDF',
            xmlContent: 'XML',
        );

        $this->assertTrue($result->success);
    }

    // ========================================
    // Status Tests
    // ========================================

    public function testGetStatusUsesDefaultConnector(): void
    {
        $connector = new NullConnector();
        $connector->setStatus('TX-123', new PdpInvoiceStatus(
            transmissionId: 'TX-123',
            status: PdpStatusCode::DELIVERED,
            statusAt: new \DateTimeImmutable(),
        ));

        $dispatcher = new PdpDispatcher([$connector], 'null');

        $status = $dispatcher->getStatus('TX-123');

        $this->assertSame(PdpStatusCode::DELIVERED, $status->status);
    }

    public function testGetStatusUsesSpecifiedConnector(): void
    {
        $connector1 = new NullConnector();
        $connector2 = $this->createMockConnector('other');
        $connector2->method('getStatus')->willReturn(new PdpInvoiceStatus(
            transmissionId: 'TX-OTHER',
            status: PdpStatusCode::PAID,
            statusAt: new \DateTimeImmutable(),
        ));

        $dispatcher = new PdpDispatcher([$connector1, $connector2], 'null');

        $status = $dispatcher->getStatus('TX-OTHER', 'other');

        $this->assertSame(PdpStatusCode::PAID, $status->status);
    }

    // ========================================
    // Received Invoices Tests
    // ========================================

    public function testGetReceivedInvoicesUsesDefaultConnector(): void
    {
        $connector = new NullConnector();
        $connector->addReceivedInvoice(new ReceivedInvoice(
            transmissionId: 'RX-001',
            invoiceNumber: 'SUP-001',
            invoiceDate: new \DateTimeImmutable(),
            supplierName: 'Supplier',
            receivedAt: new \DateTimeImmutable(),
        ));

        $dispatcher = new PdpDispatcher([$connector], 'null');

        $invoices = $dispatcher->getReceivedInvoices();

        $this->assertCount(1, $invoices);
    }

    public function testGetReceivedInvoicesWithParams(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $invoices = $dispatcher->getReceivedInvoices(
            since: new \DateTimeImmutable('-7 days'),
            limit: 50,
        );

        $this->assertIsArray($invoices);
    }

    // ========================================
    // Health Check Tests
    // ========================================

    public function testHealthCheckUsesDefaultConnector(): void
    {
        $connector = new NullConnector();
        $dispatcher = new PdpDispatcher([$connector], 'null');

        $result = $dispatcher->healthCheck();

        $this->assertTrue($result->healthy);
        $this->assertSame('null', $result->connectorId);
    }

    public function testHealthCheckUsesSpecifiedConnector(): void
    {
        $connector1 = new NullConnector();
        $connector2 = $this->createMockConnector('other');
        $connector2->method('healthCheck')->willReturn(
            HealthCheckResult::healthy('other'),
        );

        $dispatcher = new PdpDispatcher([$connector1, $connector2], 'null');

        $result = $dispatcher->healthCheck('other');

        $this->assertSame('other', $result->connectorId);
    }

    public function testHealthCheckAll(): void
    {
        $connector1 = new NullConnector();
        $connector2 = $this->createMockConnector('other');
        $connector2->method('healthCheck')->willReturn(
            HealthCheckResult::unhealthy('other', 'Down for maintenance'),
        );

        $dispatcher = new PdpDispatcher([$connector1, $connector2]);

        $results = $dispatcher->healthCheckAll();

        $this->assertCount(2, $results);
        $this->assertTrue($results['null']->healthy);
        $this->assertFalse($results['other']->healthy);
    }

    // ========================================
    // Default Connector ID Tests
    // ========================================

    public function testSetDefaultConnectorId(): void
    {
        $dispatcher = new PdpDispatcher([new NullConnector()]);

        $result = $dispatcher->setDefaultConnectorId('null');

        $this->assertSame($dispatcher, $result); // Fluent
        $this->assertSame('null', $dispatcher->getDefaultConnectorId());
    }

    public function testSetDefaultConnectorIdToNull(): void
    {
        $dispatcher = new PdpDispatcher([new NullConnector()], 'null');

        $dispatcher->setDefaultConnectorId(null);

        $this->assertNull($dispatcher->getDefaultConnectorId());
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

    private function createMockConnector(string $id): PdpConnectorInterface
    {
        $mock = $this->createMock(PdpConnectorInterface::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getName')->willReturn("Mock {$id}");
        $mock->method('isConfigured')->willReturn(true);
        $mock->method('getCapabilities')->willReturn([PdpCapability::TRANSMIT]);
        $mock->method('supports')->willReturn(true);

        return $mock;
    }
}
