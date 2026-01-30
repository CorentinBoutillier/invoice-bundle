<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Dto;

use CorentinBoutillier\InvoiceBundle\EReporting\Dto\ReportingSummary;
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;
use PHPUnit\Framework\TestCase;

final class ReportingSummaryTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $periodStart = new \DateTimeImmutable('2025-01-01');
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $deadline = new \DateTimeImmutable('2025-02-28');

        $summary = new ReportingSummary(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            deadline: $deadline,
            frequency: ReportingFrequency::MONTHLY,
        );

        $this->assertSame($periodStart, $summary->periodStart);
        $this->assertSame($periodEnd, $summary->periodEnd);
        $this->assertSame($deadline, $summary->deadline);
        $this->assertSame(ReportingFrequency::MONTHLY, $summary->frequency);
        $this->assertSame(0, $summary->transactionCount);
        $this->assertSame('0.00', $summary->totalExcludingVat);
        $this->assertSame('0.00', $summary->totalVat);
        $this->assertSame('0.00', $summary->totalIncludingVat);
        $this->assertSame([], $summary->transactionsByType);
        $this->assertSame([], $summary->vatByRate);
        $this->assertFalse($summary->isSubmitted);
        $this->assertNull($summary->reportId);
    }

    public function testConstructorWithAllFields(): void
    {
        $periodStart = new \DateTimeImmutable('2025-01-01');
        $periodEnd = new \DateTimeImmutable('2025-01-31');
        $deadline = new \DateTimeImmutable('2025-02-28');

        $summary = new ReportingSummary(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            deadline: $deadline,
            frequency: ReportingFrequency::MONTHLY,
            transactionCount: 25,
            totalExcludingVat: '10000.00',
            totalVat: '2000.00',
            totalIncludingVat: '12000.00',
            transactionsByType: ['b2c_france' => 20, 'b2c_export' => 5],
            vatByRate: ['20.00' => '1800.00', '5.50' => '200.00'],
            isSubmitted: true,
            reportId: 'RPT-2025-JAN',
        );

        $this->assertSame(25, $summary->transactionCount);
        $this->assertSame('10000.00', $summary->totalExcludingVat);
        $this->assertSame(['b2c_france' => 20, 'b2c_export' => 5], $summary->transactionsByType);
        $this->assertTrue($summary->isSubmitted);
        $this->assertSame('RPT-2025-JAN', $summary->reportId);
    }

    public function testIsOverdueWhenPastDeadlineAndNotSubmitted(): void
    {
        $summary = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2024-01-01'),
            periodEnd: new \DateTimeImmutable('2024-01-31'),
            deadline: new \DateTimeImmutable('2024-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            isSubmitted: false,
        );

        $this->assertTrue($summary->isOverdue());
    }

    public function testIsNotOverdueWhenSubmitted(): void
    {
        $summary = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2024-01-01'),
            periodEnd: new \DateTimeImmutable('2024-01-31'),
            deadline: new \DateTimeImmutable('2024-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            isSubmitted: true,
            reportId: 'RPT-001',
        );

        $this->assertFalse($summary->isOverdue());
    }

    public function testIsNotOverdueWhenDeadlineInFuture(): void
    {
        $summary = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2030-01-01'),
            periodEnd: new \DateTimeImmutable('2030-01-31'),
            deadline: new \DateTimeImmutable('2030-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            isSubmitted: false,
        );

        $this->assertFalse($summary->isOverdue());
    }

    public function testHasTransactions(): void
    {
        $noTransactions = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-01-01'),
            periodEnd: new \DateTimeImmutable('2025-01-31'),
            deadline: new \DateTimeImmutable('2025-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            transactionCount: 0,
        );
        $this->assertFalse($noTransactions->hasTransactions());

        $withTransactions = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-01-01'),
            periodEnd: new \DateTimeImmutable('2025-01-31'),
            deadline: new \DateTimeImmutable('2025-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            transactionCount: 5,
        );
        $this->assertTrue($withTransactions->hasTransactions());
    }

    public function testGetPeriodLabelMonthly(): void
    {
        $january = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-01-01'),
            periodEnd: new \DateTimeImmutable('2025-01-31'),
            deadline: new \DateTimeImmutable('2025-02-28'),
            frequency: ReportingFrequency::MONTHLY,
        );
        $this->assertSame('Janvier 2025', $january->getPeriodLabel());

        $september = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-09-01'),
            periodEnd: new \DateTimeImmutable('2025-09-30'),
            deadline: new \DateTimeImmutable('2025-10-31'),
            frequency: ReportingFrequency::MONTHLY,
        );
        $this->assertSame('Septembre 2025', $september->getPeriodLabel());
    }

    public function testGetPeriodLabelQuarterly(): void
    {
        $q1 = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-01-01'),
            periodEnd: new \DateTimeImmutable('2025-03-31'),
            deadline: new \DateTimeImmutable('2025-04-30'),
            frequency: ReportingFrequency::QUARTERLY,
        );
        $this->assertSame('T1 2025', $q1->getPeriodLabel());

        $q3 = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-07-01'),
            periodEnd: new \DateTimeImmutable('2025-09-30'),
            deadline: new \DateTimeImmutable('2025-10-31'),
            frequency: ReportingFrequency::QUARTERLY,
        );
        $this->assertSame('T3 2025', $q3->getPeriodLabel());
    }

    public function testGetDaysUntilDeadline(): void
    {
        // Future deadline
        $future = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2030-01-01'),
            periodEnd: new \DateTimeImmutable('2030-01-31'),
            deadline: new \DateTimeImmutable('+10 days'),
            frequency: ReportingFrequency::MONTHLY,
        );
        $this->assertGreaterThanOrEqual(9, $future->getDaysUntilDeadline());
        $this->assertLessThanOrEqual(11, $future->getDaysUntilDeadline());

        // Past deadline
        $past = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2024-01-01'),
            periodEnd: new \DateTimeImmutable('2024-01-31'),
            deadline: new \DateTimeImmutable('-5 days'),
            frequency: ReportingFrequency::MONTHLY,
        );
        $this->assertLessThan(0, $past->getDaysUntilDeadline());
    }

    public function testWithSubmission(): void
    {
        $original = new ReportingSummary(
            periodStart: new \DateTimeImmutable('2025-01-01'),
            periodEnd: new \DateTimeImmutable('2025-01-31'),
            deadline: new \DateTimeImmutable('2025-02-28'),
            frequency: ReportingFrequency::MONTHLY,
            transactionCount: 10,
            totalExcludingVat: '5000.00',
            totalVat: '1000.00',
            totalIncludingVat: '6000.00',
            isSubmitted: false,
        );

        $submitted = $original->withSubmission('RPT-2025-JAN-001');

        // Original unchanged
        $this->assertFalse($original->isSubmitted);
        $this->assertNull($original->reportId);

        // New instance has submission data
        $this->assertTrue($submitted->isSubmitted);
        $this->assertSame('RPT-2025-JAN-001', $submitted->reportId);

        // Other fields preserved
        $this->assertSame(10, $submitted->transactionCount);
        $this->assertSame('5000.00', $submitted->totalExcludingVat);
        $this->assertSame($original->periodStart, $submitted->periodStart);
    }
}
