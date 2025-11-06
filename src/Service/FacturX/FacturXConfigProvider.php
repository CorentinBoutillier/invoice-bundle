<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

/**
 * Default implementation of Factur-X configuration provider.
 *
 * Reads configuration from bundle parameters set by DependencyInjection/InvoiceBundleExtension.
 */
final class FacturXConfigProvider implements FacturXConfigProviderInterface
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $profile,
        private readonly string $xmlFilename,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function getXmlFilename(): string
    {
        return $this->xmlFilename;
    }
}
