<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use Atgp\FacturX\Utils\ProfileHandler;
use Atgp\FacturX\Writer;

/**
 * Converts standard PDF to PDF/A-3 with embedded Factur-X XML using atgp/factur-x library.
 *
 * Implementation wraps the atgp/factur-x library to:
 * - Embed XML file in PDF as attachment (factur-x.xml)
 * - Convert PDF to PDF/A-3 compliance (ISO 19005-3)
 * - Add XMP metadata with Factur-X profile conformance level
 */
final class PdfA3Converter implements PdfA3ConverterInterface
{
    /**
     * Valid Factur-X profiles according to specification 1.07.3.
     */
    private const VALID_PROFILES = ['MINIMUM', 'BASIC', 'BASIC_WL', 'EN16931', 'EXTENDED'];

    /**
     * Map bundle profile names (uppercase) to atgp/factur-x library constants (lowercase).
     */
    private const PROFILE_MAP = [
        'MINIMUM' => ProfileHandler::PROFILE_FACTURX_MINIMUM,
        'BASIC' => ProfileHandler::PROFILE_FACTURX_BASIC,
        'BASIC_WL' => ProfileHandler::PROFILE_FACTURX_BASICWL,
        'EN16931' => ProfileHandler::PROFILE_FACTURX_EN16931,
        'EXTENDED' => ProfileHandler::PROFILE_FACTURX_EXTENDED,
    ];

    public function embedXml(string $pdfContent, string $xmlContent, string $profile): string
    {
        $this->validateInputs($pdfContent, $xmlContent, $profile);

        try {
            // Create Writer instance
            $writer = new Writer();

            // Generate PDF/A-3 with embedded XML (no XSD validation - already done by XmlBuilder)
            $pdfA3Content = $writer->generate(
                $pdfContent,
                $xmlContent,
                self::PROFILE_MAP[$profile],
                false, // validateXSD = false (XML already validated)
                [], // no additional attachments
                false, // addLogo = false (no branding)
            );

            if (empty($pdfA3Content)) {
                throw new \RuntimeException('Failed to generate PDF/A-3 with embedded XML');
            }

            return $pdfA3Content;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                \sprintf('PDF/A-3 conversion failed: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Validate inputs before processing.
     *
     * @throws \InvalidArgumentException
     */
    private function validateInputs(string $pdfContent, string $xmlContent, string $profile): void
    {
        if (empty($pdfContent)) {
            throw new \InvalidArgumentException('PDF content cannot be empty');
        }

        if (empty($xmlContent)) {
            throw new \InvalidArgumentException('XML content cannot be empty');
        }

        if (!\in_array($profile, self::VALID_PROFILES, true)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Invalid Factur-X profile "%s". Valid profiles: %s',
                    $profile,
                    implode(', ', self::VALID_PROFILES),
                ),
            );
        }
    }
}
