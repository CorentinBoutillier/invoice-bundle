<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

readonly class CompanyData
{
    public function __construct(
        public string $name,
        public string $address,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $logo = null,
        public ?string $legalForm = null,
        public ?string $capital = null,
        public ?string $rcs = null,
        public int $fiscalYearStartMonth = 1,
        public int $fiscalYearStartDay = 1,
        public int $fiscalYearStartYear = 0,
        public ?string $bankName = null,
        public ?string $iban = null,
        public ?string $bic = null,
        // EN16931 structured address fields
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $countryCode = null,
    ) {
    }
}
