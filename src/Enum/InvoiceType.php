<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

enum InvoiceType: string
{
    case INVOICE = 'invoice';
    case CREDIT_NOTE = 'credit_note';
}
