<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Exception\FacturXBuilderNotFoundException;

/**
 * Factory for creating Factur-X XML builders based on profile.
 *
 * Selects the appropriate builder implementation for the requested Factur-X profile.
 */
final class FacturXXmlBuilderFactory
{
    /**
     * @var array<string, FacturXXmlBuilderInterface>
     */
    private array $buildersByProfile = [];

    /**
     * @param iterable<FacturXXmlBuilderInterface> $builders
     */
    public function __construct(iterable $builders = [])
    {
        foreach ($builders as $builder) {
            $profileValue = $builder->getProfile()->value;
            $this->buildersByProfile[$profileValue] = $builder;
        }
    }

    /**
     * Get a builder for the specified Factur-X profile.
     *
     * @throws FacturXBuilderNotFoundException if no builder supports the profile
     */
    public function getBuilder(FacturXProfile $profile): FacturXXmlBuilderInterface
    {
        $profileValue = $profile->value;

        if (!isset($this->buildersByProfile[$profileValue])) {
            throw new FacturXBuilderNotFoundException($profile);
        }

        return $this->buildersByProfile[$profileValue];
    }

    /**
     * Check if a builder exists for the specified profile.
     */
    public function hasBuilder(FacturXProfile $profile): bool
    {
        return isset($this->buildersByProfile[$profile->value]);
    }

    /**
     * Get all supported profiles.
     *
     * @return array<FacturXProfile>
     */
    public function getSupportedProfiles(): array
    {
        return array_map(
            fn (string $value) => FacturXProfile::from($value),
            array_keys($this->buildersByProfile),
        );
    }

    /**
     * Register a builder.
     */
    public function registerBuilder(FacturXXmlBuilderInterface $builder): void
    {
        $this->buildersByProfile[$builder->getProfile()->value] = $builder;
    }
}
