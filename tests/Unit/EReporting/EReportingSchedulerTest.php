<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;
use CorentinBoutillier\InvoiceBundle\EReporting\EReportingScheduler;
use PHPUnit\Framework\TestCase;

final class EReportingSchedulerTest extends TestCase
{
    private EReportingScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new EReportingScheduler();
    }

    // ========================================
    // Monthly Period Tests
    // ========================================

    public function testGetCurrentPeriodMonthly(): void
    {
        $date = new \DateTimeImmutable('2025-03-15');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::MONTHLY);

        $this->assertSame('2025-03-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-04-30', $period['deadline']->format('Y-m-d'));
    }

    public function testGetCurrentPeriodMonthlyFebruary(): void
    {
        $date = new \DateTimeImmutable('2025-02-15');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::MONTHLY);

        $this->assertSame('2025-02-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-02-28', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['deadline']->format('Y-m-d'));
    }

    public function testGetCurrentPeriodMonthlyLeapYear(): void
    {
        $date = new \DateTimeImmutable('2024-02-15');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::MONTHLY);

        $this->assertSame('2024-02-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2024-02-29', $period['end']->format('Y-m-d'));
        $this->assertSame('2024-03-31', $period['deadline']->format('Y-m-d'));
    }

    // ========================================
    // Quarterly Period Tests
    // ========================================

    public function testGetCurrentPeriodQuarterlyQ1(): void
    {
        $date = new \DateTimeImmutable('2025-02-15');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-01-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-04-30', $period['deadline']->format('Y-m-d'));
    }

    public function testGetCurrentPeriodQuarterlyQ2(): void
    {
        $date = new \DateTimeImmutable('2025-05-10');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-04-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-06-30', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-07-31', $period['deadline']->format('Y-m-d'));
    }

    public function testGetCurrentPeriodQuarterlyQ3(): void
    {
        $date = new \DateTimeImmutable('2025-08-20');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-07-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-09-30', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-10-31', $period['deadline']->format('Y-m-d'));
    }

    public function testGetCurrentPeriodQuarterlyQ4(): void
    {
        $date = new \DateTimeImmutable('2025-11-05');

        $period = $this->scheduler->getCurrentPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-10-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-12-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2026-01-31', $period['deadline']->format('Y-m-d'));
    }

    // ========================================
    // Previous Period Tests
    // ========================================

    public function testGetPreviousPeriodMonthly(): void
    {
        $date = new \DateTimeImmutable('2025-03-15');

        $period = $this->scheduler->getPreviousPeriod($date, ReportingFrequency::MONTHLY);

        $this->assertSame('2025-02-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-02-28', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['deadline']->format('Y-m-d'));
    }

    public function testGetPreviousPeriodMonthlyJanuary(): void
    {
        $date = new \DateTimeImmutable('2025-01-15');

        $period = $this->scheduler->getPreviousPeriod($date, ReportingFrequency::MONTHLY);

        $this->assertSame('2024-12-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2024-12-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-01-31', $period['deadline']->format('Y-m-d'));
    }

    public function testGetPreviousPeriodQuarterly(): void
    {
        $date = new \DateTimeImmutable('2025-05-15');

        $period = $this->scheduler->getPreviousPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-01-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-04-30', $period['deadline']->format('Y-m-d'));
    }

    public function testGetPreviousPeriodQuarterlyQ1(): void
    {
        $date = new \DateTimeImmutable('2025-02-15');

        $period = $this->scheduler->getPreviousPeriod($date, ReportingFrequency::QUARTERLY);

        $this->assertSame('2024-10-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2024-12-31', $period['end']->format('Y-m-d'));
        $this->assertSame('2025-01-31', $period['deadline']->format('Y-m-d'));
    }

    // ========================================
    // Deadline Calculation Tests
    // ========================================

    public function testGetDeadlineForPeriod(): void
    {
        $periodEnd = new \DateTimeImmutable('2025-01-31');

        $deadline = $this->scheduler->getDeadlineForPeriod($periodEnd);

        $this->assertSame('2025-02-28', $deadline->format('Y-m-d'));
    }

    public function testGetDeadlineForPeriodDecember(): void
    {
        $periodEnd = new \DateTimeImmutable('2025-12-31');

        $deadline = $this->scheduler->getDeadlineForPeriod($periodEnd);

        $this->assertSame('2026-01-31', $deadline->format('Y-m-d'));
    }

    // ========================================
    // Pending Periods Tests
    // ========================================

    public function testGetPendingPeriodsMonthly(): void
    {
        // If we're in March and last submission was for December
        $lastSubmitted = new \DateTimeImmutable('2024-12-31');
        $now = new \DateTimeImmutable('2025-03-15');

        $periods = $this->scheduler->getPendingPeriods(
            $lastSubmitted,
            $now,
            ReportingFrequency::MONTHLY,
        );

        // Should have January and February (March is current, not yet due)
        $this->assertCount(2, $periods);
        $this->assertSame('2025-01-01', $periods[0]['start']->format('Y-m-d'));
        $this->assertSame('2025-02-01', $periods[1]['start']->format('Y-m-d'));
    }

    public function testGetPendingPeriodsQuarterly(): void
    {
        // If we're in July and last submission was for Q4 2024
        $lastSubmitted = new \DateTimeImmutable('2024-12-31');
        $now = new \DateTimeImmutable('2025-07-15');

        $periods = $this->scheduler->getPendingPeriods(
            $lastSubmitted,
            $now,
            ReportingFrequency::QUARTERLY,
        );

        // Should have Q1 and Q2 (Q3 is current, not yet due)
        $this->assertCount(2, $periods);
        $this->assertSame('2025-01-01', $periods[0]['start']->format('Y-m-d'));
        $this->assertSame('2025-04-01', $periods[1]['start']->format('Y-m-d'));
    }

    public function testGetPendingPeriodsNoPending(): void
    {
        // If last submission was for current period
        $lastSubmitted = new \DateTimeImmutable('2025-03-31');
        $now = new \DateTimeImmutable('2025-04-15');

        $periods = $this->scheduler->getPendingPeriods(
            $lastSubmitted,
            $now,
            ReportingFrequency::MONTHLY,
        );

        $this->assertCount(0, $periods);
    }

    // ========================================
    // Overdue Check Tests
    // ========================================

    public function testIsOverdue(): void
    {
        // Period end was January, we're now in April (past Feb deadline)
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $now = new \DateTimeImmutable('2025-04-15');

        $this->assertTrue($this->scheduler->isOverdue($periodEnd, $now));
    }

    public function testIsNotOverdue(): void
    {
        // Period end was January, we're still in February (before deadline)
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $now = new \DateTimeImmutable('2025-02-15');

        $this->assertFalse($this->scheduler->isOverdue($periodEnd, $now));
    }

    public function testIsOverdueOnDeadlineDay(): void
    {
        // On deadline day, not yet overdue
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $now = new \DateTimeImmutable('2025-02-28');

        $this->assertFalse($this->scheduler->isOverdue($periodEnd, $now));
    }

    // ========================================
    // Days Until Deadline Tests
    // ========================================

    public function testGetDaysUntilDeadline(): void
    {
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $now = new \DateTimeImmutable('2025-02-20');

        $days = $this->scheduler->getDaysUntilDeadline($periodEnd, $now);

        $this->assertSame(8, $days); // Feb 20 to Feb 28 = 8 days
    }

    public function testGetDaysUntilDeadlineNegative(): void
    {
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $now = new \DateTimeImmutable('2025-03-05');

        $days = $this->scheduler->getDaysUntilDeadline($periodEnd, $now);

        $this->assertLessThan(0, $days); // Past deadline
    }

    // ========================================
    // Period for Date Tests
    // ========================================

    public function testGetPeriodForDateMonthly(): void
    {
        $transactionDate = new \DateTimeImmutable('2025-03-15');

        $period = $this->scheduler->getPeriodForDate($transactionDate, ReportingFrequency::MONTHLY);

        $this->assertSame('2025-03-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-03-31', $period['end']->format('Y-m-d'));
    }

    public function testGetPeriodForDateQuarterly(): void
    {
        $transactionDate = new \DateTimeImmutable('2025-05-20');

        $period = $this->scheduler->getPeriodForDate($transactionDate, ReportingFrequency::QUARTERLY);

        $this->assertSame('2025-04-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2025-06-30', $period['end']->format('Y-m-d'));
    }

    // ========================================
    // Period Label Tests
    // ========================================

    public function testGetPeriodLabelMonthly(): void
    {
        $periodStart = new \DateTimeImmutable('2025-03-01');

        $label = $this->scheduler->getPeriodLabel($periodStart, ReportingFrequency::MONTHLY);

        $this->assertSame('Mars 2025', $label);
    }

    public function testGetPeriodLabelQuarterly(): void
    {
        $periodStart = new \DateTimeImmutable('2025-04-01');

        $label = $this->scheduler->getPeriodLabel($periodStart, ReportingFrequency::QUARTERLY);

        $this->assertSame('T2 2025', $label);
    }
}
