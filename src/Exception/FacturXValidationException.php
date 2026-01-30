<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Exception;

use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationResult;

/**
 * Exception thrown when Factur-X XML validation fails.
 */
class FacturXValidationException extends \RuntimeException
{
    public function __construct(
        private readonly ValidationResult $validationResult,
        string $message = 'Factur-X XML validation failed',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $errorMessages = $this->validationResult->getErrorMessages();
        if ([] !== $errorMessages) {
            $message .= ': '.implode('; ', $errorMessages);
        }

        parent::__construct($message, $code, $previous);
    }

    public function getValidationResult(): ValidationResult
    {
        return $this->validationResult;
    }

    /**
     * @return array<int, string>
     */
    public function getErrorMessages(): array
    {
        return $this->validationResult->getErrorMessages();
    }

    public function getErrorCount(): int
    {
        return $this->validationResult->getErrorCount();
    }
}
