<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * UN/CEFACT tax category codes (UNTDID 5305).
 *
 * Used in Factur-X for VAT treatment classification.
 *
 * @see https://unece.org/fileadmin/DAM/trade/untdid/d16b/tred/tred5305.htm
 */
enum TaxCategoryCode: string
{
    case STANDARD = 'S';         // Standard rate (20%, 10%, 5.5%, 2.1%)
    case ZERO_RATE = 'Z';        // Zero-rated (0%)
    case EXEMPT = 'E';           // VAT exempt
    case REVERSE_CHARGE = 'AE';  // Autoliquidation (reverse charge)
    case INTRA_EU = 'K';         // Intra-Community exempt
    case EXPORT = 'G';           // Export exempt
    case NOT_SUBJECT = 'O';      // Not subject to VAT (e.g., DOM-TOM)

    /**
     * Get human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'TVA taux normal',
            self::ZERO_RATE => 'TVA taux zéro',
            self::EXEMPT => 'Exonéré de TVA',
            self::REVERSE_CHARGE => 'Autoliquidation',
            self::INTRA_EU => 'Livraison intracommunautaire',
            self::EXPORT => 'Exportation',
            self::NOT_SUBJECT => 'Non soumis à TVA',
        };
    }

    /**
     * Alias for label() for compatibility.
     */
    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Check if this category requires VAT rate to be zero.
     */
    public function requiresZeroRate(): bool
    {
        return \in_array($this, [
            self::ZERO_RATE,
            self::EXEMPT,
            self::REVERSE_CHARGE,
            self::INTRA_EU,
            self::EXPORT,
            self::NOT_SUBJECT,
        ], true);
    }

    /**
     * Get required exemption reason codes for Factur-X.
     *
     * @return string|null Exemption reason code if applicable
     */
    public function getExemptionReasonCode(): ?string
    {
        return match ($this) {
            self::REVERSE_CHARGE => 'VATEX-EU-AE',
            self::INTRA_EU => 'VATEX-EU-IC',
            self::EXPORT => 'VATEX-EU-G',
            default => null,
        };
    }
}
