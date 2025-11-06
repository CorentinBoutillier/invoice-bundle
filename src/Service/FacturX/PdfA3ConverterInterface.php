<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

/**
 * Converts standard PDF to PDF/A-3 with embedded Factur-X XML.
 *
 * PDF/A-3 is ISO 19005-3 standard for long-term archival with embedded files.
 * Factur-X requires XML embedded in PDF/A-3 with XMP metadata.
 */
interface PdfA3ConverterInterface
{
    /**
     * Embed Factur-X XML into PDF and convert to PDF/A-3.
     *
     * @param string $pdfContent Standard PDF binary content (from DomPDF)
     * @param string $xmlContent Factur-X XML string (UN/CEFACT CII format)
     * @param string $profile Factur-X profile (MINIMUM|BASIC|BASIC_WL|EN16931|EXTENDED)
     *
     * @return string PDF/A-3 binary with embedded XML and XMP metadata
     *
     * @throws \InvalidArgumentException If PDF/XML is empty or profile is invalid
     * @throws \RuntimeException If PDF/A-3 conversion fails
     */
    public function embedXml(string $pdfContent, string $xmlContent, string $profile): string;
}
