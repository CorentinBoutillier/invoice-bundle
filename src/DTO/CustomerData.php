<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DTO;

readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $address,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
    ) {
    }
}
