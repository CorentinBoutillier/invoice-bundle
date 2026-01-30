<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmissionFailedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmittedEvent;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpConnectorNotFoundException;
use CorentinBoutillier\InvoiceBundle\Pdp\Exception\PdpTransmissionException;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpDispatcherInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\PdfStorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Automatically sends invoices to PDP after finalization.
 *
 * This subscriber listens to InvoiceFinalizedEvent and, when auto_send_on_finalize
 * is enabled, transmits the invoice to the configured PDP connector.
 */
final class InvoicePdpSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PdpDispatcherInterface $pdpDispatcher,
        private readonly PdfStorageInterface $pdfStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly bool $pdpEnabled = false,
        private readonly bool $autoSendOnFinalize = false,
        private readonly ?string $defaultConnectorId = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Use a low priority to ensure the invoice is fully persisted first
            InvoiceFinalizedEvent::class => ['onInvoiceFinalized', -100],
        ];
    }

    public function onInvoiceFinalized(InvoiceFinalizedEvent $event): void
    {
        // Skip if PDP is disabled or auto-send is not configured
        if (!$this->pdpEnabled || !$this->autoSendOnFinalize) {
            return;
        }

        $invoice = $event->invoice;

        // Retrieve PDF content from storage
        $pdfPath = $invoice->getPdfPath();
        if (null === $pdfPath) {
            $this->logger->warning('Cannot send invoice to PDP: PDF path is null', [
                'invoice_number' => $invoice->getNumber(),
            ]);

            return;
        }

        try {
            $pdfContent = $this->pdfStorage->retrieve($pdfPath);
        } catch (\Exception $e) {
            $this->logger->error('Cannot retrieve PDF for PDP transmission', [
                'invoice_number' => $invoice->getNumber(),
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $connectorId = $this->defaultConnectorId ?? 'unknown';

        try {
            // Transmit to PDP using default connector
            $result = $this->pdpDispatcher->transmit($invoice, pdfContent: $pdfContent);

            $this->logger->info('Invoice transmitted to PDP', [
                'invoice_number' => $invoice->getNumber(),
                'transmission_id' => $result->transmissionId,
                'connector' => $connectorId,
            ]);

            // Dispatch success event
            $this->eventDispatcher->dispatch(new InvoiceTransmittedEvent(
                invoice: $invoice,
                result: $result,
                connectorId: $connectorId,
            ));
        } catch (PdpConnectorNotFoundException $e) {
            $this->logger->error('PDP connector not found', [
                'invoice_number' => $invoice->getNumber(),
                'error' => $e->getMessage(),
            ]);

            // Dispatch failure event
            $this->eventDispatcher->dispatch(new InvoiceTransmissionFailedEvent(
                invoice: $invoice,
                result: null,
                connectorId: $connectorId,
                errorMessage: 'PDP connector not found: '.$e->getMessage(),
                exception: $e,
            ));
        } catch (PdpTransmissionException $e) {
            $this->logger->error('PDP transmission failed', [
                'invoice_number' => $invoice->getNumber(),
                'error' => $e->getMessage(),
            ]);

            // Dispatch failure event
            $this->eventDispatcher->dispatch(new InvoiceTransmissionFailedEvent(
                invoice: $invoice,
                result: null,
                connectorId: $connectorId,
                errorMessage: $e->getMessage(),
                exception: $e,
            ));
        }
    }
}
