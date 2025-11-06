<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

/**
 * Provides Factur-X configuration settings.
 *
 * Encapsulates bundle configuration for Factur-X feature:
 * - Enable/disable Factur-X generation (opt-out for 2026 B2G compliance)
 * - Profile selection (MINIMUM, BASIC, BASIC_WL, EN16931, EXTENDED)
 * - XML filename embedded in PDF/A-3
 */
interface FacturXConfigProviderInterface
{
    /**
     * Check if Factur-X generation is enabled.
     *
     * @return bool True to generate PDF/A-3 with embedded XML, false for standard PDF
     */
    public function isEnabled(): bool;

    /**
     * Get Factur-X profile.
     *
     * @return string Profile name (MINIMUM|BASIC|BASIC_WL|EN16931|EXTENDED)
     */
    public function getProfile(): string;

    /**
     * Get XML filename to embed in PDF/A-3.
     *
     * @return string Filename (default: 'factur-x.xml')
     */
    public function getXmlFilename(): string;
}
