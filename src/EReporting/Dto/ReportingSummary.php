<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Dto;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;

/**
 * Summary of a reporting period.
 *
 * Aggregates transaction data for a reporting period.
 */
readonly class ReportingSummary
{
    /**
     * @param \DateTimeImmutable   $periodStart         Start of the reporting period
     * @param \DateTimeImmutable   $periodEnd           End of the reporting period
     * @param \DateTimeImmutable   $deadline            Submission deadline
     * @param ReportingFrequency   $frequency           Reporting frequency
     * @param int                  $transactionCount    Number of transactions
     * @param string               $totalExcludingVat   Total HT for the period
     * @param string               $totalVat            Total VAT for the period
     * @param string               $totalIncludingVat   Total TTC for the period
     * @param array<string, int>   $transactionsByType  Count by transaction type
     * @param array<string, string> $vatByRate          VAT totals by rate
     * @param bool                 $isSubmitted         Whether already submitted
     * @param string|null          $reportId            Report ID if submitted
     */
    public function __construct(
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $periodEnd,
        public \DateTimeImmutable $deadline,
        public ReportingFrequency $frequency,
        public int $transactionCount = 0,
        public string $totalExcludingVat = '0.00',
        public string $totalVat = '0.00',
        public string $totalIncludingVat = '0.00',
        public array $transactionsByType = [],
        public array $vatByRate = [],
        public bool $isSubmitted = false,
        public ?string $reportId = null,
    ) {
    }

    /**
     * Check if the period is overdue (past deadline).
     */
    public function isOverdue(): bool
    {
        return !$this->isSubmitted && $this->deadline < new \DateTimeImmutable();
    }

    /**
     * Check if there are transactions to report.
     */
    public function hasTransactions(): bool
    {
        return $this->transactionCount > 0;
    }

    /**
     * Get the period label (e.g., "Janvier 2025" or "T1 2025").
     */
    public function getPeriodLabel(): string
    {
        if (ReportingFrequency::MONTHLY === $this->frequency) {
            $months = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
                4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
                10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
            ];
            $month = (int) $this->periodStart->format('n');
            $year = $this->periodStart->format('Y');

            return $months[$month].' '.$year;
        }

        // Quarterly
        $month = (int) $this->periodStart->format('n');
        $quarter = (int) ceil($month / 3);
        $year = $this->periodStart->format('Y');

        return "T{$quarter} {$year}";
    }

    /**
     * Get days remaining until deadline.
     */
    public function getDaysUntilDeadline(): int
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->deadline);
        $days = false !== $diff->days ? $diff->days : 0;

        if ($diff->invert) {
            return -$days;
        }

        return $days;
    }

    /**
     * Create a copy marked as submitted.
     */
    public function withSubmission(string $reportId): self
    {
        return new self(
            periodStart: $this->periodStart,
            periodEnd: $this->periodEnd,
            deadline: $this->deadline,
            frequency: $this->frequency,
            transactionCount: $this->transactionCount,
            totalExcludingVat: $this->totalExcludingVat,
            totalVat: $this->totalVat,
            totalIncludingVat: $this->totalIncludingVat,
            transactionsByType: $this->transactionsByType,
            vatByRate: $this->vatByRate,
            isSubmitted: true,
            reportId: $reportId,
        );
    }
}
