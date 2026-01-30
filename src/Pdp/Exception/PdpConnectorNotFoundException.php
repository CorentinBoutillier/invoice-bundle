<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp\Exception;

/**
 * Exception thrown when a requested PDP connector is not found.
 */
class PdpConnectorNotFoundException extends PdpException
{
    public function __construct(
        string $connectorId,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = \sprintf('PDP connector "%s" not found', $connectorId);

        parent::__construct($message, $code, $previous, $connectorId);
    }
}
