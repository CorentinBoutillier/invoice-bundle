<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;

/**
 * Handles e-reporting period calculations and deadlines.
 *
 * Calculates reporting periods, deadlines, and pending submissions
 * according to French e-reporting regulations.
 */
final class EReportingScheduler
{
    private const MONTHS_FR = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
        4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
        10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    /**
     * Get the current reporting period for a given date.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable, deadline: \DateTimeImmutable}
     */
    public function getCurrentPeriod(
        \DateTimeImmutable $date,
        ReportingFrequency $frequency,
    ): array {
        $start = $frequency->getPeriodStart($date);
        $end = $frequency->getPeriodEnd($date);
        $deadline = $this->getDeadlineForPeriod($end);

        return [
            'start' => $start,
            'end' => $end,
            'deadline' => $deadline,
        ];
    }

    /**
     * Get the previous reporting period.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable, deadline: \DateTimeImmutable}
     */
    public function getPreviousPeriod(
        \DateTimeImmutable $date,
        ReportingFrequency $frequency,
    ): array {
        $current = $this->getCurrentPeriod($date, $frequency);
        $previousDate = $current['start']->modify('-1 day');

        return $this->getCurrentPeriod($previousDate, $frequency);
    }

    /**
     * Calculate the deadline for a period.
     *
     * The deadline is the last day of the month following the period end.
     */
    public function getDeadlineForPeriod(\DateTimeImmutable $periodEnd): \DateTimeImmutable
    {
        return $periodEnd
            ->modify('first day of next month')
            ->modify('last day of this month');
    }

    /**
     * Get all pending periods that need to be reported.
     *
     * Returns periods between the last submitted period and the current date,
     * excluding the current period (which is not yet complete).
     *
     * @return array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable, deadline: \DateTimeImmutable}>
     */
    public function getPendingPeriods(
        \DateTimeImmutable $lastSubmittedPeriodEnd,
        \DateTimeImmutable $now,
        ReportingFrequency $frequency,
    ): array {
        $periods = [];
        $current = $this->getCurrentPeriod($now, $frequency);

        // Start from the period after the last submitted one
        $checkDate = $lastSubmittedPeriodEnd->modify('+1 day');

        while ($checkDate < $current['start']) {
            $period = $this->getCurrentPeriod($checkDate, $frequency);
            $periods[] = $period;

            // Move to the next period
            $checkDate = $period['end']->modify('+1 day');
        }

        return $periods;
    }

    /**
     * Check if a period is overdue (past its deadline).
     */
    public function isOverdue(\DateTimeImmutable $periodEnd, \DateTimeImmutable $now): bool
    {
        $deadline = $this->getDeadlineForPeriod($periodEnd);

        return $now > $deadline;
    }

    /**
     * Get the number of days until the deadline for a period.
     *
     * Returns a negative number if the deadline has passed.
     */
    public function getDaysUntilDeadline(\DateTimeImmutable $periodEnd, \DateTimeImmutable $now): int
    {
        $deadline = $this->getDeadlineForPeriod($periodEnd);
        $diff = $now->diff($deadline);
        $days = false !== $diff->days ? $diff->days : 0;

        if ($diff->invert) {
            return -$days;
        }

        return $days;
    }

    /**
     * Get the period containing a specific date.
     *
     * Useful for determining which reporting period a transaction belongs to.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable, deadline: \DateTimeImmutable}
     */
    public function getPeriodForDate(
        \DateTimeImmutable $date,
        ReportingFrequency $frequency,
    ): array {
        return $this->getCurrentPeriod($date, $frequency);
    }

    /**
     * Get a human-readable label for a period.
     */
    public function getPeriodLabel(
        \DateTimeImmutable $periodStart,
        ReportingFrequency $frequency,
    ): string {
        if (ReportingFrequency::MONTHLY === $frequency) {
            $month = (int) $periodStart->format('n');
            $year = $periodStart->format('Y');

            return self::MONTHS_FR[$month].' '.$year;
        }

        // Quarterly
        $month = (int) $periodStart->format('n');
        $quarter = (int) ceil($month / 3);
        $year = $periodStart->format('Y');

        return "T{$quarter} {$year}";
    }
}
