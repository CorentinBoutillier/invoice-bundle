<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Dto;

/**
 * Result of an e-reporting submission.
 */
readonly class ReportingResult
{
    /**
     * @param bool                        $success      Whether the submission was successful
     * @param string|null                 $reportId     ID assigned by the reporting portal
     * @param string|null                 $message      Human-readable message
     * @param array<string>               $errors       List of errors (if failed)
     * @param array<string>               $warnings     List of warnings
     * @param int                         $transactions Number of transactions submitted
     * @param \DateTimeImmutable|null     $submittedAt  When the submission was made
     * @param array<string, mixed>        $metadata     Additional metadata
     */
    public function __construct(
        public bool $success,
        public ?string $reportId = null,
        public ?string $message = null,
        public array $errors = [],
        public array $warnings = [],
        public int $transactions = 0,
        public ?\DateTimeImmutable $submittedAt = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param array<string, mixed> $metadata
     */
    public static function success(
        string $reportId,
        int $transactions,
        ?string $message = null,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            reportId: $reportId,
            message: $message ?? 'Rapport soumis avec succès',
            transactions: $transactions,
            submittedAt: new \DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * @param array<string> $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(
        string $message,
        array $errors = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
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
}
