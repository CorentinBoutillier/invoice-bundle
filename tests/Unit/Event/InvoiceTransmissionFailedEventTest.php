<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmissionFailedEvent;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class InvoiceTransmissionFailedEventTest extends TestCase
{
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable(),
            dueDate: new \DateTimeImmutable('+30 days'),
            customerName: 'Test Customer',
            customerAddress: '123 Test St',
            companyName: 'Test Company',
            companyAddress: '456 Company St',
        );
    }

    public function testExtendsEvent(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetInvoice(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertSame($this->invoice, $event->getInvoice());
    }

    public function testGetResultWithTransmissionResult(): void
    {
        $result = TransmissionResult::failure(
            status: PdpStatusCode::FAILED,
            message: 'API Error',
            errors: ['Connection refused'],
        );

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: $result,
            connectorId: 'pennylane',
        );

        $this->assertSame($result, $event->getResult());
    }

    public function testGetResultReturnsNull(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertNull($event->getResult());
    }

    public function testGetConnectorId(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'chorus_pro',
        );

        $this->assertSame('chorus_pro', $event->getConnectorId());
    }

    public function testGetErrorMessageFromExplicitMessage(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
            errorMessage: 'Custom error message',
        );

        $this->assertSame('Custom error message', $event->getErrorMessage());
    }

    public function testGetErrorMessageFromResult(): void
    {
        $result = TransmissionResult::failure(
            status: PdpStatusCode::FAILED,
            message: 'API returned 500',
        );

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: $result,
            connectorId: 'pennylane',
        );

        $this->assertSame('API returned 500', $event->getErrorMessage());
    }

    public function testGetErrorMessageFromException(): void
    {
        $exception = new \RuntimeException('Connection timed out');

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
            exception: $exception,
        );

        $this->assertSame('Connection timed out', $event->getErrorMessage());
    }

    public function testGetErrorMessagePriority(): void
    {
        $result = TransmissionResult::failure(
            status: PdpStatusCode::FAILED,
            message: 'Result message',
        );
        $exception = new \RuntimeException('Exception message');

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: $result,
            connectorId: 'pennylane',
            errorMessage: 'Explicit message',
            exception: $exception,
        );

        // Explicit message takes priority
        $this->assertSame('Explicit message', $event->getErrorMessage());
    }

    public function testGetErrorMessageReturnsNullWhenNoSource(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertNull($event->getErrorMessage());
    }

    public function testGetException(): void
    {
        $exception = new \InvalidArgumentException('Invalid invoice data');

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
            exception: $exception,
        );

        $this->assertSame($exception, $event->getException());
    }

    public function testGetExceptionReturnsNull(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertNull($event->getException());
    }

    public function testGetErrors(): void
    {
        $result = TransmissionResult::failure(
            status: PdpStatusCode::REJECTED,
            message: 'Validation failed',
            errors: ['Missing SIRET', 'Invalid VAT rate'],
        );

        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: $result,
            connectorId: 'pennylane',
        );

        $this->assertSame(['Missing SIRET', 'Invalid VAT rate'], $event->getErrors());
    }

    public function testGetErrorsReturnsEmptyArrayWhenNoResult(): void
    {
        $event = new InvoiceTransmissionFailedEvent(
            invoice: $this->invoice,
            result: null,
            connectorId: 'pennylane',
        );

        $this->assertSame([], $event->getErrors());
    }
}
