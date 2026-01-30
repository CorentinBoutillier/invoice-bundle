<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;

/**
 * Status snapshot of an invoice in the PDP system.
 *
 * Represents the current state of an invoice as reported by the PDP.
 */
readonly class PdpInvoiceStatus
{
    /**
     * @param string           $transmissionId PDP-assigned tracking ID
     * @param PdpStatusCode    $status         Current status
     * @param \DateTimeImmutable $statusAt     When this status was set
     * @param string|null      $message        Optional status message
     * @param string|null      $recipientId    Recipient identifier (SIRET, SIREN)
     * @param string|null      $recipientName  Recipient name
     * @param array<string, mixed> $metadata   Additional PDP-specific data
     */
    public function __construct(
        public string $transmissionId,
        public PdpStatusCode $status,
        public \DateTimeImmutable $statusAt,
        public ?string $message = null,
        public ?string $recipientId = null,
        public ?string $recipientName = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Check if the invoice is still being processed.
     */
    public function isInProgress(): bool
    {
        return $this->status->isPending() && !$this->status->isTerminal();
    }

    /**
     * Check if the invoice reached a final state.
     */
    public function isComplete(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if the invoice was successfully delivered.
     */
    public function isDelivered(): bool
    {
        return \in_array($this->status, [
            PdpStatusCode::DELIVERED,
            PdpStatusCode::ACKNOWLEDGED,
            PdpStatusCode::APPROVED,
            PdpStatusCode::PAID,
        ], true);
    }

    /**
     * Check if the invoice encountered an error.
     */
    public function hasError(): bool
    {
        return $this->status->isFailure();
    }
}
