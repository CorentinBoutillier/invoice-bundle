<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

/**
 * Builds Factur-X XML from Invoice entity.
 *
 * **Profile support:**
 * - BASIC: ✅ Fully implemented (recommended)
 * - MINIMUM, BASIC_WL, EN16931, EXTENDED: ⚠️ Accepted but generates BASIC XML
 *
 * To extend support for other profiles, this builder would need to generate
 * profile-specific XML structures based on the configured profile.
 */
interface FacturXXmlBuilderInterface
{
    /**
     * Build Factur-X XML (UN/CEFACT CII format) from Invoice entity.
     *
     * Generates machine-readable XML according to EN 16931 standard
     * with BASIC profile (essential invoice data).
     *
     * @param Invoice $invoice Invoice entity with finalized data
     * @param CompanyData $companyData Company information from provider
     *
     * @return string XML content as string (UTF-8 encoded)
     */
    public function build(Invoice $invoice, CompanyData $companyData): string;
}
