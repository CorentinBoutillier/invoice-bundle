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
use CorentinBoutillier\InvoiceBundle\Enum\QuantityUnitCode;
use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilderInterface;
use PHPUnit\Framework\TestCase;

final class FacturXXmlBuilderTest extends TestCase
{
    private FacturXXmlBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->builder = new \CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilder();
    }

    // ========================================
    // XML Structure Tests (4 tests)
    // ========================================

    public function testBuildReturnsValidXmlString(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $this->assertIsString($xml);
        $this->assertStringStartsWith('<?xml', $xml);

        // Validate XML is well-formed
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xml);
        $this->assertTrue($result, 'XML must be well-formed');
    }

    public function testXmlHasCorrectRootElementAndNamespaces(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $this->assertStringContainsString('<rsm:CrossIndustryInvoice', $xml);
        $this->assertStringContainsString('xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"', $xml);
        $this->assertStringContainsString('xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"', $xml);
        $this->assertStringContainsString('xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100"', $xml);
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

        // Required sections for BASIC profile
        $this->assertCount(1, $xpath->query('//rsm:ExchangedDocumentContext'));
        $this->assertCount(1, $xpath->query('//rsm:ExchangedDocument'));
        $this->assertCount(1, $xpath->query('//rsm:SupplyChainTradeTransaction'));
    }

    public function testXmlProfileSpecifiesBasicLevel(): void
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
        $this->assertCount(1, $profileNodes);
        $this->assertSame('urn:factur-x.eu:1p0:basic', $profileNodes->item(0)->nodeValue);
    }

    // ========================================
    // Document Mapping Tests (4 tests)
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
        $this->assertCount(1, $numberNodes);
        $this->assertSame('FA-2025-0042', $numberNodes->item(0)->nodeValue);
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
        $this->assertCount(1, $dateNodes);
        // Format 102 = YYYYMMDD
        $this->assertMatchesRegularExpression('/^\d{8}$/', $dateNodes->item(0)->nodeValue);
        $this->assertSame('20250115', $dateNodes->item(0)->nodeValue);
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
        $this->assertCount(1, $typeCodeNodes);
        $this->assertSame('380', $typeCodeNodes->item(0)->nodeValue); // 380 = Commercial Invoice
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
        $this->assertCount(1, $typeCodeNodes);
        $this->assertSame('381', $typeCodeNodes->item(0)->nodeValue); // 381 = Credit Note
    }

    // ========================================
    // Seller/Company Mapping Tests (5 tests)
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
        $this->assertCount(1, $nameNodes);
        $this->assertSame('Test Company SARL', $nameNodes->item(0)->nodeValue);
    }

    public function testMapsCompanySiretToSellerLegalId(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // SIRET = French company ID (scheme 0002)
        $siretNodes = $xpath->query('//ram:SellerTradeParty/ram:ID[@schemeID="0002"]');
        $this->assertCount(1, $siretNodes);
        $this->assertSame('12345678901234', $siretNodes->item(0)->nodeValue);
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
        $this->assertCount(1, $vatNodes);
        $this->assertSame('FR12345678901', $vatNodes->item(0)->nodeValue);
    }

    public function testMapsCompanyAddressToSellerPostalAddress(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $addressNodes = $xpath->query('//ram:SellerTradeParty/ram:PostalTradeAddress');
        $this->assertCount(1, $addressNodes);

        // Full address in LineOne element
        $lineOneNodes = $xpath->query('.//ram:LineOne', $addressNodes->item(0));
        $this->assertCount(1, $lineOneNodes);
        $this->assertStringContainsString('123 Test Street', $lineOneNodes->item(0)->nodeValue);
    }

    public function testMapsCompanyBankingDetailsToSellerFinancialAccount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // IBAN in PayeePartyCreditorFinancialAccount
        $ibanNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeePartyCreditorFinancialAccount/ram:IBANID');
        $this->assertCount(1, $ibanNodes);
        $this->assertSame('FR7612345678901234567890123', $ibanNodes->item(0)->nodeValue);
    }

    // ========================================
    // Buyer/Customer Mapping Tests (3 tests)
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
        $this->assertCount(1, $nameNodes);
        $this->assertSame('Test Customer SA', $nameNodes->item(0)->nodeValue);
    }

    public function testMapsCustomerAddressToBuyerPostalAddress(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $addressNodes = $xpath->query('//ram:BuyerTradeParty/ram:PostalTradeAddress/ram:LineOne');
        $this->assertCount(1, $addressNodes);
        $this->assertStringContainsString('456 Customer Street', $addressNodes->item(0)->nodeValue);
    }

    public function testMapsCustomerVatNumberToBuyerTaxRegistration(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setCustomerVatNumber('FR98765432109');
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $vatNodes = $xpath->query('//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]');
        $this->assertCount(1, $vatNodes);
        $this->assertSame('FR98765432109', $vatNodes->item(0)->nodeValue);
    }

    // ========================================
    // Line Items Mapping Tests (4 tests)
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
        $this->assertCount(2, $lineNodes); // Invoice has 2 lines
    }

    public function testMapsLineDescriptionAndQuantity(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Description
        $descNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:Name');
        $this->assertCount(1, $descNodes);
        $this->assertSame('Service de développement', $descNodes->item(0)->nodeValue);

        // Quantity
        $qtyNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity');
        $this->assertCount(1, $qtyNodes);
        $this->assertSame('10.0000', $qtyNodes->item(0)->nodeValue);
    }

    public function testMapsLinePricesInEuros(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Unit price (converted from cents to euros)
        $priceNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount');
        $this->assertCount(1, $priceNodes);
        $this->assertSame('100.00', $priceNodes->item(0)->nodeValue); // 10000 cents = 100.00 euros
    }

    public function testMapsLineVatRate(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $vatNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent');
        $this->assertCount(1, $vatNodes);
        $this->assertSame('20.00', $vatNodes->item(0)->nodeValue);
    }

    // ========================================
    // Totals & VAT Calculation Tests (5 tests)
    // ========================================

    public function testCalculatesLineTotalAmount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // LineTotalAmount = quantity * unitPrice = 10 * 100.00 = 1000.00
        $lineTotalNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount');
        $this->assertCount(1, $lineTotalNodes);
        $this->assertSame('1000.00', $lineTotalNodes->item(0)->nodeValue);
    }

    public function testCalculatesSubtotalBeforeDiscount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $subtotalNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount');
        $this->assertCount(1, $subtotalNodes);
        $expectedSubtotal = $invoice->getSubtotalBeforeDiscount()->toEuros();
        $this->assertSame($expectedSubtotal, $subtotalNodes->item(0)->nodeValue);
    }

    public function testCalculatesTaxBasisTotalAmountAfterDiscount(): void
    {
        $invoice = $this->createTestInvoiceWithGlobalDiscount();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // TaxBasisTotalAmount = subtotal - global discount
        $taxBasisNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount');
        $this->assertCount(1, $taxBasisNodes);
        $expectedBasis = $invoice->getSubtotalAfterDiscount()->toEuros();
        $this->assertSame($expectedBasis, $taxBasisNodes->item(0)->nodeValue);
    }

    public function testCalculatesTotalVatAmount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $vatTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID="EUR"]');
        $this->assertCount(1, $vatTotalNodes);
        $expectedVat = $invoice->getTotalVat()->toEuros();
        $this->assertSame($expectedVat, $vatTotalNodes->item(0)->nodeValue);
    }

    public function testCalculatesGrandTotalAmount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $grandTotalNodes = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount');
        $this->assertCount(1, $grandTotalNodes);
        $expectedTotal = $invoice->getTotalIncludingVat()->toEuros();
        $this->assertSame($expectedTotal, $grandTotalNodes->item(0)->nodeValue);
    }

    // ========================================
    // VAT Breakdown Tests (2 tests)
    // ========================================

    public function testBreaksDownVatByRate(): void
    {
        $invoice = $this->createTestInvoiceWithMultipleVatRates();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Should have 2 ApplicableTradeTax sections (20% and 10%)
        $vatBreakdownNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax');
        $this->assertCount(2, $vatBreakdownNodes);
    }

    public function testVatBreakdownContainsRateAndBasisAmount(): void
    {
        $invoice = $this->createTestInvoice();
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // VAT at 20% (in header settlement, not line items)
        $rateNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent');
        $this->assertCount(1, $rateNodes);
        $this->assertSame('20.00', $rateNodes->item(0)->nodeValue);

        // Basis amount
        $basisNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:BasisAmount');
        $this->assertCount(1, $basisNodes);
        $this->assertSame('1000.00', $basisNodes->item(0)->nodeValue);

        // Calculated VAT amount
        $calculatedVatNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CalculatedAmount');
        $this->assertCount(1, $calculatedVatNodes);
        $this->assertSame('200.00', $calculatedVatNodes->item(0)->nodeValue);
    }

    // ========================================
    // Credit Note Tests (1 test)
    // ========================================

    public function testMapsCreditNoteWithOriginalInvoiceReference(): void
    {
        $originalInvoice = $this->createTestInvoice();
        $originalInvoice->setNumber('FA-2025-0010');

        $creditNote = $this->createTestCreditNote();
        $creditNote->setCreditedInvoice($originalInvoice);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($creditNote, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $refInvoiceNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID');
        $this->assertCount(1, $refInvoiceNodes);
        $this->assertSame('FA-2025-0010', $refInvoiceNodes->item(0)->nodeValue);
    }

    // ========================================
    // Profile Tests (3 tests)
    // ========================================

    public function testGetProfileReturnsBasic(): void
    {
        $this->assertSame(FacturXProfile::BASIC, $this->builder->getProfile());
    }

    public function testSupportsReturnsTrueForBasicProfile(): void
    {
        $this->assertTrue($this->builder->supports(FacturXProfile::BASIC));
    }

    public function testSupportsReturnsFalseForOtherProfiles(): void
    {
        $this->assertFalse($this->builder->supports(FacturXProfile::MINIMUM));
        $this->assertFalse($this->builder->supports(FacturXProfile::BASIC_WL));
        $this->assertFalse($this->builder->supports(FacturXProfile::EN16931));
        $this->assertFalse($this->builder->supports(FacturXProfile::EXTENDED));
    }

    // ========================================
    // Dynamic Enum Tests (3 tests)
    // ========================================

    public function testMapsLineQuantityUnitFromEnum(): void
    {
        $invoice = $this->createTestInvoice();
        // Change quantity unit to DAYS
        $lines = $invoice->getLines();
        $line = $lines[0];
        $line->setQuantityUnit(QuantityUnitCode::DAY);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $qtyNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode');
        $this->assertCount(1, $qtyNodes);
        $this->assertSame('DAY', $qtyNodes->item(0)->nodeValue);
    }

    public function testMapsLineTaxCategoryCodeFromEnum(): void
    {
        $invoice = $this->createTestInvoice();
        // Change tax category to Zero rate
        $lines = $invoice->getLines();
        $line = $lines[0];
        $line->setTaxCategoryCode(TaxCategoryCode::ZERO_RATE);
        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Check line-level category code
        $lineVatNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode');
        $this->assertCount(1, $lineVatNodes);
        $this->assertSame('Z', $lineVatNodes->item(0)->nodeValue);

        // Check header-level category code in VAT breakdown
        $headerVatNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode');
        $this->assertCount(1, $headerVatNodes);
        $this->assertSame('Z', $headerVatNodes->item(0)->nodeValue);
    }

    public function testMapsMultipleTaxCategoriesInBreakdown(): void
    {
        $invoice = $this->createTestInvoice();
        // First line is standard rate (S)
        $lines = $invoice->getLines();
        $line1 = $lines[0];
        $line1->setTaxCategoryCode(TaxCategoryCode::STANDARD);

        // Add second line with exempt tax
        $line2 = new InvoiceLine(
            description: 'Service exonéré',
            quantity: 5,
            unitPrice: Money::fromEuros('200.00'),
            vatRate: 0.0,
        );
        $line2->setTaxCategoryCode(TaxCategoryCode::EXEMPT);
        $invoice->addLine($line2);

        $companyData = $this->createTestCompanyData();

        $xml = $this->builder->build($invoice, $companyData);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Should have 2 VAT breakdown sections (S and E)
        $headerVatNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode');
        $this->assertCount(2, $headerVatNodes);
        $this->assertNotFalse($headerVatNodes);

        $categories = [];
        /** @var \DOMNode $node */
        foreach ($headerVatNodes as $node) {
            $categories[] = $node->nodeValue;
        }
        sort($categories);
        $this->assertSame(['E', 'S'], $categories);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Safely query XPath and assert result is not false.
     *
     * @return \DOMNodeList<\DOMNode>
     */
    private function safeQueryXPath(\DOMXPath $xpath, string $query): \DOMNodeList
    {
        $result = $xpath->query($query);
        $this->assertNotFalse($result, "XPath query failed: {$query}");

        return $result;
    }

    /**
     * Safely get node value from DOMNodeList.
     */
    private function safeGetNodeValue(\DOMNodeList $nodes, int $index = 0): string
    {
        $node = $nodes->item($index);
        $this->assertNotNull($node, "Node at index {$index} is null");
        $this->assertNotNull($node->nodeValue, "Node value at index {$index} is null");

        return $node->nodeValue;
    }

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

    private function createTestInvoiceWithGlobalDiscount(): Invoice
    {
        $invoice = $this->createTestInvoice();
        $invoice->setGlobalDiscountAmount(Money::fromEuros('100.00'));

        return $invoice;
    }

    private function createTestInvoiceWithMultipleVatRates(): Invoice
    {
        $invoice = $this->createTestInvoice();

        // Line with 10% VAT
        $line2 = new InvoiceLine(
            description: 'Produit réduit',
            quantity: 2,
            unitPrice: Money::fromEuros('50.00'),
            vatRate: 10.0,
        );

        $invoice->addLine($line2);

        return $invoice;
    }

    private function createTestCreditNote(): Invoice
    {
        return $this->createTestInvoice(InvoiceType::CREDIT_NOTE);
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
