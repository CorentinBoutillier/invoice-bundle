<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

use CorentinBoutillier\InvoiceBundle\Enum\PaymentMeansCode;

/**
 * Payment means information for Factur-X BG-16 group.
 *
 * Contains payment instructions including bank details and references.
 */
readonly class PaymentMeansData
{
    public function __construct(
        public PaymentMeansCode $typeCode,             // BT-81: Payment means type code (required)
        public ?string $iban = null,                   // BT-84: IBAN
        public ?string $bic = null,                    // BT-86: BIC
        public ?string $bankName = null,               // BT-85: Payment account name
        public ?string $remittanceInformation = null,  // BT-83: Remittance information
        public ?string $paymentId = null,              // BT-84: Payment ID/mandate reference
    ) {
    }

    /**
     * Create from CompanyData for seller payment info.
     */
    public static function fromCompanyData(
        CompanyData $company,
        PaymentMeansCode $typeCode = PaymentMeansCode::SEPA_CREDIT_TRANSFER,
    ): self {
        return new self(
            typeCode: $typeCode,
            iban: $company->iban,
            bic: $company->bic,
            bankName: $company->bankName,
        );
    }
}
