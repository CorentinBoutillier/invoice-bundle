<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * UN/CEFACT payment means codes (UNTDID 4461).
 *
 * @see https://unece.org/fileadmin/DAM/trade/untdid/d16b/tred/tred4461.htm
 */
enum PaymentMeansCode: string
{
    case CASH = '10';                    // Cash
    case CHECK = '20';                   // Check
    case CREDIT_TRANSFER = '30';         // Credit transfer (general)
    case BANK_ACCOUNT = '42';            // Payment to bank account
    case CREDIT_CARD = '48';             // Bank card
    case DIRECT_DEBIT = '49';            // Direct debit
    case SEPA_CREDIT_TRANSFER = '58';    // SEPA Credit Transfer
    case SEPA_DIRECT_DEBIT = '59';       // SEPA Direct Debit

    /**
     * Get human-readable label for display.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => 'Especes',
            self::CHECK => 'Cheque',
            self::CREDIT_TRANSFER => 'Virement',
            self::BANK_ACCOUNT => 'Compte bancaire',
            self::CREDIT_CARD => 'Carte bancaire',
            self::DIRECT_DEBIT => 'Prelevement',
            self::SEPA_CREDIT_TRANSFER => 'Virement SEPA',
            self::SEPA_DIRECT_DEBIT => 'Prelevement SEPA',
        };
    }

    /**
     * Convert from existing PaymentMethod enum.
     */
    public static function fromPaymentMethod(PaymentMethod $method): self
    {
        return match ($method) {
            PaymentMethod::CASH => self::CASH,
            PaymentMethod::CHECK => self::CHECK,
            PaymentMethod::BANK_TRANSFER => self::SEPA_CREDIT_TRANSFER,
            PaymentMethod::CREDIT_CARD => self::CREDIT_CARD,
            PaymentMethod::DIRECT_DEBIT => self::SEPA_DIRECT_DEBIT,
            PaymentMethod::OTHER => self::CREDIT_TRANSFER,
        };
    }
}
