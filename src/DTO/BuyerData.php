<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

/**
 * Extended buyer information for Factur-X EN16931 BG-7 group.
 *
 * Extends CustomerData with additional fields required for EN16931:
 * - Structured address (BG-8)
 * - Delivery address (BG-15)
 * - Buyer reference (BT-10)
 * - Accounting reference (BT-19)
 */
readonly class BuyerData
{
    public function __construct(
        public string $name,                           // BT-44: Buyer name (required)
        public AddressData $address,                   // BG-8: Postal address (required)
        public ?string $siret = null,                  // BT-47: Buyer legal registration ID
        public ?string $vatNumber = null,              // BT-48: Buyer VAT identifier
        public ?string $email = null,                  // BT-58: Buyer contact email
        public ?string $phone = null,                  // Buyer contact phone
        public ?string $buyerReference = null,         // BT-10: Buyer reference (for routing)
        public ?string $accountingReference = null,    // BT-19: Buyer accounting reference
        public ?string $legalForm = null,              // Legal form (SA, SAS, SARL...)
        public ?string $tradingName = null,            // BT-45: Trading name (if different from legal)
        public ?AddressData $deliveryAddress = null,   // BG-15: Delivery address (if different)
        public ?string $purchaseOrderReference = null, // BT-13: Purchase order reference
    ) {
    }

    /**
     * Create from legacy CustomerData for backward compatibility.
     */
    public static function fromCustomerData(CustomerData $customer): self
    {
        return new self(
            name: $customer->name,
            address: AddressData::fromLegacyString($customer->address),
            siret: $customer->siret,
            vatNumber: $customer->vatNumber,
            email: $customer->email,
            phone: $customer->phone,
        );
    }

    /**
     * Check if delivery address differs from billing address.
     */
    public function hasDeliveryAddress(): bool
    {
        return null !== $this->deliveryAddress;
    }
}
