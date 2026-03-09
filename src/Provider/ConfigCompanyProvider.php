<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;

/**
 * Configuration-based company provider for mono-company setups.
 *
 * Reads company data from a configuration array (typically from YAML).
 * Does not support multi-company mode (throws exception if companyId provided).
 *
 * For multi-company applications, implement a custom provider that reads from database.
 */
final class ConfigCompanyProvider implements CompanyProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function getCompanyData(?int $companyId = null): CompanyData
    {
        if (null !== $companyId) {
            throw new \LogicException('ConfigCompanyProvider does not support multi-company mode');
        }

        // Validate required fields
        if (!isset($this->config['name']) || !\is_string($this->config['name'])) {
            throw new \InvalidArgumentException('Company name is required');
        }

        if (!isset($this->config['address']) || !\is_string($this->config['address'])) {
            throw new \InvalidArgumentException('Company address is required');
        }

        return new CompanyData(
            name: $this->config['name'],
            address: $this->config['address'],
            siret: $this->getStringConfig('siret'),
            vatNumber: $this->getStringConfig('vatNumber') ?? $this->getStringConfig('vat_number'),
            email: $this->getStringConfig('email'),
            phone: $this->getStringConfig('phone'),
            logo: $this->getStringConfig('logo'),
            legalForm: $this->getStringConfig('legalForm') ?? $this->getStringConfig('legal_form'),
            capital: $this->getStringConfig('capital'),
            rcs: $this->getStringConfig('rcs'),
            fiscalYearStartMonth: $this->getIntConfig('fiscalYearStartMonth') ?? $this->getIntConfig('fiscal_year_start_month') ?? 1,
            fiscalYearStartDay: $this->getIntConfig('fiscalYearStartDay') ?? $this->getIntConfig('fiscal_year_start_day') ?? 1,
            fiscalYearStartYear: $this->getIntConfig('fiscalYearStartYear') ?? $this->getIntConfig('fiscal_year_start_year') ?? 0,
            bankName: $this->getStringConfig('bankName') ?? $this->getStringConfig('bank_name'),
            iban: $this->getStringConfig('iban'),
            bic: $this->getStringConfig('bic'),
            city: $this->getStringConfig('city'),
            postalCode: $this->getStringConfig('postalCode') ?? $this->getStringConfig('postal_code'),
            countryCode: $this->getStringConfig('countryCode') ?? $this->getStringConfig('country_code') ?? 'FR',
        );
    }

    private function getStringConfig(string $key): ?string
    {
        return isset($this->config[$key]) && \is_string($this->config[$key]) ? $this->config[$key] : null;
    }

    private function getIntConfig(string $key): ?int
    {
        return isset($this->config[$key]) && \is_int($this->config[$key]) ? $this->config[$key] : null;
    }
}
