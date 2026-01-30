<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Enum;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\EReportingPaymentStatus;
use PHPUnit\Framework\TestCase;

final class EReportingPaymentStatusTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = EReportingPaymentStatus::cases();

        $this->assertContains(EReportingPaymentStatus::NOT_PAID, $cases);
        $this->assertContains(EReportingPaymentStatus::PARTIALLY_PAID, $cases);
        $this->assertContains(EReportingPaymentStatus::FULLY_PAID, $cases);
        $this->assertCount(3, $cases);
    }

    public function testNotPaidValues(): void
    {
        $status = EReportingPaymentStatus::NOT_PAID;

        $this->assertSame('not_paid', $status->value);
        $this->assertSame('Non payé', $status->getLabel());
        $this->assertFalse($status->isPaid());
        $this->assertFalse($status->isPartiallyPaid());
    }

    public function testPartiallyPaidValues(): void
    {
        $status = EReportingPaymentStatus::PARTIALLY_PAID;

        $this->assertSame('partially_paid', $status->value);
        $this->assertSame('Partiellement payé', $status->getLabel());
        $this->assertFalse($status->isPaid());
        $this->assertTrue($status->isPartiallyPaid());
    }

    public function testFullyPaidValues(): void
    {
        $status = EReportingPaymentStatus::FULLY_PAID;

        $this->assertSame('fully_paid', $status->value);
        $this->assertSame('Payé', $status->getLabel());
        $this->assertTrue($status->isPaid());
        $this->assertFalse($status->isPartiallyPaid());
    }
}
