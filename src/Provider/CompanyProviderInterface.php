<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;

/**
 * Provides company data for invoice generation.
 *
 * This interface allows flexible data sourcing:
 * - Configuration-based (YAML) for simple mono-company setups
 * - Database-based for multi-company applications
 * - API-based for external data sources
 */
interface CompanyProviderInterface
{
    /**
     * Returns company data for the specified company ID.
     *
     * @param int|null $companyId The company ID (null for mono-company mode)
     *
     * @return CompanyData The company data
     *
     * @throws \LogicException If the provider does not support the requested mode
     * @throws \InvalidArgumentException If required data is missing
     */
    public function getCompanyData(?int $companyId = null): CompanyData;
}
