<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;

/**
 * Builds Factur-X XML from Invoice entity.
 *
 * Implementations generate profile-specific XML according to UN/CEFACT CII format.
 */
interface FacturXXmlBuilderInterface
{
    /**
     * Build Factur-X XML (UN/CEFACT CII format) from Invoice entity.
     *
     * @param Invoice     $invoice     Invoice entity with finalized data
     * @param CompanyData $companyData Company information from provider
     *
     * @return string XML content as string (UTF-8 encoded)
     */
    public function build(Invoice $invoice, CompanyData $companyData): string;

    /**
     * Get the Factur-X profile this builder generates.
     */
    public function getProfile(): FacturXProfile;

    /**
     * Check if this builder supports the given profile.
     */
    public function supports(FacturXProfile $profile): bool;
}
