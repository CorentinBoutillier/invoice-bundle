<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Exception;

use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;

/**
 * Exception thrown when invoice transmission to PDP fails.
 */
class PdpTransmissionException extends PdpException
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        string $message,
        private readonly ?TransmissionResult $result = null,
        private readonly array $errors = [],
        ?string $connectorId = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous, $connectorId);
    }

    /**
     * Create from a failed TransmissionResult.
     */
    public static function fromResult(
        TransmissionResult $result,
        ?string $connectorId = null,
    ): self {
        $message = $result->message ?? 'Transmission failed';

        return new self(
            message: $message,
            result: $result,
            errors: $result->errors,
            connectorId: $connectorId,
        );
    }

    /**
     * Create for a network/connectivity error.
     */
    public static function networkError(
        string $message,
        ?string $connectorId = null,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            message: $message,
            connectorId: $connectorId,
            previous: $previous,
        );
    }

    /**
     * Create for a validation rejection.
     *
     * @param array<string> $validationErrors
     */
    public static function validationFailed(
        array $validationErrors,
        ?string $connectorId = null,
    ): self {
        $message = 'Invoice validation failed: '.implode(', ', $validationErrors);

        return new self(
            message: $message,
            errors: $validationErrors,
            connectorId: $connectorId,
        );
    }

    public function getResult(): ?TransmissionResult
    {
        return $this->result;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
