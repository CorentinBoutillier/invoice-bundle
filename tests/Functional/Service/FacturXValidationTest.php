<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Service;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizerInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\PdfStorageInterface;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;

/**
 * Tests de validation Factur-X et PDF/A-3.
 *
 * Ces tests vérifient que les PDFs générés avec Factur-X activé sont conformes aux standards :
 * - PDF/A-3 (ISO 19005-3) avec métadonnées XMP
 * - Factur-X BASIC profile avec XML EN 16931 embarqué
 * - XML extractible et valide
 */
final class FacturXValidationTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private InvoiceFinalizerInterface $invoiceFinalizer;
    /** @phpstan-ignore property.uninitialized */
    private PdfStorageInterface $pdfStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->kernel->getContainer();

        $invoiceFinalizer = $container->get(InvoiceFinalizerInterface::class);
        if (!$invoiceFinalizer instanceof InvoiceFinalizerInterface) {
            throw new \RuntimeException('InvoiceFinalizerInterface not found');
        }
        $this->invoiceFinalizer = $invoiceFinalizer;

        $pdfStorage = $container->get(PdfStorageInterface::class);
        if (!$pdfStorage instanceof PdfStorageInterface) {
            throw new \RuntimeException('PdfStorageInterface not found');
        }
        $this->pdfStorage = $pdfStorage;
    }

    // ========================================
    // Tests Factur-X XML Embedding (4 tests)
    // ========================================

    public function testFinalizedInvoiceContainsEmbeddedXml(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfPath = $invoice->getPdfPath();
        $this->assertNotNull($pdfPath);

        $pdfContent = $this->pdfStorage->retrieve($pdfPath);

        // Utiliser Atgp\FacturX\Reader pour extraire le XML
        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent, 'PDF should contain embedded Factur-X XML');
        $this->assertNotEmpty($xmlContent, 'Embedded XML should not be empty');
    }

    public function testExtractedXmlIsWellFormed(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        // Vérifier que le XML est bien formé
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xmlContent);
        $this->assertTrue($result, 'Extracted XML must be well-formed');
    }

    public function testExtractedXmlContainsInvoiceData(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Vérifier que le numéro de facture est dans le XML
        $invoiceNumber = $invoice->getNumber();
        $this->assertNotNull($invoiceNumber);

        $numberNodes = $this->safeQueryXPath($xpath, '//rsm:ExchangedDocument/ram:ID');
        $this->assertGreaterThan(0, $numberNodes->count());
        $this->assertSame($invoiceNumber, $numberNodes->item(0)?->nodeValue);
    }

    public function testExtractedXmlContainsRequiredFacturXElements(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Vérifier les sections obligatoires Factur-X BASIC
        $this->assertGreaterThan(0, $this->safeQueryXPath($xpath, '//rsm:ExchangedDocumentContext')->count(), 'Missing ExchangedDocumentContext');
        $this->assertGreaterThan(0, $this->safeQueryXPath($xpath, '//rsm:ExchangedDocument')->count(), 'Missing ExchangedDocument');
        $this->assertGreaterThan(0, $this->safeQueryXPath($xpath, '//rsm:SupplyChainTradeTransaction')->count(), 'Missing SupplyChainTradeTransaction');

        // Vérifier le profil BASIC
        $profileNodes = $this->safeQueryXPath($xpath, '//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        $this->assertGreaterThan(0, $profileNodes->count(), 'Missing Factur-X profile declaration');
        $this->assertSame('urn:factur-x.eu:1p0:basic', $profileNodes->item(0)?->nodeValue, 'Profile should be BASIC');
    }

    // ========================================
    // Tests PDF/A-3 Compliance (5 tests)
    // ========================================

    public function testPdfContainsPdfA3Metadata(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        // Vérifier les métadonnées XMP PDF/A-3
        $this->assertStringContainsString('pdfaid:part', $pdfContent, 'PDF should contain PDF/A part metadata');
        $this->assertStringContainsString('pdfaid:conformance', $pdfContent, 'PDF should contain PDF/A conformance metadata');
    }

    public function testPdfContainsColorProfileGtsPdfa1(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        // Vérifier le profil de couleur GTS_PDFA1 (obligatoire pour PDF/A)
        $this->assertStringContainsString('/GTS_PDFA1', $pdfContent, 'PDF should contain GTS_PDFA1 color profile');
    }

    public function testPdfContainsOutputIntent(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        // Vérifier l'OutputIntent (obligatoire pour PDF/A)
        $this->assertStringContainsString('/OutputIntent', $pdfContent, 'PDF should contain OutputIntent');
    }

    public function testPdfContainsFacturXFileAttachment(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        // Vérifier la référence au fichier XML embarqué
        $this->assertStringContainsString('factur-x.xml', $pdfContent, 'PDF should contain reference to factur-x.xml attachment');
    }

    public function testPdfContainsXmpMetadata(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        // Vérifier la présence de métadonnées XMP (RDF)
        $this->assertStringContainsString('<rdf:RDF', $pdfContent, 'PDF should contain XMP RDF metadata');
        $this->assertStringContainsString('http://www.aiim.org/pdfa', $pdfContent, 'PDF should reference PDF/A namespace');
    }

    // ========================================
    // Tests XML Schema Validation (2 tests)
    // ========================================

    public function testXmlElementsAreInCorrectOrder(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Vérifier l'ordre des éléments dans SupplyChainTradeTransaction
        // D'après EN 16931, IncludedSupplyChainTradeLineItem DOIT venir AVANT ApplicableHeaderTradeAgreement
        $transaction = $this->safeQueryXPath($xpath, '//rsm:SupplyChainTradeTransaction')->item(0);
        $this->assertNotNull($transaction);

        $children = [];
        foreach ($transaction->childNodes as $child) {
            if (\XML_ELEMENT_NODE === $child->nodeType) {
                $children[] = $child->localName;
            }
        }

        // Vérifier que IncludedSupplyChainTradeLineItem vient avant ApplicableHeaderTradeAgreement
        $lineItemIndex = array_search('IncludedSupplyChainTradeLineItem', $children, true);
        $agreementIndex = array_search('ApplicableHeaderTradeAgreement', $children, true);

        $this->assertNotFalse($lineItemIndex, 'IncludedSupplyChainTradeLineItem should be present');
        $this->assertNotFalse($agreementIndex, 'ApplicableHeaderTradeAgreement should be present');
        $this->assertLessThan($agreementIndex, $lineItemIndex, 'IncludedSupplyChainTradeLineItem must come before ApplicableHeaderTradeAgreement');
    }

    public function testXmlContainsMandatoryCountryCodeInAddress(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Vérifier que toutes les adresses PostalTradeAddress ont un CountryID (obligatoire)
        $addressNodes = $this->safeQueryXPath($xpath, '//ram:PostalTradeAddress');
        $this->assertGreaterThan(0, $addressNodes->count(), 'Should have at least one address');

        foreach ($addressNodes as $address) {
            $countryNodes = $this->safeQueryXPath($xpath, './/ram:CountryID', $address);
            $this->assertGreaterThan(0, $countryNodes->count(), 'PostalTradeAddress must contain CountryID');
        }
    }

    // ========================================
    // Tests Credit Note Factur-X (2 tests)
    // ========================================

    public function testCreditNoteContainsFacturXXml(): void
    {
        $creditNote = $this->createDraftCreditNoteWithLines();

        $this->invoiceFinalizer->finalize($creditNote);

        $pdfContent = $this->pdfStorage->retrieve($creditNote->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent, 'Credit note PDF should contain embedded Factur-X XML');
    }

    public function testCreditNoteXmlHasCorrectTypeCode(): void
    {
        $creditNote = $this->createDraftCreditNoteWithLines();

        $this->invoiceFinalizer->finalize($creditNote);

        $pdfContent = $this->pdfStorage->retrieve($creditNote->getPdfPath() ?? '');

        $reader = new \Atgp\FacturX\Reader();
        $xmlContent = $reader->extractXML($pdfContent);

        $this->assertNotNull($xmlContent);

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // TypeCode 381 = Credit Note selon UN/CEFACT
        $typeCodeNodes = $this->safeQueryXPath($xpath, '//rsm:ExchangedDocument/ram:TypeCode');
        $this->assertGreaterThan(0, $typeCodeNodes->count());
        $this->assertSame('381', $typeCodeNodes->item(0)?->nodeValue, 'Credit note should have TypeCode 381');
    }

    // ========================================
    // Tests Size & Performance (1 test)
    // ========================================

    public function testFacturXPdfSizeIsReasonable(): void
    {
        $invoice = $this->createDraftInvoiceWithLines();

        $this->invoiceFinalizer->finalize($invoice);

        $pdfContent = $this->pdfStorage->retrieve($invoice->getPdfPath() ?? '');

        $pdfSize = \strlen($pdfContent);

        // Un PDF Factur-X BASIC devrait faire moins de 500 Ko pour une facture simple
        $this->assertLessThan(500 * 1024, $pdfSize, 'Factur-X PDF should be under 500 KB for simple invoice');

        // Mais devrait être plus grand qu'un PDF vide (minimum 10 Ko)
        $this->assertGreaterThan(10 * 1024, $pdfSize, 'PDF should be at least 10 KB');
    }

    // ========================================
    // Helper methods
    // ========================================

    /**
     * Safely query XPath and assert result is not false.
     *
     * @return \DOMNodeList<\DOMNode>
     */
    private function safeQueryXPath(\DOMXPath $xpath, string $query, ?\DOMNode $contextNode = null): \DOMNodeList
    {
        $result = null !== $contextNode ? $xpath->query($query, $contextNode) : $xpath->query($query);
        $this->assertNotFalse($result, "XPath query failed: {$query}");

        return $result;
    }

    private function createDraftInvoiceWithLines(): Invoice
    {
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '456 Customer Street, 75002 Paris, France',
            email: 'customer@example.com',
            phone: '+33 1 98 76 54 32',
            siret: null,
            vatNumber: null,
        );

        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: new \DateTimeImmutable('2025-01-15'),
            dueDate: new \DateTimeImmutable('2025-02-14'),
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris, France',
        );

        $invoice->setCustomerEmail($customerData->email);
        $invoice->setCustomerPhone($customerData->phone);
        $invoice->setPaymentTerms('30 jours net');

        $line1 = new InvoiceLine(
            description: 'Service de développement',
            quantity: 10,
            unitPrice: Money::fromEuros('100.00'),
            vatRate: 20.0,
        );

        $line2 = new InvoiceLine(
            description: 'Service de conseil',
            quantity: 5,
            unitPrice: Money::fromEuros('150.00'),
            vatRate: 20.0,
        );

        $invoice->addLine($line1);
        $invoice->addLine($line2);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function createDraftCreditNoteWithLines(): Invoice
    {
        $customerData = new CustomerData(
            name: 'Test Customer',
            address: '456 Customer Street, 75002 Paris, France',
            email: 'customer@example.com',
            phone: '+33 1 98 76 54 32',
            siret: null,
            vatNumber: null,
        );

        $creditNote = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: new \DateTimeImmutable('2025-01-20'),
            dueDate: new \DateTimeImmutable('2025-02-19'),
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: 'Test Company SARL',
            companyAddress: '123 Test Street, 75001 Paris, France',
        );

        $creditNote->setCustomerEmail($customerData->email);
        $creditNote->setCustomerPhone($customerData->phone);
        $creditNote->setPaymentTerms('30 jours net');

        $line = new InvoiceLine(
            description: 'Remboursement service',
            quantity: 1,
            unitPrice: Money::fromEuros('500.00'),
            vatRate: 20.0,
        );

        $creditNote->addLine($line);

        $this->entityManager->persist($creditNote);
        $this->entityManager->flush();

        return $creditNote;
    }
}
