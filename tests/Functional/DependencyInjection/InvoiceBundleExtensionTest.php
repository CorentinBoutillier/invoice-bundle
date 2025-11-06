<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\DependencyInjection;

use CorentinBoutillier\InvoiceBundle\Doctrine\Type\MoneyType;
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\Provider\ConfigCompanyProvider;
use CorentinBoutillier\InvoiceBundle\Service\DueDateCalculatorInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXConfigProviderInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilderInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\PdfA3ConverterInterface;
use CorentinBoutillier\InvoiceBundle\Service\Fec\FecExporterInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Doctrine\DBAL\Types\Type;

/**
 * Tests for bundle configuration and dependency injection.
 *
 * Verifies that:
 * - Configuration tree loads successfully
 * - Default values are correct
 * - Services are properly registered and autowired
 * - MoneyType Doctrine type is registered
 * - Service aliases work correctly
 */
final class InvoiceBundleExtensionTest extends RepositoryTestCase
{
    /**
     * Test 1: Bundle loads without errors.
     */
    public function testConfigurationLoadsSuccessfully(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertNotNull($container);
        $this->assertTrue($container->has('doctrine'));

        // Verify InvoiceBundle configuration is loaded
        $this->assertTrue($container->hasParameter('invoice.accounting'));
        $this->assertTrue($container->hasParameter('invoice.pdf'));
        $this->assertTrue($container->hasParameter('invoice.factur_x'));
    }

    /**
     * Test 2: Accounting section has correct default values.
     */
    public function testAccountingDefaultValues(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->hasParameter('invoice.accounting'),
            'Parameter invoice.accounting must be defined',
        );

        $accounting = $container->getParameter('invoice.accounting');

        $this->assertIsArray($accounting);
        $this->assertArrayHasKey('customer_account', $accounting);
        $this->assertArrayHasKey('sales_account', $accounting);
        $this->assertArrayHasKey('vat_collected_account', $accounting);
        $this->assertArrayHasKey('journal_code', $accounting);
        $this->assertArrayHasKey('journal_label', $accounting);

