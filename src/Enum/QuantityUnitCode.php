<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * UN/ECE Recommendation 20 unit codes.
 *
 * Common units for invoice line quantities.
 *
 * @see https://unece.org/trade/uncefact/cl-recommendations
 */
enum QuantityUnitCode: string
{
    // Time units
    case HOUR = 'HUR';        // Hours (default for services)
    case DAY = 'DAY';         // Days
    case MONTH = 'MON';       // Months
    case YEAR = 'ANN';        // Years

    // Quantity units
    case UNIT = 'C62';        // Unit/piece
    case PIECE = 'H87';       // Piece
    case SET = 'SET';         // Set

    // Weight units
    case KILOGRAM = 'KGM';    // Kilogram
    case GRAM = 'GRM';        // Gram

    // Volume units
    case LITER = 'LTR';       // Liter
    case MILLILITER = 'MLT';  // Milliliter
    case CUBIC_METER = 'MTQ'; // Cubic meter

    // Length units
    case METER = 'MTR';       // Meter
    case CENTIMETER = 'CMT';  // Centimeter
    case MILLIMETER = 'MMT';  // Millimeter
    case KILOMETER = 'KMT';   // Kilometer

    // Area units
    case SQUARE_METER = 'MTK'; // Square meter

    /**
     * Get human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::HOUR => 'Heure',
            self::DAY => 'Jour',
            self::MONTH => 'Mois',
            self::YEAR => 'Année',
            self::UNIT => 'Unité',
            self::PIECE => 'Pièce',
            self::SET => 'Lot',
            self::KILOGRAM => 'Kilogramme',
            self::GRAM => 'Gramme',
            self::LITER => 'Litre',
            self::MILLILITER => 'Millilitre',
            self::CUBIC_METER => 'Mètre cube',
            self::METER => 'Mètre',
            self::CENTIMETER => 'Centimètre',
            self::MILLIMETER => 'Millimètre',
            self::KILOMETER => 'Kilomètre',
            self::SQUARE_METER => 'Mètre carré',
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
     * Get short symbol for display.
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::HOUR => 'h',
            self::DAY => 'j',
            self::MONTH => 'mois',
            self::YEAR => 'an',
            self::UNIT, self::PIECE => 'u',
            self::SET => 'lot',
            self::KILOGRAM => 'kg',
            self::GRAM => 'g',
            self::LITER => 'L',
            self::MILLILITER => 'mL',
            self::CUBIC_METER => 'm3',
            self::METER => 'm',
            self::CENTIMETER => 'cm',
            self::MILLIMETER => 'mm',
            self::KILOMETER => 'km',
            self::SQUARE_METER => 'm2',
        };
    }
}
