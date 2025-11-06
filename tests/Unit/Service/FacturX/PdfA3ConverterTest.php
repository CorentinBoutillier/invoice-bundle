<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\Service\FacturX\PdfA3Converter;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\PdfA3ConverterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PdfA3Converter service.
 *
 * Validates PDF/A-3 conversion with embedded Factur-X XML using atgp/factur-x library.
 *
 * Test categories:
 * - Basic embedding functionality (3 tests)
 * - XML extraction and verification (2 tests)
 * - Profile metadata (2 tests)
 * - Error handling (3 tests)
 */
final class PdfA3ConverterTest extends TestCase
{
    private PdfA3ConverterInterface $converter;

    protected function setUp(): void
    {
        $this->converter = new PdfA3Converter();
    }

    /**
     * Test 1: Basic embedding - Returns valid PDF content.
     */
    public function testEmbedXmlReturnsNonEmptyString(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test 2: Result starts with PDF header.
     */
    public function testResultStartsWithPdfHeader(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        $this->assertStringStartsWith('%PDF-', $result);
    }

    /**
     * Test 3: Result is different from input (XML embedded).
     */
    public function testResultIsDifferentFromInputPdf(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        $this->assertNotSame($pdfContent, $result);
    }

    /**
     * Test 4: Embedded XML can be extracted.
     */
    public function testEmbeddedXmlCanBeExtracted(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        // Use atgp/factur-x Reader to extract XML
        $reader = new \Atgp\FacturX\Reader();
        $extractedXml = $reader->extractXML($result, false); // validateXsd = false for speed

        $this->assertNotNull($extractedXml, 'XML should be embedded in PDF');
        $this->assertStringContainsString('CrossIndustryInvoice', $extractedXml);
    }

    /**
     * Test 5: Extracted XML matches original.
     */
    public function testExtractedXmlMatchesOriginal(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        $reader = new \Atgp\FacturX\Reader();
        $extractedXml = $reader->extractXML($result, false);

        // Normalize whitespace for comparison
        $normalizedOriginal = preg_replace('/\s+/', ' ', trim($xmlContent));
        $normalizedExtracted = preg_replace('/\s+/', ' ', trim($extractedXml));

        $this->assertSame($normalizedOriginal, $normalizedExtracted);
    }

    /**
     * Test 6: PDF contains XMP metadata with Factur-X profile.
     */
    public function testPdfContainsXmpMetadata(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'BASIC');

        // Check for XMP metadata markers
        $this->assertStringContainsString('<?xpacket', $result);
        $this->assertStringContainsString('factur-x', strtolower($result));
    }

    /**
     * Test 7: Profile parameter is used in metadata.
     */
    public function testProfileParameterIsUsedInMetadata(): void
    {
        $pdfContent = $this->createSimplePdf();
        $xmlContent = $this->createValidXml();

        $result = $this->converter->embedXml($pdfContent, $xmlContent, 'EN16931');

        // Should contain EN16931 profile reference (formatted as 'EN 16931' with space in XMP metadata)
        $this->assertStringContainsString('EN 16931', $result);
    }

    /**
     * Test 8: Throws exception for empty PDF content.
     */
    public function testThrowsExceptionForEmptyPdfContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF content cannot be empty');

        $this->converter->embedXml('', $this->createValidXml(), 'BASIC');
    }

    /**
     * Test 9: Throws exception for empty XML content.
     */
    public function testThrowsExceptionForEmptyXmlContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('XML content cannot be empty');

        $this->converter->embedXml($this->createSimplePdf(), '', 'BASIC');
    }

    /**
     * Test 10: Throws exception for invalid profile.
     */
    public function testThrowsExceptionForInvalidProfile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Factur-X profile');

        $this->converter->embedXml($this->createSimplePdf(), $this->createValidXml(), 'INVALID');
    }

    /**
     * Helper: Create simple valid PDF using FPDF.
     */
    private function createSimplePdf(): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Test Invoice');

        return $pdf->Output('S');
    }

    /**
     * Helper: Create valid Factur-X XML.
     */
    private function createValidXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
    <rsm:ExchangedDocumentContext>
        <ram:GuidelineSpecifiedDocumentContextParameter>
            <ram:ID>urn:factur-x.eu:1p0:basic</ram:ID>
        </ram:GuidelineSpecifiedDocumentContextParameter>
    </rsm:ExchangedDocumentContext>
    <rsm:ExchangedDocument>
        <ram:ID>TEST-2025-0001</ram:ID>
        <ram:TypeCode>380</ram:TypeCode>
        <ram:IssueDateTime>
            <udt:DateTimeString format="102">20250106</udt:DateTimeString>
        </ram:IssueDateTime>
    </rsm:ExchangedDocument>
</rsm:CrossIndustryInvoice>
XML;
    }
}
