<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Event;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an invoice transmission to a PDP has failed.
 */
final class InvoiceTransmissionFailedEvent extends Event
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly ?TransmissionResult $result,
        private readonly string $connectorId,
        private readonly ?string $errorMessage = null,
        private readonly ?\Throwable $exception = null,
    ) {
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getResult(): ?TransmissionResult
    {
        return $this->result;
    }

    public function getConnectorId(): string
    {
        return $this->connectorId;
    }

    public function getErrorMessage(): ?string
    {
        if (null !== $this->errorMessage) {
            return $this->errorMessage;
        }

        if (null !== $this->result) {
            return $this->result->message;
        }

        if (null !== $this->exception) {
            return $this->exception->getMessage();
        }

        return null;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->result?->errors ?? [];
    }
}
