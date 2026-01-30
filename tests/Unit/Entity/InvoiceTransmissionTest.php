<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Entity;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceTransmission;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;

final class InvoiceTransmissionTest extends TestCase
{
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Avenue',
        );
        $this->invoice->setNumber('FA-2025-0001');
    }

    // ========================================
    // Constructor Tests
    // ========================================

    public function testConstructorSetsRequiredProperties(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertSame($this->invoice, $transmission->getInvoice());
        $this->assertSame('pennylane', $transmission->getConnectorId());
        $this->assertSame(PdpStatusCode::PENDING, $transmission->getStatus());
        $this->assertNull($transmission->getTransmissionId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $transmission->getCreatedAt());
    }

    public function testConstructorWithTransmissionId(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
            transmissionId: 'TX-2025-00001',
        );

        $this->assertSame('TX-2025-00001', $transmission->getTransmissionId());
    }

    public function testConstructorWithInitialStatus(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
            status: PdpStatusCode::SUBMITTED,
        );

        $this->assertSame(PdpStatusCode::SUBMITTED, $transmission->getStatus());
    }

    // ========================================
    // Transmission ID Tests
    // ========================================

    public function testSetTransmissionId(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->setTransmissionId('PDP-TX-12345');

        $this->assertSame('PDP-TX-12345', $transmission->getTransmissionId());
    }

    // ========================================
    // Status Tests
    // ========================================

    public function testUpdateStatus(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->updateStatus(PdpStatusCode::SUBMITTED, 'Invoice sent to PDP');

        $this->assertSame(PdpStatusCode::SUBMITTED, $transmission->getStatus());
        $this->assertSame('Invoice sent to PDP', $transmission->getStatusMessage());
        $this->assertInstanceOf(\DateTimeImmutable::class, $transmission->getStatusUpdatedAt());
    }

    public function testUpdateStatusRecordsHistory(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->updateStatus(PdpStatusCode::SUBMITTED, 'Sent');
        $transmission->updateStatus(PdpStatusCode::ACCEPTED, 'Validated by PDP');
        $transmission->updateStatus(PdpStatusCode::DELIVERED, 'Delivered to recipient');

        $history = $transmission->getStatusHistory();

        $this->assertCount(3, $history);
        $this->assertSame(PdpStatusCode::SUBMITTED->value, $history[0]['status']);
        $this->assertSame('Sent', $history[0]['message']);
        $this->assertSame(PdpStatusCode::ACCEPTED->value, $history[1]['status']);
        $this->assertSame(PdpStatusCode::DELIVERED->value, $history[2]['status']);
    }

    public function testUpdateStatusWithoutMessage(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->updateStatus(PdpStatusCode::SUBMITTED);

        $this->assertSame(PdpStatusCode::SUBMITTED, $transmission->getStatus());
        $this->assertNull($transmission->getStatusMessage());
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    public function testMarkAsFailedWithErrors(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->markAsFailed(
            message: 'Validation error',
            errors: ['Missing SIRET', 'Invalid VAT rate'],
        );

        $this->assertSame(PdpStatusCode::FAILED, $transmission->getStatus());
        $this->assertSame('Validation error', $transmission->getStatusMessage());
        $this->assertSame(['Missing SIRET', 'Invalid VAT rate'], $transmission->getErrors());
    }

    public function testMarkAsRejected(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $transmission->markAsRejected('Invoice rejected by recipient');

        $this->assertSame(PdpStatusCode::REJECTED, $transmission->getStatus());
        $this->assertSame('Invoice rejected by recipient', $transmission->getStatusMessage());
    }

    public function testGetErrorsEmptyByDefault(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertSame([], $transmission->getErrors());
    }

    // ========================================
    // Retry Tests
    // ========================================

    public function testIncrementRetryCount(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertSame(0, $transmission->getRetryCount());

        $transmission->incrementRetryCount();
        $this->assertSame(1, $transmission->getRetryCount());

        $transmission->incrementRetryCount();
        $this->assertSame(2, $transmission->getRetryCount());
    }

    public function testGetLastRetryAt(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertNull($transmission->getLastRetryAt());

        $transmission->incrementRetryCount();

        $this->assertInstanceOf(\DateTimeImmutable::class, $transmission->getLastRetryAt());
    }

    // ========================================
    // Terminal State Tests
    // ========================================

    public function testIsTerminal(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertFalse($transmission->isTerminal());

        $transmission->updateStatus(PdpStatusCode::SUBMITTED);
        $this->assertFalse($transmission->isTerminal());

        $transmission->updateStatus(PdpStatusCode::DELIVERED);
        $this->assertFalse($transmission->isTerminal());

        // Only PAID is terminal for successful transmissions
        $transmission->updateStatus(PdpStatusCode::PAID);
        $this->assertTrue($transmission->isTerminal());

        // FAILED is also terminal
        $transmission2 = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );
        $transmission2->updateStatus(PdpStatusCode::FAILED);
        $this->assertTrue($transmission2->isTerminal());
    }

    public function testIsPending(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        // PENDING status is pending
        $this->assertTrue($transmission->isPending());

        // SUBMITTED is still pending (in-progress)
        $transmission->updateStatus(PdpStatusCode::SUBMITTED);
        $this->assertTrue($transmission->isPending());

        // DELIVERED is still pending (awaiting payment)
        $transmission->updateStatus(PdpStatusCode::DELIVERED);
        $this->assertTrue($transmission->isPending());

        // Only terminal states are not pending
        $transmission->updateStatus(PdpStatusCode::PAID);
        $this->assertFalse($transmission->isPending());
    }

    public function testIsSuccessful(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        // PENDING is not successful
        $this->assertFalse($transmission->isSuccessful());

        // SUBMITTED is not successful (not yet accepted)
        $transmission->updateStatus(PdpStatusCode::SUBMITTED);
        $this->assertFalse($transmission->isSuccessful());

        // ACCEPTED is successful
        $transmission->updateStatus(PdpStatusCode::ACCEPTED);
        $this->assertTrue($transmission->isSuccessful());

        // DELIVERED is successful
        $transmission->updateStatus(PdpStatusCode::DELIVERED);
        $this->assertTrue($transmission->isSuccessful());

        // PAID is successful
        $transmission->updateStatus(PdpStatusCode::PAID);
        $this->assertTrue($transmission->isSuccessful());

        // FAILED is not successful
        $transmission2 = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );
        $transmission2->updateStatus(PdpStatusCode::FAILED);
        $this->assertFalse($transmission2->isSuccessful());
    }

    public function testIsFailed(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertFalse($transmission->isFailed());

        $transmission->updateStatus(PdpStatusCode::FAILED);
        $this->assertTrue($transmission->isFailed());

        $transmission->updateStatus(PdpStatusCode::REJECTED);
        $this->assertTrue($transmission->isFailed());
    }

    // ========================================
    // Metadata Tests
    // ========================================

    public function testSetAndGetMetadata(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $metadata = ['raw_response' => '{"id": 123}', 'api_version' => '2.0'];
        $transmission->setMetadata($metadata);

        $this->assertSame($metadata, $transmission->getMetadata());
    }

    public function testGetMetadataEmptyByDefault(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertSame([], $transmission->getMetadata());
    }

    // ========================================
    // ID Tests
    // ========================================

    public function testIdIsNullBeforePersistence(): void
    {
        $transmission = new InvoiceTransmission(
            invoice: $this->invoice,
            connectorId: 'pennylane',
        );

        $this->assertNull($transmission->getId());
    }
}
