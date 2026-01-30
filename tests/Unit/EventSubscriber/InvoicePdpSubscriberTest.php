<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmissionFailedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmittedEvent;
use CorentinBoutillier\InvoiceBundle\EventSubscriber\InvoicePdpSubscriber;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpConnectorNotFoundException;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpTransmissionException;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpDispatcherInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\PdfStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class InvoicePdpSubscriberTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    private MockObject&PdpDispatcherInterface $pdpDispatcher;
    /** @phpstan-ignore property.uninitialized */
    private MockObject&PdfStorageInterface $pdfStorage;
    /** @phpstan-ignore property.uninitialized */
    private MockObject&EventDispatcherInterface $eventDispatcher;
    /** @phpstan-ignore property.uninitialized */
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->pdpDispatcher = $this->createMock(PdpDispatcherInterface::class);
        $this->pdfStorage = $this->createMock(PdfStorageInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->invoice = $this->createInvoice();
    }

    // ========================================
    // Configuration Tests
    // ========================================

    public function testGetSubscribedEvents(): void
    {
        $events = InvoicePdpSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(InvoiceFinalizedEvent::class, $events);
        $this->assertSame('onInvoiceFinalized', $events[InvoiceFinalizedEvent::class][0]);
        $this->assertSame(-100, $events[InvoiceFinalizedEvent::class][1]);
    }

    public function testDoesNothingWhenPdpDisabled(): void
    {
        $subscriber = new InvoicePdpSubscriber(
            pdpDispatcher: $this->pdpDispatcher,
            pdfStorage: $this->pdfStorage,
            eventDispatcher: $this->eventDispatcher,
            pdpEnabled: false,
            autoSendOnFinalize: true,
        );

        $this->pdpDispatcher->expects($this->never())->method('transmit');

        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    public function testDoesNothingWhenAutoSendDisabled(): void
    {
        $subscriber = new InvoicePdpSubscriber(
            pdpDispatcher: $this->pdpDispatcher,
            pdfStorage: $this->pdfStorage,
            eventDispatcher: $this->eventDispatcher,
            pdpEnabled: true,
            autoSendOnFinalize: false,
        );

        $this->pdpDispatcher->expects($this->never())->method('transmit');

        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    // ========================================
    // Transmission Tests
    // ========================================

    public function testTransmitsInvoiceWhenEnabled(): void
    {
        $this->invoice->setNumber('FA-2025-0001');
        $this->invoice->setPdfPath('2025/01/FA-2025-0001.pdf');

        $this->pdfStorage->method('retrieve')->willReturn('%PDF-1.4 content');

        $transmissionResult = TransmissionResult::success(
            transmissionId: 'TX-123',
            message: 'Invoice submitted',
        );

        $this->pdpDispatcher
            ->expects($this->once())
            ->method('transmit')
            ->with($this->invoice, null, '%PDF-1.4 content', null)
            ->willReturn($transmissionResult);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(InvoiceTransmittedEvent::class));

        $subscriber = $this->createEnabledSubscriber();
        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    public function testDoesNotTransmitWhenPdfPathIsNull(): void
    {
        // PDF path is null by default
        $this->pdpDispatcher->expects($this->never())->method('transmit');

        $subscriber = $this->createEnabledSubscriber();
        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    public function testHandlesPdfStorageFailure(): void
    {
        $this->invoice->setPdfPath('2025/01/FA-2025-0001.pdf');

        $this->pdfStorage
            ->method('retrieve')
            ->willThrowException(new \RuntimeException('Storage error'));

        $this->pdpDispatcher->expects($this->never())->method('transmit');

        $subscriber = $this->createEnabledSubscriber();
        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    public function testHandlesConnectorNotFound(): void
    {
        $this->invoice->setNumber('FA-2025-0001');
        $this->invoice->setPdfPath('2025/01/FA-2025-0001.pdf');

        $this->pdfStorage->method('retrieve')->willReturn('%PDF-1.4 content');

        $this->pdpDispatcher
            ->method('transmit')
            ->willThrowException(new PdpConnectorNotFoundException('default'));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof InvoiceTransmissionFailedEvent
                    && str_contains($event->getErrorMessage() ?? '', 'connector not found');
            }));

        $subscriber = $this->createEnabledSubscriber();
        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    public function testHandlesTransmissionException(): void
    {
        $this->invoice->setNumber('FA-2025-0001');
        $this->invoice->setPdfPath('2025/01/FA-2025-0001.pdf');

        $this->pdfStorage->method('retrieve')->willReturn('%PDF-1.4 content');

        $this->pdpDispatcher
            ->method('transmit')
            ->willThrowException(new PdpTransmissionException('API error'));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof InvoiceTransmissionFailedEvent
                    && str_contains($event->getErrorMessage() ?? '', 'API error');
            }));

        $subscriber = $this->createEnabledSubscriber();
        $subscriber->onInvoiceFinalized(new InvoiceFinalizedEvent($this->invoice, 'FA-2025-0001'));
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createEnabledSubscriber(): InvoicePdpSubscriber
    {
        return new InvoicePdpSubscriber(
            pdpDispatcher: $this->pdpDispatcher,
            pdfStorage: $this->pdfStorage,
            eventDispatcher: $this->eventDispatcher,
            pdpEnabled: true,
            autoSendOnFinalize: true,
            defaultConnectorId: 'null',
        );
    }

    private function createInvoice(): Invoice
    {
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-15'),
            customerName: 'Test Customer',
            customerAddress: '123 Test Street',
            companyName: 'Test Company',
            companyAddress: '456 Company Street',
        );

        $invoice->addLine(new InvoiceLine(
            description: 'Test Service',
            quantity: 1.0,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        ));

        return $invoice;
    }
}
