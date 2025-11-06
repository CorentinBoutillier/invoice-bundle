<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;

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
