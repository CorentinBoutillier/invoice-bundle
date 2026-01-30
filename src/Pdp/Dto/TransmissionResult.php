<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Dto;

use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;

/**
 * Result of an invoice transmission to a PDP.
 *
 * Returned after attempting to send an invoice through a PDP connector.
 */
readonly class TransmissionResult
{
    /**
     * @param bool             $success        Whether the transmission was accepted
     * @param string|null      $transmissionId PDP-assigned ID for tracking
     * @param PdpStatusCode    $status         Current status after transmission
     * @param string|null      $message        Human-readable message (error or confirmation)
     * @param array<string>    $errors         List of validation/transmission errors
     * @param array<string>    $warnings       List of non-fatal warnings
     * @param \DateTimeImmutable|null $transmittedAt When the transmission occurred
     * @param array<string, mixed> $metadata   Additional connector-specific data
     */
    public function __construct(
        public bool $success,
        public ?string $transmissionId = null,
        public PdpStatusCode $status = PdpStatusCode::PENDING,
        public ?string $message = null,
        public array $errors = [],
        public array $warnings = [],
        public ?\DateTimeImmutable $transmittedAt = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Create a successful transmission result.
     *
     * @param array<string, mixed> $metadata
     */
    public static function success(
        string $transmissionId,
        ?string $message = null,
        PdpStatusCode $status = PdpStatusCode::SUBMITTED,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            transmissionId: $transmissionId,
            status: $status,
            message: $message,
            transmittedAt: new \DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * Create a failed transmission result.
     *
     * @param array<string> $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(
        string $message,
        array $errors = [],
        PdpStatusCode $status = PdpStatusCode::FAILED,
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            status: $status,
            message: $message,
            errors: $errors,
            metadata: $metadata,
        );
    }

    /**
     * Create a rejection result (validation failed on PDP side).
     *
     * @param array<string> $errors
     * @param array<string, mixed> $metadata
     */
    public static function rejected(
        string $message,
        array $errors = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            status: PdpStatusCode::REJECTED,
            message: $message,
            errors: $errors,
            metadata: $metadata,
        );
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }

    public function getErrorCount(): int
    {
        return \count($this->errors);
    }
}
