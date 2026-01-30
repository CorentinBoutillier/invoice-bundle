<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Validation;

/**
 * Represents a single validation error or warning.
 */
readonly class ValidationError
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    public function __construct(
        public string $message,
        public string $severity = self::SEVERITY_ERROR,
        public ?string $code = null,
        public ?int $line = null,
        public ?string $path = null,
    ) {
    }

    public function isError(): bool
    {
        return self::SEVERITY_ERROR === $this->severity;
    }

    public function isWarning(): bool
    {
        return self::SEVERITY_WARNING === $this->severity;
    }

    public function __toString(): string
    {
        $parts = [];

        if (null !== $this->code) {
            $parts[] = "[{$this->code}]";
        }

        if (null !== $this->line) {
            $parts[] = "Line {$this->line}:";
        }

        $parts[] = $this->message;

        return implode(' ', $parts);
    }
}