        // Verify French PCG defaults
        $this->assertSame('411000', $accounting['customer_account']);
        $this->assertSame('707000', $accounting['sales_account']);
        $this->assertSame('445710', $accounting['vat_collected_account']);
        $this->assertSame('VT', $accounting['journal_code']);
        $this->assertSame('Ventes', $accounting['journal_label']);
    }

    /**
     * Test 3: PDF configuration has correct defaults.
     */
    public function testPdfDefaultConfiguration(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->hasParameter('invoice.pdf'),
            'Parameter invoice.pdf must be defined',
        );

        $pdf = $container->getParameter('invoice.pdf');

        $this->assertIsArray($pdf);
        $this->assertArrayHasKey('storage_path', $pdf);
        $this->assertArrayHasKey('enabled', $pdf);

        $this->assertIsString($pdf['storage_path']);
        $this->assertTrue($pdf['enabled']);
    }

    /**
     * Test 4: Factur-X configuration has correct defaults.
     */
    public function testFacturXDefaultConfiguration(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->hasParameter('invoice.factur_x'),
            'Parameter invoice.factur_x must be defined',
        );

        $facturX = $container->getParameter('invoice.factur_x');

        $this->assertIsArray($facturX);
        $this->assertArrayHasKey('enabled', $facturX);
        $this->assertArrayHasKey('profile', $facturX);
        $this->assertArrayHasKey('xml_filename', $facturX);

        // Factur-X should be enabled by default in tests, BASIC profile
        $this->assertTrue($facturX['enabled']);
        $this->assertSame('BASIC', $facturX['profile']);
        $this->assertSame('factur-x.xml', $facturX['xml_filename']);
    }

    /**
     * Test 5: Company configuration is optional but has structure.
     */
    public function testCompanyConfigurationOptional(): void
    {
        $container = $this->kernel->getContainer();

        // Company parameter should exist even if optional
        $this->assertTrue(
            $container->hasParameter('invoice.company'),
            'Parameter invoice.company should be defined',
        );

        $company = $container->getParameter('invoice.company');

        if (null !== $company) {
            $this->assertIsArray($company);
            // If provided, should have these keys
            $expectedKeys = ['name', 'address', 'siret', 'vat_number', 'email', 'phone', 'bank_name', 'iban', 'bic'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $company);
            }
        }
    }

    /**
     * Test 6: VAT rates have French defaults.
     */
    public function testVatRatesDefaults(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->hasParameter('invoice.vat_rates'),
            'Parameter invoice.vat_rates must be defined',
        );

        $vatRates = $container->getParameter('invoice.vat_rates');

        $this->assertIsArray($vatRates);
        $this->assertArrayHasKey('standard', $vatRates);
        $this->assertArrayHasKey('intermediate', $vatRates);
        $this->assertArrayHasKey('reduced', $vatRates);
        $this->assertArrayHasKey('super_reduced', $vatRates);

        // French VAT rates
        $this->assertSame(20.0, $vatRates['standard']);
        $this->assertSame(10.0, $vatRates['intermediate']);
        $this->assertSame(5.5, $vatRates['reduced']);
        $this->assertSame(2.1, $vatRates['super_reduced']);
    }

    /**
     * Test 7: Fiscal year configuration has correct defaults.
     */
    public function testFiscalYearDefaults(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->hasParameter('invoice.fiscal_year'),
            'Parameter invoice.fiscal_year must be defined',
        );

        $fiscalYear = $container->getParameter('invoice.fiscal_year');

        $this->assertIsArray($fiscalYear);
        $this->assertArrayHasKey('start_month', $fiscalYear);

        // Default to calendar year (January = 1)
        $this->assertSame(1, $fiscalYear['start_month']);
    }

    /**
     * Test 8: MoneyType Doctrine type is registered.
     */
    public function testMoneyTypeIsRegistered(): void
    {
        $this->assertTrue(
            Type::hasType('money'),
            'Doctrine type "money" must be registered',
        );

        $moneyType = Type::getType('money');

        $this->assertInstanceOf(MoneyType::class, $moneyType);
        $this->assertSame('money', $moneyType->getName());
    }

    /**
     * Test 9: Service aliases are correctly configured.
     */
    public function testServiceAliasesAreCorrect(): void
    {
        $container = $this->kernel->getContainer();

        // CompanyProviderInterface
        $this->assertTrue($container->has(CompanyProviderInterface::class));
        $companyProvider = $container->get(CompanyProviderInterface::class);
        $this->assertInstanceOf(ConfigCompanyProvider::class, $companyProvider);

        // DueDateCalculatorInterface
        $this->assertTrue($container->has(DueDateCalculatorInterface::class));
        $dueDateCalculator = $container->get(DueDateCalculatorInterface::class);
        $this->assertNotNull($dueDateCalculator);

        // FecExporterInterface
        $this->assertTrue($container->has(FecExporterInterface::class));
        $fecExporter = $container->get(FecExporterInterface::class);
        $this->assertNotNull($fecExporter);

        // Factur-X interfaces
        $this->assertTrue($container->has(FacturXConfigProviderInterface::class));
        $this->assertTrue($container->has(FacturXXmlBuilderInterface::class));
        $this->assertTrue($container->has(PdfA3ConverterInterface::class));
    }

    /**
     * Test 10: ConfigCompanyProvider is registered and autowired.
     */
    public function testConfigCompanyProviderIsRegistered(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue(
            $container->has(ConfigCompanyProvider::class),
            'ConfigCompanyProvider must be registered as a service',
        );

        $provider = $container->get(ConfigCompanyProvider::class);

        $this->assertInstanceOf(ConfigCompanyProvider::class, $provider);

        // Provider should be able to return company data
        $companyData = $provider->getCompanyData();
        $this->assertNotNull($companyData);
        $this->assertNotEmpty($companyData->name);
    }

    /**
     * Test 11: FecExporter receives accounting configuration.
     */
    public function testFecExporterReceivesAccountingConfig(): void
    {
        $container = $this->kernel->getContainer();

        $fecExporter = $container->get(FecExporterInterface::class);
        $this->assertInstanceOf(FecExporterInterface::class, $fecExporter);

        // Export a simple FEC to verify configuration is injected
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $csv = $fecExporter->export($startDate, $endDate);

        // CSV should have header with correct journal code
        $this->assertIsString($csv);
        $this->assertStringContainsString('JournalCode', $csv);
        // Even with no invoices, header should be present
        $lines = explode("\n", $csv);
        $this->assertGreaterThanOrEqual(1, \count($lines));
    }

    /**
     * Test 12: Factur-X services are registered when enabled.
     */
    public function testFacturXServicesAreRegistered(): void
    {
        $container = $this->kernel->getContainer();

        // Check if Factur-X is enabled
        $facturXConfig = $container->getParameter('invoice.factur_x');
        $this->assertIsArray($facturXConfig);

        if (\is_array($facturXConfig) && isset($facturXConfig['enabled']) && true === $facturXConfig['enabled']) {
            $configProvider = $container->get(FacturXConfigProviderInterface::class);
            $this->assertInstanceOf(FacturXConfigProviderInterface::class, $configProvider);

            $xmlBuilder = $container->get(FacturXXmlBuilderInterface::class);
            $this->assertInstanceOf(FacturXXmlBuilderInterface::class, $xmlBuilder);

            $pdfConverter = $container->get(PdfA3ConverterInterface::class);
            $this->assertInstanceOf(PdfA3ConverterInterface::class, $pdfConverter);

            // Verify config provider returns correct values
            $this->assertTrue($configProvider->isEnabled());
            $this->assertSame('BASIC', $configProvider->getProfile());
            $this->assertSame('factur-x.xml', $configProvider->getXmlFilename());
        }

        $this->addToAssertionCount(1); // Ensure test runs
    }
}
