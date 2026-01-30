<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Exception\FacturXBuilderNotFoundException;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilder;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilderFactory;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilderInterface;
use PHPUnit\Framework\TestCase;

final class FacturXXmlBuilderFactoryTest extends TestCase
{
    // ========================================
    // Constructor Tests (2 tests)
    // ========================================

    public function testConstructorWithEmptyBuilders(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);

        $this->assertSame([], $factory->getSupportedProfiles());
    }

    public function testConstructorRegistersBuilders(): void
    {
        $basicBuilder = new FacturXXmlBuilder();
        $factory = new FacturXXmlBuilderFactory([$basicBuilder]);

        $this->assertSame([FacturXProfile::BASIC], $factory->getSupportedProfiles());
    }

    // ========================================
    // getBuilder Tests (3 tests)
    // ========================================

    public function testGetBuilderReturnsCorrectBuilder(): void
    {
        $basicBuilder = new FacturXXmlBuilder();
        $factory = new FacturXXmlBuilderFactory([$basicBuilder]);

        $result = $factory->getBuilder(FacturXProfile::BASIC);

        $this->assertSame($basicBuilder, $result);
    }

    public function testGetBuilderThrowsExceptionForUnsupportedProfile(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);

        $this->expectException(FacturXBuilderNotFoundException::class);
        $this->expectExceptionMessage('No Factur-X XML builder found for profile "EN16931"');

        $factory->getBuilder(FacturXProfile::EN16931);
    }

    public function testGetBuilderExceptionContainsProfile(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);

        try {
            $factory->getBuilder(FacturXProfile::EXTENDED);
            $this->fail('Expected FacturXBuilderNotFoundException');
        } catch (FacturXBuilderNotFoundException $e) {
            $this->assertSame(FacturXProfile::EXTENDED, $e->getProfile());
        }
    }

    // ========================================
    // hasBuilder Tests (2 tests)
    // ========================================

    public function testHasBuilderReturnsTrueWhenBuilderExists(): void
    {
        $basicBuilder = new FacturXXmlBuilder();
        $factory = new FacturXXmlBuilderFactory([$basicBuilder]);

        $this->assertTrue($factory->hasBuilder(FacturXProfile::BASIC));
    }

    public function testHasBuilderReturnsFalseWhenBuilderDoesNotExist(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);

        $this->assertFalse($factory->hasBuilder(FacturXProfile::BASIC));
        $this->assertFalse($factory->hasBuilder(FacturXProfile::EN16931));
    }

    // ========================================
    // getSupportedProfiles Tests (2 tests)
    // ========================================

    public function testGetSupportedProfilesReturnsEmptyArrayWhenNoBuilders(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);

        $this->assertSame([], $factory->getSupportedProfiles());
    }

    public function testGetSupportedProfilesReturnsAllRegisteredProfiles(): void
    {
        $basicBuilder = new FacturXXmlBuilder();

        // Mock an EN16931 builder
        $en16931Builder = $this->createMock(FacturXXmlBuilderInterface::class);
        $en16931Builder->method('getProfile')->willReturn(FacturXProfile::EN16931);

        $factory = new FacturXXmlBuilderFactory([$basicBuilder, $en16931Builder]);

        $profiles = $factory->getSupportedProfiles();

        $this->assertCount(2, $profiles);
        $this->assertContains(FacturXProfile::BASIC, $profiles);
        $this->assertContains(FacturXProfile::EN16931, $profiles);
    }

    // ========================================
    // registerBuilder Tests (2 tests)
    // ========================================

    public function testRegisterBuilderAddsNewBuilder(): void
    {
        $factory = new FacturXXmlBuilderFactory([]);
        $basicBuilder = new FacturXXmlBuilder();

        $factory->registerBuilder($basicBuilder);

        $this->assertTrue($factory->hasBuilder(FacturXProfile::BASIC));
        $this->assertSame($basicBuilder, $factory->getBuilder(FacturXProfile::BASIC));
    }

    public function testRegisterBuilderReplacesExistingBuilder(): void
    {
        $basicBuilder1 = new FacturXXmlBuilder();
        $factory = new FacturXXmlBuilderFactory([$basicBuilder1]);

        $basicBuilder2 = new FacturXXmlBuilder();
        $factory->registerBuilder($basicBuilder2);

        $this->assertSame($basicBuilder2, $factory->getBuilder(FacturXProfile::BASIC));
        $this->assertNotSame($basicBuilder1, $factory->getBuilder(FacturXProfile::BASIC));
    }

    // ========================================
    // Multiple Builders Tests (2 tests)
    // ========================================

    public function testFactoryWithMultipleBuilders(): void
    {
        $basicBuilder = new FacturXXmlBuilder();

        $minimumBuilder = $this->createMock(FacturXXmlBuilderInterface::class);
        $minimumBuilder->method('getProfile')->willReturn(FacturXProfile::MINIMUM);

        $en16931Builder = $this->createMock(FacturXXmlBuilderInterface::class);
        $en16931Builder->method('getProfile')->willReturn(FacturXProfile::EN16931);

        $factory = new FacturXXmlBuilderFactory([
            $basicBuilder,
            $minimumBuilder,
            $en16931Builder,
        ]);

        $this->assertSame($basicBuilder, $factory->getBuilder(FacturXProfile::BASIC));
        $this->assertSame($minimumBuilder, $factory->getBuilder(FacturXProfile::MINIMUM));
        $this->assertSame($en16931Builder, $factory->getBuilder(FacturXProfile::EN16931));
    }

    public function testFactoryBuilderCanBuildXml(): void
    {
        $basicBuilder = new FacturXXmlBuilder();
        $factory = new FacturXXmlBuilderFactory([$basicBuilder]);

        $builder = $factory->getBuilder(FacturXProfile::BASIC);

        // Verify the builder can actually build XML
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getNumber')->willReturn('FA-2025-0001');
        $invoice->method('getDate')->willReturn(new \DateTimeImmutable());
        $invoice->method('getDueDate')->willReturn(new \DateTimeImmutable('+30 days'));
        $invoice->method('getType')->willReturn(\CorentinBoutillier\InvoiceBundle\Enum\InvoiceType::INVOICE);
        $invoice->method('getLines')->willReturn([]);
        $invoice->method('getCustomerName')->willReturn('Test Customer');
        $invoice->method('getCustomerAddress')->willReturn('123 Test St');
        $invoice->method('getCustomerSiret')->willReturn(null);
        $invoice->method('getCustomerVatNumber')->willReturn(null);
        $invoice->method('getGlobalDiscountAmount')->willReturn(\CorentinBoutillier\InvoiceBundle\DTO\Money::fromCents(0));
        $invoice->method('getSubtotalBeforeDiscount')->willReturn(\CorentinBoutillier\InvoiceBundle\DTO\Money::fromCents(0));
        $invoice->method('getSubtotalAfterDiscount')->willReturn(\CorentinBoutillier\InvoiceBundle\DTO\Money::fromCents(0));
        $invoice->method('getTotalVat')->willReturn(\CorentinBoutillier\InvoiceBundle\DTO\Money::fromCents(0));
        $invoice->method('getTotalIncludingVat')->willReturn(\CorentinBoutillier\InvoiceBundle\DTO\Money::fromCents(0));
        $invoice->method('getCreditedInvoice')->willReturn(null);

        $companyData = new CompanyData(
            name: 'Test Company',
            address: '456 Company St',
        );

        $xml = $builder->build($invoice, $companyData);

        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('CrossIndustryInvoice', $xml);
    }
}
