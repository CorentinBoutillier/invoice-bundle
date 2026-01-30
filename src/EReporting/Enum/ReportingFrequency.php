<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Enum;

/**
 * E-reporting frequency options.
 *
 * Determines how often e-reporting submissions are required.
 * Most businesses use monthly reporting, but quarterly is allowed
 * for smaller businesses based on French tax regulations.
 */
enum ReportingFrequency: string
{
    /**
     * Monthly reporting (default for most businesses).
     */
    case MONTHLY = 'monthly';

    /**
     * Quarterly reporting (for eligible smaller businesses).
     */
    case QUARTERLY = 'quarterly';

    /**
     * Get the interval in months.
     */
    public function getMonthsInterval(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
        };
    }

    /**
     * Get the French label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensuel',
            self::QUARTERLY => 'Trimestriel',
        };
    }

    /**
     * Calculate the deadline for reporting a transaction from a given period.
     *
     * The deadline is the last day of the month following the reporting period.
     */
    public function getNextDeadline(\DateTimeImmutable $periodDate): \DateTimeImmutable
    {
        $periodEnd = $this->getPeriodEnd($periodDate);

        // Deadline is last day of the month following the period end
        return $periodEnd
            ->modify('first day of next month')
            ->modify('last day of this month');
    }

    /**
     * Get the start of the reporting period containing the given date.
     */
    public function getPeriodStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        if (self::MONTHLY === $this) {
            return $date->modify('first day of this month');
        }

        // Quarterly: find the quarter start
        $month = (int) $date->format('n');
        $quarterStartMonth = (int) (ceil($month / 3) - 1) * 3 + 1;

        return $date->setDate(
            (int) $date->format('Y'),
            $quarterStartMonth,
            1,
        );
    }

    /**
     * Get the end of the reporting period containing the given date.
     */
    public function getPeriodEnd(\DateTimeImmutable $date): \DateTimeImmutable
    {
        if (self::MONTHLY === $this) {
            return $date->modify('last day of this month');
        }

        // Quarterly: find the quarter end
        $month = (int) $date->format('n');
        $quarterEndMonth = (int) ceil($month / 3) * 3;

        return $date
            ->setDate((int) $date->format('Y'), $quarterEndMonth, 1)
            ->modify('last day of this month');
    }
}
