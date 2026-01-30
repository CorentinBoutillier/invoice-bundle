<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Enum;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;
use PHPUnit\Framework\TestCase;

final class ReportingFrequencyTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = ReportingFrequency::cases();

        $this->assertContains(ReportingFrequency::MONTHLY, $cases);
        $this->assertContains(ReportingFrequency::QUARTERLY, $cases);
        $this->assertCount(2, $cases);
    }

    public function testMonthlyValues(): void
    {
        $frequency = ReportingFrequency::MONTHLY;

        $this->assertSame('monthly', $frequency->value);
        $this->assertSame(1, $frequency->getMonthsInterval());
        $this->assertSame('Mensuel', $frequency->getLabel());
    }

    public function testQuarterlyValues(): void
    {
        $frequency = ReportingFrequency::QUARTERLY;

        $this->assertSame('quarterly', $frequency->value);
        $this->assertSame(3, $frequency->getMonthsInterval());
        $this->assertSame('Trimestriel', $frequency->getLabel());
    }

    public function testGetNextDeadlineMonthly(): void
    {
        $frequency = ReportingFrequency::MONTHLY;

        // Test for January transactions -> deadline end of February
        $periodDate = new \DateTimeImmutable('2025-01-15');
        $deadline = $frequency->getNextDeadline($periodDate);

        // E-reporting deadline is the last day of the following month
        $this->assertSame('2025-02-28', $deadline->format('Y-m-d'));

        // Test for February transactions -> deadline end of March
        $periodDate = new \DateTimeImmutable('2025-02-10');
        $deadline = $frequency->getNextDeadline($periodDate);
        $this->assertSame('2025-03-31', $deadline->format('Y-m-d'));
    }

    public function testGetNextDeadlineQuarterly(): void
    {
        $frequency = ReportingFrequency::QUARTERLY;

        // Q1 (Jan-Mar) transactions -> deadline end of April
        $periodDate = new \DateTimeImmutable('2025-02-15');
        $deadline = $frequency->getNextDeadline($periodDate);
        $this->assertSame('2025-04-30', $deadline->format('Y-m-d'));

        // Q2 (Apr-Jun) transactions -> deadline end of July
        $periodDate = new \DateTimeImmutable('2025-05-10');
        $deadline = $frequency->getNextDeadline($periodDate);
        $this->assertSame('2025-07-31', $deadline->format('Y-m-d'));

        // Q3 (Jul-Sep) transactions -> deadline end of October
        $periodDate = new \DateTimeImmutable('2025-08-20');
        $deadline = $frequency->getNextDeadline($periodDate);
        $this->assertSame('2025-10-31', $deadline->format('Y-m-d'));

        // Q4 (Oct-Dec) transactions -> deadline end of January next year
        $periodDate = new \DateTimeImmutable('2025-11-05');
        $deadline = $frequency->getNextDeadline($periodDate);
        $this->assertSame('2026-01-31', $deadline->format('Y-m-d'));
    }

    public function testGetPeriodStart(): void
    {
        // Monthly
        $monthly = ReportingFrequency::MONTHLY;
        $date = new \DateTimeImmutable('2025-03-15');
        $start = $monthly->getPeriodStart($date);
        $this->assertSame('2025-03-01', $start->format('Y-m-d'));

        // Quarterly
        $quarterly = ReportingFrequency::QUARTERLY;
        // Q1 starts January
        $start = $quarterly->getPeriodStart(new \DateTimeImmutable('2025-02-15'));
        $this->assertSame('2025-01-01', $start->format('Y-m-d'));
        // Q2 starts April
        $start = $quarterly->getPeriodStart(new \DateTimeImmutable('2025-05-15'));
        $this->assertSame('2025-04-01', $start->format('Y-m-d'));
        // Q3 starts July
        $start = $quarterly->getPeriodStart(new \DateTimeImmutable('2025-08-15'));
        $this->assertSame('2025-07-01', $start->format('Y-m-d'));
        // Q4 starts October
        $start = $quarterly->getPeriodStart(new \DateTimeImmutable('2025-11-15'));
        $this->assertSame('2025-10-01', $start->format('Y-m-d'));
    }

    public function testGetPeriodEnd(): void
    {
        // Monthly
        $monthly = ReportingFrequency::MONTHLY;
        $date = new \DateTimeImmutable('2025-03-15');
        $end = $monthly->getPeriodEnd($date);
        $this->assertSame('2025-03-31', $end->format('Y-m-d'));

        // Quarterly
        $quarterly = ReportingFrequency::QUARTERLY;
        // Q1 ends March
        $end = $quarterly->getPeriodEnd(new \DateTimeImmutable('2025-02-15'));
        $this->assertSame('2025-03-31', $end->format('Y-m-d'));
        // Q2 ends June
        $end = $quarterly->getPeriodEnd(new \DateTimeImmutable('2025-05-15'));
        $this->assertSame('2025-06-30', $end->format('Y-m-d'));
    }
}
