<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmittedEvent;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class InvoiceTransmittedEventTest extends TestCase
{
    private Invoice $invoice;
    private TransmissionResult $result;
    private InvoiceTransmittedEvent $event;

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

        $this->result = TransmissionResult::success(
            transmissionId: 'TX-2025-001',
            status: PdpStatusCode::SUBMITTED,
            message: 'Invoice transmitted successfully',
        );

        $this->event = new InvoiceTransmittedEvent(
            invoice: $this->invoice,
            result: $this->result,
            connectorId: 'pennylane',
        );
    }

    public function testExtendsEvent(): void
    {
        $this->assertInstanceOf(Event::class, $this->event);
    }

    public function testGetInvoice(): void
    {
        $this->assertSame($this->invoice, $this->event->getInvoice());
    }

    public function testGetResult(): void
    {
        $this->assertSame($this->result, $this->event->getResult());
    }

    public function testGetConnectorId(): void
    {
        $this->assertSame('pennylane', $this->event->getConnectorId());
    }

    public function testGetTransmissionId(): void
    {
        $this->assertSame('TX-2025-001', $this->event->getTransmissionId());
    }

    public function testEventContainsSuccessfulResult(): void
    {
        $this->assertTrue($this->event->getResult()->success);
    }
}
