<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Exception;

/**
 * Base exception for all PDP-related errors.
 */
class PdpException extends \RuntimeException
{
    public function __construct(
        string $message = 'PDP operation failed',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $connectorId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getConnectorId(): ?string
    {
        return $this->connectorId;
    }
}
