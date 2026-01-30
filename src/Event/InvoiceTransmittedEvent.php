<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an invoice has been successfully transmitted to a PDP.
 */
final class InvoiceTransmittedEvent extends Event
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly TransmissionResult $result,
        private readonly string $connectorId,
    ) {
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getResult(): TransmissionResult
    {
        return $this->result;
    }

    public function getConnectorId(): string
    {
        return $this->connectorId;
    }

    public function getTransmissionId(): ?string
    {
        return $this->result->transmissionId;
    }
}
