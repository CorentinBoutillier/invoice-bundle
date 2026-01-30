<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

/**
 * Operation category for French VAT treatment.
 *
 * Determines whether VAT is collected on invoice (goods) or payment (services).
 */
enum OperationCategory: string
{
    case GOODS = 'goods';       // TVA sur debit (VAT on invoice)
    case SERVICES = 'services'; // TVA sur encaissements (VAT on payment)
    case MIXED = 'mixed';       // Both goods and services

    /**
     * Get human-readable label for the operation category.
     */
    public function label(): string
    {
        return match ($this) {
            self::GOODS => 'Livraison de biens',
            self::SERVICES => 'Prestation de services',
            self::MIXED => 'Mixte (biens et services)',
        };
    }

    /**
     * Check if VAT is on debits (invoice date) vs receipts (payment date).
     *
     * For goods: VAT is due when invoice is issued.
     * For services: VAT is due when payment is received (unless opted for VAT on debits).
     */
    public function isVatOnDebits(): bool
    {
        return self::GOODS === $this;
    }
}
