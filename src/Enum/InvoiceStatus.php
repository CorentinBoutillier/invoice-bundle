<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case FINALIZED = 'finalized';
    case SENT = 'sent';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
}
