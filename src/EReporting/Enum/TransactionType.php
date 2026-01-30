<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\EReporting\Enum;

/**
 * Type of transaction for e-reporting purposes.
 *
 * Based on the French e-invoicing reform requirements.
 * Determines whether a transaction requires e-reporting (B2C, B2B export)
 * or e-invoicing (B2B France, B2G).
 */
enum TransactionType: string
{
    /**
     * Business-to-Business transaction within France.
     * Handled via e-invoicing through PDP.
     */
    case B2B_FRANCE = 'b2b_france';

    /**
     * Business-to-Business transaction within EU.
     * May use e-invoicing or direct transmission.
     */
    case B2B_INTRA_EU = 'b2b_intra_eu';

    /**
     * Business-to-Business transaction outside EU.
     * Requires e-reporting.
     */
    case B2B_EXPORT = 'b2b_export';

    /**
     * Business-to-Consumer transaction within France.
     * Requires e-reporting.
     */
    case B2C_FRANCE = 'b2c_france';

    /**
     * Business-to-Consumer transaction within EU.
     * Requires e-reporting.
     */
    case B2C_INTRA_EU = 'b2c_intra_eu';

    /**
     * Business-to-Consumer transaction outside EU.
     * Requires e-reporting.
     */
    case B2C_EXPORT = 'b2c_export';

    /**
     * Business-to-Government transaction (public sector).
     * Uses Chorus Pro portal.
     */
    case B2G_FRANCE = 'b2g_france';

    public function isB2B(): bool
    {
        return \in_array($this, [
            self::B2B_FRANCE,
            self::B2B_INTRA_EU,
            self::B2B_EXPORT,
        ], true);
    }

    public function isB2C(): bool
    {
        return \in_array($this, [
            self::B2C_FRANCE,
            self::B2C_INTRA_EU,
            self::B2C_EXPORT,
        ], true);
    }

    public function isB2G(): bool
    {
        return self::B2G_FRANCE === $this;
    }

    public function isDomestic(): bool
    {
        return \in_array($this, [
            self::B2B_FRANCE,
            self::B2C_FRANCE,
            self::B2G_FRANCE,
        ], true);
    }

    public function isIntraEU(): bool
    {
        return \in_array($this, [
            self::B2B_INTRA_EU,
            self::B2C_INTRA_EU,
        ], true);
    }

    public function isExport(): bool
    {
        return \in_array($this, [
            self::B2B_EXPORT,
            self::B2C_EXPORT,
        ], true);
    }

    /**
     * Check if this transaction type requires e-reporting.
     *
     * E-reporting is required for:
     * - B2C transactions (all)
     * - B2B export transactions (outside EU)
     */
    public function requiresEReporting(): bool
    {
        return $this->isB2C() || self::B2B_EXPORT === $this;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::B2B_FRANCE => 'B2B France',
            self::B2B_INTRA_EU => 'B2B Intra-UE',
            self::B2B_EXPORT => 'B2B Export',
            self::B2C_FRANCE => 'B2C France',
            self::B2C_INTRA_EU => 'B2C Intra-UE',
            self::B2C_EXPORT => 'B2C Export',
            self::B2G_FRANCE => 'B2G France',
        };
    }
}
