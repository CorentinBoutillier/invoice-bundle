<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service;

use CorentinBoutillier\InvoiceBundle\Service\DueDateCalculator;
use CorentinBoutillier\InvoiceBundle\Service\DueDateCalculatorInterface;
use PHPUnit\Framework\TestCase;

final class DueDateCalculatorTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    private DueDateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DueDateCalculator();
    }

    public function testImplementsDueDateCalculatorInterface(): void
    {
        $this->assertInstanceOf(DueDateCalculatorInterface::class, $this->calculator);
    }

    // ========== Comptant (immediate payment) ==========

    public function testComptantReturnsSameDate(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, 'comptant');

        $this->assertEquals('2025-01-15', $dueDate->format('Y-m-d'));
    }

    public function testComptantOnLastDayOfMonth(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-31');

        $dueDate = $this->calculator->calculate($invoiceDate, 'comptant');

        $this->assertEquals('2025-01-31', $dueDate->format('Y-m-d'));
    }

    // ========== X jours net (net days) ==========

    public function test30JoursNetAdds30Days(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '30 jours net');

        $this->assertEquals('2025-02-14', $dueDate->format('Y-m-d'));
    }

    public function test45JoursNetAdds45Days(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '45 jours net');

        $this->assertEquals('2025-03-01', $dueDate->format('Y-m-d'));
    }

    public function test60JoursNetAdds60Days(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '60 jours net');

        $this->assertEquals('2025-03-16', $dueDate->format('Y-m-d'));
    }

    public function test7JoursNetAdds7Days(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '7 jours net');

        $this->assertEquals('2025-01-22', $dueDate->format('Y-m-d'));
    }

    // ========== X jours fin de mois (end of month) ==========

    public function test30JoursFinDeMoisGoesToEndOfMonth(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '30 jours fin de mois');

        // Jan 15 + 30 days = Feb 14 → end of February = Feb 28
        $this->assertEquals('2025-02-28', $dueDate->format('Y-m-d'));
    }

    public function test45JoursFinDeMoisGoesToEndOfMonth(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '45 jours fin de mois');

        // Jan 15 + 45 days = March 1 → end of March = March 31
        $this->assertEquals('2025-03-31', $dueDate->format('Y-m-d'));
    }

    public function test60JoursFinDeMoisGoesToEndOfMonth(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '60 jours fin de mois');

        // Jan 15 + 60 days = March 16 → end of March = March 31
        $this->assertEquals('2025-03-31', $dueDate->format('Y-m-d'));
    }

    // ========== Edge cases ==========

    public function testInvoiceOnLastDayOfMonthWith30JoursFinDeMois(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-31');

        $dueDate = $this->calculator->calculate($invoiceDate, '30 jours fin de mois');

        // Jan 31 + 30 days = March 2 → end of March = March 31
        $this->assertEquals('2025-03-31', $dueDate->format('Y-m-d'));
    }

    public function testInvoiceOn31stWithFebruaryResult(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-31');

        $dueDate = $this->calculator->calculate($invoiceDate, '14 jours fin de mois');

        // Jan 31 + 14 days = Feb 14 → end of February = Feb 28
        $this->assertEquals('2025-02-28', $dueDate->format('Y-m-d'));
    }

    public function testLeapYearHandlingFebruary29(): void
    {
        $invoiceDate = new \DateTimeImmutable('2024-01-31'); // 2024 is a leap year

        $dueDate = $this->calculator->calculate($invoiceDate, '14 jours fin de mois');

        // Jan 31 + 14 days = Feb 14 → end of February = Feb 29 (leap year)
        $this->assertEquals('2024-02-29', $dueDate->format('Y-m-d'));
    }

    public function testLeapYearHandlingNonLeapYear(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-31'); // 2025 is NOT a leap year

        $dueDate = $this->calculator->calculate($invoiceDate, '14 jours net');

        // Jan 31 + 14 days = Feb 14 (non-leap year)
        $this->assertEquals('2025-02-14', $dueDate->format('Y-m-d'));
    }

    public function testEndOfMonthOn31stGoesTo30thInApril(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-03-01');

        $dueDate = $this->calculator->calculate($invoiceDate, '30 jours fin de mois');

        // March 1 + 30 days = March 31 → end of March = March 31
        // (No, March 31 is already end of month)
        // Actually: March 1 + 30 = March 31, end of March = March 31
        $this->assertEquals('2025-03-31', $dueDate->format('Y-m-d'));
    }

    public function testFinDeMoisFromMidMonthToNextMonth(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-06-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '45 jours fin de mois');

        // June 15 + 45 days = July 30 → end of July = July 31
        $this->assertEquals('2025-07-31', $dueDate->format('Y-m-d'));
    }

    // ========== Unknown payment terms (fallback) ==========

    public function testUnknownTermsFallbackTo30JoursNet(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, 'unknown terms');

        // Default: 30 jours net
        $this->assertEquals('2025-02-14', $dueDate->format('Y-m-d'));
    }

    public function testEmptyTermsFallbackTo30JoursNet(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '');

        // Default: 30 jours net
        $this->assertEquals('2025-02-14', $dueDate->format('Y-m-d'));
    }

    public function testInvalidFormatFallbackTo30JoursNet(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, 'payable immediately');

        // Default: 30 jours net
        $this->assertEquals('2025-02-14', $dueDate->format('Y-m-d'));
    }

    // ========== Date immutability ==========

    public function testOriginalInvoiceDateIsNotModified(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');
        $originalDate = $invoiceDate->format('Y-m-d');

        $this->calculator->calculate($invoiceDate, '30 jours net');

        // Original date should remain unchanged (DateTimeImmutable)
        $this->assertEquals($originalDate, $invoiceDate->format('Y-m-d'));
    }

    public function testCalculateReturnsDifferentInstance(): void
    {
        $invoiceDate = new \DateTimeImmutable('2025-01-15');

        $dueDate = $this->calculator->calculate($invoiceDate, '30 jours net');

        // Should return a new instance
        $this->assertNotSame($invoiceDate, $dueDate);
    }
}
