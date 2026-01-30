<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * Factur-X profile levels conforming to EN 16931.
 *
 * @see https://fnfe-mpe.org/factur-x/
 */
enum FacturXProfile: string
{
    case MINIMUM = 'MINIMUM';       // ~15 fields, minimal B2G
    case BASIC_WL = 'BASIC_WL';     // BASIC without lines (header only)
    case BASIC = 'BASIC';           // ~60 fields, current implementation
    case EN16931 = 'EN16931';       // 165 fields, full EU compliance
    case EXTENDED = 'EXTENDED';     // EN16931 + French extensions

    /**
     * Get the URN identifier for Factur-X XML (conforming to XSD 1.07.3).
     */
    public function getUrn(): string
    {
        return match ($this) {
            self::MINIMUM => 'urn:factur-x.eu:1p0:minimum',
            self::BASIC_WL => 'urn:factur-x.eu:1p0:basicwl',
            self::BASIC => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
            self::EN16931 => 'urn:cen.eu:en16931:2017',
            self::EXTENDED => 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
        };
    }

    /**
     * Map to atgp/factur-x library profile constants.
     */
    public function getAtgpProfile(): string
    {
        return match ($this) {
            self::MINIMUM => 'minimum',
            self::BASIC_WL => 'basicwl',
            self::BASIC => 'basic',
            self::EN16931 => 'en16931',
            self::EXTENDED => 'extended',
        };
    }

    /**
     * Check if this profile includes line item details.
     */
    public function hasLineItems(): bool
    {
        return self::BASIC_WL !== $this;
    }
}
