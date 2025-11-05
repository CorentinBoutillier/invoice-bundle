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
            siret: isset($this->config['siret']) && \is_string($this->config['siret']) ? $this->config['siret'] : null,
            vatNumber: isset($this->config['vatNumber']) && \is_string($this->config['vatNumber']) ? $this->config['vatNumber'] : null,
            email: isset($this->config['email']) && \is_string($this->config['email']) ? $this->config['email'] : null,
            phone: isset($this->config['phone']) && \is_string($this->config['phone']) ? $this->config['phone'] : null,
            logo: isset($this->config['logo']) && \is_string($this->config['logo']) ? $this->config['logo'] : null,
            legalForm: isset($this->config['legalForm']) && \is_string($this->config['legalForm']) ? $this->config['legalForm'] : null,
            capital: isset($this->config['capital']) && \is_string($this->config['capital']) ? $this->config['capital'] : null,
            rcs: isset($this->config['rcs']) && \is_string($this->config['rcs']) ? $this->config['rcs'] : null,
            fiscalYearStartMonth: isset($this->config['fiscalYearStartMonth']) && \is_int($this->config['fiscalYearStartMonth']) ? $this->config['fiscalYearStartMonth'] : 1,
            fiscalYearStartDay: isset($this->config['fiscalYearStartDay']) && \is_int($this->config['fiscalYearStartDay']) ? $this->config['fiscalYearStartDay'] : 1,
            fiscalYearStartYear: isset($this->config['fiscalYearStartYear']) && \is_int($this->config['fiscalYearStartYear']) ? $this->config['fiscalYearStartYear'] : 0,
            bankName: isset($this->config['bankName']) && \is_string($this->config['bankName']) ? $this->config['bankName'] : null,
            iban: isset($this->config['iban']) && \is_string($this->config['iban']) ? $this->config['iban'] : null,
            bic: isset($this->config['bic']) && \is_string($this->config['bic']) ? $this->config['bic'] : null,
        );
    }
}
