<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\EReporting\Enum;

use CorentinBoutillier\InvoiceBundle\EReporting\Enum\TransactionType;
use PHPUnit\Framework\TestCase;

final class TransactionTypeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = TransactionType::cases();

        $this->assertContains(TransactionType::B2B_FRANCE, $cases);
        $this->assertContains(TransactionType::B2B_INTRA_EU, $cases);
        $this->assertContains(TransactionType::B2B_EXPORT, $cases);
        $this->assertContains(TransactionType::B2C_FRANCE, $cases);
        $this->assertContains(TransactionType::B2C_INTRA_EU, $cases);
        $this->assertContains(TransactionType::B2C_EXPORT, $cases);
        $this->assertContains(TransactionType::B2G_FRANCE, $cases);
    }

    public function testB2BFranceValues(): void
    {
        $type = TransactionType::B2B_FRANCE;

        $this->assertSame('b2b_france', $type->value);
        $this->assertTrue($type->isB2B());
        $this->assertFalse($type->isB2C());
        $this->assertFalse($type->isB2G());
        $this->assertTrue($type->isDomestic());
        $this->assertFalse($type->isIntraEU());
        $this->assertFalse($type->isExport());
    }

    public function testB2BIntraEUValues(): void
    {
        $type = TransactionType::B2B_INTRA_EU;

        $this->assertSame('b2b_intra_eu', $type->value);
        $this->assertTrue($type->isB2B());
        $this->assertFalse($type->isDomestic());
        $this->assertTrue($type->isIntraEU());
        $this->assertFalse($type->isExport());
    }

    public function testB2BExportValues(): void
    {
        $type = TransactionType::B2B_EXPORT;

        $this->assertSame('b2b_export', $type->value);
        $this->assertTrue($type->isB2B());
        $this->assertFalse($type->isDomestic());
        $this->assertFalse($type->isIntraEU());
        $this->assertTrue($type->isExport());
    }

    public function testB2CFranceValues(): void
    {
        $type = TransactionType::B2C_FRANCE;

        $this->assertSame('b2c_france', $type->value);
        $this->assertFalse($type->isB2B());
        $this->assertTrue($type->isB2C());
        $this->assertFalse($type->isB2G());
        $this->assertTrue($type->isDomestic());
    }

    public function testB2CIntraEUValues(): void
    {
        $type = TransactionType::B2C_INTRA_EU;

        $this->assertSame('b2c_intra_eu', $type->value);
        $this->assertTrue($type->isB2C());
        $this->assertTrue($type->isIntraEU());
    }

    public function testB2CExportValues(): void
    {
        $type = TransactionType::B2C_EXPORT;

        $this->assertSame('b2c_export', $type->value);
        $this->assertTrue($type->isB2C());
        $this->assertTrue($type->isExport());
    }

    public function testB2GFranceValues(): void
    {
        $type = TransactionType::B2G_FRANCE;

        $this->assertSame('b2g_france', $type->value);
        $this->assertFalse($type->isB2B());
        $this->assertFalse($type->isB2C());
        $this->assertTrue($type->isB2G());
        $this->assertTrue($type->isDomestic());
    }

    public function testRequiresEReporting(): void
    {
        // B2C and B2B export require e-reporting
        $this->assertTrue(TransactionType::B2C_FRANCE->requiresEReporting());
        $this->assertTrue(TransactionType::B2C_INTRA_EU->requiresEReporting());
        $this->assertTrue(TransactionType::B2C_EXPORT->requiresEReporting());
        $this->assertTrue(TransactionType::B2B_EXPORT->requiresEReporting());

        // B2B France uses e-invoicing via PDP, not e-reporting
        $this->assertFalse(TransactionType::B2B_FRANCE->requiresEReporting());
        // B2B intra-EU uses e-invoicing or direct transmission
        $this->assertFalse(TransactionType::B2B_INTRA_EU->requiresEReporting());
        // B2G uses Chorus Pro
        $this->assertFalse(TransactionType::B2G_FRANCE->requiresEReporting());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('B2B France', TransactionType::B2B_FRANCE->getLabel());
        $this->assertSame('B2B Intra-UE', TransactionType::B2B_INTRA_EU->getLabel());
        $this->assertSame('B2B Export', TransactionType::B2B_EXPORT->getLabel());
        $this->assertSame('B2C France', TransactionType::B2C_FRANCE->getLabel());
        $this->assertSame('B2C Intra-UE', TransactionType::B2C_INTRA_EU->getLabel());
        $this->assertSame('B2C Export', TransactionType::B2C_EXPORT->getLabel());
        $this->assertSame('B2G France', TransactionType::B2G_FRANCE->getLabel());
    }
}
