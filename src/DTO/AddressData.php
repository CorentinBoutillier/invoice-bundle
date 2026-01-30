<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

/**
 * Structured address data for Factur-X EN16931 compliance.
 *
 * Maps to BG-5 (Seller), BG-8 (Buyer), BG-15 (Delivery) address groups.
 */
readonly class AddressData
{
    public function __construct(
        public string $line1,                      // BT-35/BT-50/BT-75: Address line 1 (required)
        public string $city,                       // BT-37/BT-52/BT-77: City (required)
        public string $postalCode,                 // BT-38/BT-53/BT-78: Postal code (required)
        public string $countryCode = 'FR',         // BT-40/BT-55/BT-80: ISO 3166-1 alpha-2 (required)
        public ?string $line2 = null,              // BT-36/BT-51/BT-76: Address line 2
        public ?string $line3 = null,              // Address line 3 (extended)
        public ?string $countrySubdivision = null, // BT-39/BT-54/BT-79: Region/Province
    ) {
    }

    /**
     * Create from legacy single-line address string.
     *
     * Best-effort parsing for backward compatibility.
     */
    public static function fromLegacyString(string $address, string $countryCode = 'FR'): self
    {
        // Split by newlines and clean up
        $lines = array_filter(array_map('trim', explode("\n", $address)));

        if (0 === \count($lines)) {
            return new self(
                line1: $address,
                city: '',
                postalCode: '',
                countryCode: $countryCode,
            );
        }

        // Try to extract postal code and city from last line
        $lastLine = array_pop($lines);
        $postalCode = '';
        $city = $lastLine;

        // Match French postal code pattern (5 digits)
        if (preg_match('/^(\d{5})\s+(.+)$/', $lastLine, $matches)) {
            $postalCode = $matches[1];
            $city = $matches[2];
        }

        return new self(
            line1: $lines[0] ?? $address,
            city: $city,
            postalCode: $postalCode,
            countryCode: $countryCode,
            line2: $lines[1] ?? null,
            line3: $lines[2] ?? null,
        );
    }

    /**
     * Convert to single-line format for legacy compatibility.
     */
    public function toSingleLine(): string
    {
        $parts = array_filter([
            $this->line1,
            $this->line2,
            $this->line3,
            trim($this->postalCode.' '.$this->city),
            'FR' !== $this->countryCode ? $this->countryCode : null,
        ]);

        return implode(', ', $parts);
    }
}
