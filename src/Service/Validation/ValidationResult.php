<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Validation;

/**
 * Result of XML validation containing errors and warnings.
 */
readonly class ValidationResult
{
    /**
     * @param array<int, ValidationError> $errors
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
    ) {
    }

    public static function valid(): self
    {
        return new self(isValid: true, errors: []);
    }

    /**
     * @param array<int, ValidationError> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(isValid: false, errors: $errors);
    }

    /**
     * Get only errors (not warnings).
     *
     * @return array<int, ValidationError>
     */
    public function getErrors(): array
    {
        return array_values(array_filter(
            $this->errors,
            static fn (ValidationError $error): bool => $error->isError(),
        ));
    }

    /**
     * Get only warnings.
     *
     * @return array<int, ValidationError>
     */
    public function getWarnings(): array
    {
        return array_values(array_filter(
            $this->errors,
            static fn (ValidationError $error): bool => $error->isWarning(),
        ));
    }

    public function hasErrors(): bool
    {
        return [] !== $this->getErrors();
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->getWarnings();
    }

    public function getErrorCount(): int
    {
        return \count($this->getErrors());
    }

    public function getWarningCount(): int
    {
        return \count($this->getWarnings());
    }

    /**
     * Get all error messages as strings.
     *
     * @return array<int, string>
     */
    public function getErrorMessages(): array
    {
        return array_map(
            static fn (ValidationError $error): string => (string) $error,
            $this->getErrors(),
        );
    }
}
