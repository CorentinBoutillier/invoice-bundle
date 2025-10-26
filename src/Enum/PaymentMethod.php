<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case CHECK = 'check';
    case CASH = 'cash';
    case DIRECT_DEBIT = 'direct_debit';
    case OTHER = 'other';
}
