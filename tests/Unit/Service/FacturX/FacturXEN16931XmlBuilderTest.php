<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXEN16931XmlBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacturXEN16931XmlBuilder::class)]
final class FacturXEN16931XmlBuilderTest extends TestCase
{
    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private FacturXEN16931XmlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new FacturXEN16931XmlBuilder();
    }

    // ========================================
    // XML Structure Tests
    // ========================================

    public function testBuildReturnsValidXmlString(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        self::assertIsString($xml);
        self::assertStringStartsWith('<?xml', $xml);

        // Validate XML is well-formed
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xml);
        self::assertTrue($result, 'XML must be well-formed');
    }

    public function testXmlHasCorrectRootElementAndNamespaces(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        self::assertStringContainsString('<rsm:CrossIndustryInvoice', $xml);
        self::assertStringContainsString('xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"', $xml);
        self::assertStringContainsString('xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"', $xml);
        self::assertStringContainsString('xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100"', $xml);
    }

    public function testXmlContainsAllRequiredSections(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        self::assertCount(1, $xpath->query('//rsm:ExchangedDocumentContext'));
        self::assertCount(1, $xpath->query('//rsm:ExchangedDocument'));
        self::assertCount(1, $xpath->query('//rsm:SupplyChainTradeTransaction'));
    }

    public function testXmlProfileSpecifiesEN16931Level(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $profileNodes = $xpath->query('//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        self::assertCount(1, $profileNodes);
        self::assertSame('urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:en16931', $profileNodes->item(0)?->nodeValue);
    }

    public function testXmlContainsBusinessProcessParameter(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $businessNodes = $xpath->query('//rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID');
        self::assertCount(1, $businessNodes);
        self::assertSame('A1', $businessNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Document Mapping Tests
    // ========================================

    public function testMapsInvoiceNumberToXml(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setNumber('FA-2025-0042');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $numberNodes = $xpath->query('//rsm:ExchangedDocument/ram:ID');
        self::assertCount(1, $numberNodes);
        self::assertSame('FA-2025-0042', $numberNodes->item(0)?->nodeValue);
    }

    public function testMapsInvoiceDateToXmlWithCorrectFormat(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        $dateNodes = $xpath->query('//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format="102"]');
        self::assertCount(1, $dateNodes);
        self::assertMatchesRegularExpression('/^\d{8}$/', (string) $dateNodes->item(0)?->nodeValue);
        self::assertSame('20250115', $dateNodes->item(0)?->nodeValue);
    }

    public function testMapsInvoiceTypeCodeCorrectly(): void
    {
        $invoice = $this->createTestInvoice(InvoiceType::INVOICE);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $typeCodeNodes = $xpath->query('//rsm:ExchangedDocument/ram:TypeCode');
        self::assertCount(1, $typeCodeNodes);
        self::assertSame('380', $typeCodeNodes->item(0)?->nodeValue);
    }

    public function testMapsCreditNoteTypeCodeCorrectly(): void
    {
        $invoice = $this->createTestInvoice(InvoiceType::CREDIT_NOTE);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $typeCodeNodes = $xpath->query('//rsm:ExchangedDocument/ram:TypeCode');
        self::assertCount(1, $typeCodeNodes);
        self::assertSame('381', $typeCodeNodes->item(0)?->nodeValue);
    }

    public function testIncludesPaymentTermsAsNote(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setPaymentTerms('30 jours net');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $noteNodes = $xpath->query('//rsm:ExchangedDocument/ram:IncludedNote/ram:Content');
        self::assertCount(1, $noteNodes);
        self::assertSame('30 jours net', $noteNodes->item(0)?->nodeValue);
    }

    // ========================================
    // EN16931 Specific Fields Tests
    // ========================================

    public function testMapsBuyerReferenceWhenPresent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setBuyerReference('PO-2025-001');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $buyerRefNodes = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference');
        self::assertCount(1, $buyerRefNodes);
        self::assertSame('PO-2025-001', $buyerRefNodes->item(0)?->nodeValue);
    }

    public function testMapsPurchaseOrderReferenceWhenPresent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setPurchaseOrderReference('ORDER-12345');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $orderRefNodes = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID');
        self::assertCount(1, $orderRefNodes);
        self::assertSame('ORDER-12345', $orderRefNodes->item(0)?->nodeValue);
    }

    public function testMapsAccountingReferenceWhenPresent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setAccountingReference('ACC-REF-001');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $accountingRefNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID');
        self::assertCount(1, $accountingRefNodes);
        self::assertSame('ACC-REF-001', $accountingRefNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Seller/Company Mapping Tests
    // ========================================

    public function testMapsCompanyNameToSellerTradeParty(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $nameNodes = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:Name');
        self::assertCount(1, $nameNodes);
        self::assertSame('Test Company SARL', $nameNodes->item(0)?->nodeValue);
    }

    public function testMapsCompanySiretWithSchemeId(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $siretNodes = $xpath->query('//ram:SellerTradeParty/ram:ID[@schemeID="0002"]');
        self::assertCount(1, $siretNodes);
        self::assertSame('12345678901234', $siretNodes->item(0)?->nodeValue);
    }

    public function testMapsCompanyVatNumberToSellerTaxRegistration(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $vatNodes = $xpath->query('//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]');
        self::assertCount(1, $vatNodes);
        self::assertSame('FR12345678901', $vatNodes->item(0)?->nodeValue);
    }

    public function testMapsSellerContactWithPhoneAndEmail(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Phone
        $phoneNodes = $xpath->query('//ram:SellerTradeParty/ram:DefinedTradeContact/ram:TelephoneUniversalCommunication/ram:CompleteNumber');
        self::assertCount(1, $phoneNodes);
        self::assertSame('+33 1 23 45 67 89', $phoneNodes->item(0)?->nodeValue);

        // Email
        $emailNodes = $xpath->query('//ram:SellerTradeParty/ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID');
        self::assertCount(1, $emailNodes);
        self::assertSame('contact@testcompany.fr', $emailNodes->item(0)?->nodeValue);
    }

    public function testMapsStructuredSellerPostalAddress(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = new CompanyData(
            name: 'Test Company SARL',
            address: '123 Test Street',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            email: 'contact@testcompany.fr',
            phone: '+33 1 23 45 67 89',
            city: 'Paris',
            postalCode: '75001',
            countryCode: 'FR',
        );

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $addressNodes = $xpath->query('//ram:SellerTradeParty/ram:PostalTradeAddress');
        self::assertCount(1, $addressNodes);

        $postcodeNodes = $xpath->query('.//ram:PostcodeCode', $addressNodes->item(0));
        self::assertCount(1, $postcodeNodes);
        self::assertSame('75001', $postcodeNodes->item(0)?->nodeValue);

        $cityNodes = $xpath->query('.//ram:CityName', $addressNodes->item(0));
        self::assertCount(1, $cityNodes);
        self::assertSame('Paris', $cityNodes->item(0)?->nodeValue);

        $countryNodes = $xpath->query('.//ram:CountryID', $addressNodes->item(0));
        self::assertCount(1, $countryNodes);
        self::assertSame('FR', $countryNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Buyer/Customer Mapping Tests
    // ========================================

    public function testMapsCustomerNameToBuyerTradeParty(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $nameNodes = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:Name');
        self::assertCount(1, $nameNodes);
        self::assertSame('Test Customer SA', $nameNodes->item(0)?->nodeValue);
    }

    public function testMapsCustomerSiretWithSchemeId(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setCustomerSiret('98765432109876');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $siretNodes = $xpath->query('//ram:BuyerTradeParty/ram:ID[@schemeID="0002"]');
        self::assertCount(1, $siretNodes);
        self::assertSame('98765432109876', $siretNodes->item(0)?->nodeValue);
    }

    public function testMapsBuyerContactEmail(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setCustomerEmail('buyer@example.com');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $emailNodes = $xpath->query('//ram:BuyerTradeParty/ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID');
        self::assertCount(1, $emailNodes);
        self::assertSame('buyer@example.com', $emailNodes->item(0)?->nodeValue);
    }

    public function testMapsStructuredBuyerPostalAddress(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setCustomerCity('Lyon');
        $invoice->setCustomerPostalCode('69001');
        $invoice->setCustomerCountryCode('FR');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $addressNodes = $xpath->query('//ram:BuyerTradeParty/ram:PostalTradeAddress');
        self::assertCount(1, $addressNodes);

        $postcodeNodes = $xpath->query('.//ram:PostcodeCode', $addressNodes->item(0));
        self::assertCount(1, $postcodeNodes);
        self::assertSame('69001', $postcodeNodes->item(0)?->nodeValue);

        $cityNodes = $xpath->query('.//ram:CityName', $addressNodes->item(0));
        self::assertCount(1, $cityNodes);
        self::assertSame('Lyon', $cityNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Delivery Address Tests (BG-15)
    // ========================================

    public function testMapsDeliveryAddressWhenPresent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setDeliveryAddressLine1('789 Delivery Street');
        $invoice->setDeliveryCity('Marseille');
        $invoice->setDeliveryPostalCode('13001');
        $invoice->setDeliveryCountryCode('FR');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $shipToNodes = $xpath->query('//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress');
        self::assertCount(1, $shipToNodes);

        $lineOneNodes = $xpath->query('.//ram:LineOne', $shipToNodes->item(0));
        self::assertSame('789 Delivery Street', $lineOneNodes->item(0)?->nodeValue);

        $cityNodes = $xpath->query('.//ram:CityName', $shipToNodes->item(0));
        self::assertSame('Marseille', $cityNodes->item(0)?->nodeValue);
    }

    public function testIncludesActualDeliveryDate(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        $deliveryDateNodes = $xpath->query('//ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString');
        self::assertCount(1, $deliveryDateNodes);
        self::assertSame('20250115', $deliveryDateNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Payment Means Tests
    // ========================================

    public function testMapsPaymentMeansWithSEPATransfer(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Type code 58 = SEPA Credit Transfer
        $typeCodeNodes = $xpath->query('//ram:SpecifiedTradeSettlementPaymentMeans/ram:TypeCode');
        self::assertCount(1, $typeCodeNodes);
        self::assertSame('58', $typeCodeNodes->item(0)?->nodeValue);
    }

    public function testMapsIbanAndBic(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // IBAN
        $ibanNodes = $xpath->query('//ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeePartyCreditorFinancialAccount/ram:IBANID');
        self::assertCount(1, $ibanNodes);
        self::assertSame('FR7612345678901234567890123', $ibanNodes->item(0)?->nodeValue);

        // BIC
        $bicNodes = $xpath->query('//ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID');
        self::assertCount(1, $bicNodes);
        self::assertSame('TESTFRPP', $bicNodes->item(0)?->nodeValue);
    }

    public function testIncludesDueDateInPaymentTerms(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        $dueDateNodes = $xpath->query('//ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString');
        self::assertCount(1, $dueDateNodes);
        self::assertSame('20250214', $dueDateNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Line Items Tests
    // ========================================

    public function testMapsInvoiceLinesToXml(): void
    {
        $invoice = $this->createTestInvoiceWithMultipleLines();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $lineNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem');
        self::assertCount(2, $lineNodes);
    }

    public function testMapsLineWithItemIdentifier(): void
    {
        $invoice = $this->createTestInvoice();
        $line = $invoice->getLines()[0];
        $line->setItemIdentifier('SKU-001');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $skuNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:SellerAssignedID');
        self::assertCount(1, $skuNodes);
        self::assertSame('SKU-001', $skuNodes->item(0)?->nodeValue);
    }

    public function testMapsLineWithCountryOfOrigin(): void
    {
        $invoice = $this->createTestInvoice();
        $line = $invoice->getLines()[0];
        $line->setCountryOfOrigin('DE');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $countryNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:OriginTradeCountry/ram:ID');
        self::assertCount(1, $countryNodes);
        self::assertSame('DE', $countryNodes->item(0)?->nodeValue);
    }

    public function testResetsLineIdCounterBetweenBuilds(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        // First build
        $this->builder->build($invoice, $companyData);

        // Second build should start from 1 again
        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $lineIdNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:AssociatedDocumentLineDocument/ram:LineID');
        self::assertCount(1, $lineIdNodes);
        self::assertSame('1', $lineIdNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Global Discount Tests
    // ========================================

    public function testMapsGlobalDiscountAsAllowance(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setGlobalDiscountAmount(Money::fromEuros('100.00'));
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        $allowanceNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge');
        self::assertCount(1, $allowanceNodes);

        // Check ChargeIndicator is false (allowance, not charge)
        $indicatorNodes = $xpath->query('.//udt:Indicator', $allowanceNodes->item(0));
        self::assertSame('false', $indicatorNodes->item(0)?->nodeValue);

        // Check amount
        $amountNodes = $xpath->query('.//ram:ActualAmount', $allowanceNodes->item(0));
        self::assertSame('100.00', $amountNodes->item(0)?->nodeValue);

        // Check reason
        $reasonNodes = $xpath->query('.//ram:Reason', $allowanceNodes->item(0));
        self::assertSame('Remise globale', $reasonNodes->item(0)?->nodeValue);
    }

    // ========================================
    // VAT Exemption Reasons Tests
    // ========================================

    public function testMapsVatExemptionReasonForExemptCategory(): void
    {
        $invoice = $this->createTestInvoice();
        $line = $invoice->getLines()[0];
        $line->setVatRate(0.0);
        $line->setTaxCategoryCode(TaxCategoryCode::EXEMPT);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $exemptionNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason');
        self::assertCount(1, $exemptionNodes);
        self::assertSame('Exonération de TVA', $exemptionNodes->item(0)?->nodeValue);
    }

    public function testMapsVatExemptionReasonForReverseCharge(): void
    {
        $invoice = $this->createTestInvoice();
        $line = $invoice->getLines()[0];
        $line->setVatRate(0.0);
        $line->setTaxCategoryCode(TaxCategoryCode::REVERSE_CHARGE);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $exemptionNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason');
        self::assertCount(1, $exemptionNodes);
        self::assertSame('Autoliquidation - Article 283 du CGI', $exemptionNodes->item(0)?->nodeValue);
    }

    public function testMapsVatExemptionReasonForIntraEu(): void
    {
        $invoice = $this->createTestInvoice();
        $line = $invoice->getLines()[0];
        $line->setVatRate(0.0);
        $line->setTaxCategoryCode(TaxCategoryCode::INTRA_EU);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $exemptionNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason');
        self::assertCount(1, $exemptionNodes);
        self::assertSame('Livraison intracommunautaire exonérée - Article 262 ter du CGI', $exemptionNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Credit Note Reference Tests
    // ========================================

    public function testMapsCreditNoteWithOriginalInvoiceReference(): void
    {
        $originalInvoice = $this->createTestInvoice();
        $originalInvoice->setNumber('FA-2025-0010');

        $creditNote = $this->createTestInvoice(InvoiceType::CREDIT_NOTE);
        $creditNote->setCreditedInvoice($originalInvoice);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($creditNote, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $refInvoiceNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID');
        self::assertCount(1, $refInvoiceNodes);
        self::assertSame('FA-2025-0010', $refInvoiceNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Monetary Summation Tests
    // ========================================

    public function testCalculatesMonetarySummation(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Line total
        $lineTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount');
        self::assertSame('1000.00', $lineTotalNodes->item(0)?->nodeValue);

        // Charge total (always 0.00 in current implementation)
        $chargeTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:ChargeTotalAmount');
        self::assertSame('0.00', $chargeTotalNodes->item(0)?->nodeValue);

        // Tax basis
        $taxBasisNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount');
        self::assertSame('1000.00', $taxBasisNodes->item(0)?->nodeValue);

        // Tax total with currency
        $taxTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID="EUR"]');
        self::assertSame('200.00', $taxTotalNodes->item(0)?->nodeValue);

        // Grand total
        $grandTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount');
        self::assertSame('1200.00', $grandTotalNodes->item(0)?->nodeValue);

        // Due payable amount
        $duePayableNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount');
        self::assertSame('1200.00', $duePayableNodes->item(0)?->nodeValue);
    }

    // ========================================
    // Profile Tests
    // ========================================

    public function testGetProfileReturnsEN16931(): void
    {
        self::assertSame(FacturXProfile::EN16931, $this->builder->getProfile());
    }

    public function testSupportsReturnsTrueForEN16931Profile(): void
    {
        self::assertTrue($this->builder->supports(FacturXProfile::EN16931));
    }

    public function testSupportsReturnsFalseForOtherProfiles(): void
    {
        self::assertFalse($this->builder->supports(FacturXProfile::MINIMUM));
        self::assertFalse($this->builder->supports(FacturXProfile::BASIC_WL));
        self::assertFalse($this->builder->supports(FacturXProfile::BASIC));
        self::assertFalse($this->builder->supports(FacturXProfile::EXTENDED));
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createTestInvoice(?InvoiceType $type = null): Invoice
    {
        $invoice = new Invoice(
            type: $type ?? InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: 'Test Customer SA',
            customerAddress: '456 Customer Street, 75002 Paris, France',
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris, France',
        );

        $invoice->setStatus(InvoiceStatus::FINALIZED);
        $invoice->setNumber('FA-2025-0001');
        $invoice->setCompanyId(1);
        $invoice->setCompanySiret('12345678901234');
        $invoice->setCompanyVatNumber('FR12345678901');

        $line = new InvoiceLine(
            description: 'Service de développement',
            quantity: 10,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line);

        return $invoice;
    }

    private function createTestInvoiceWithMultipleLines(): Invoice
    {
        $invoice = $this->createTestInvoice();

        $line2 = new InvoiceLine(
            description: 'Service de conseil',
            quantity: 5,
            unitPrice: Money::fromEuros('150.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line2);

        return $invoice;
    }

    private function createTestCompanyData(): CompanyData
    {
        return new CompanyData(
            name: 'Test Company SARL',
            address: '123 Test Street, 75001 Paris, France',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            email: 'contact@testcompany.fr',
            phone: '+33 1 23 45 67 89',
            bankName: 'Test Bank',
            iban: 'FR7612345678901234567890123',
            bic: 'TESTFRPP',
        );
    }
}
