<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

enum InvoiceHistoryAction: string
{
    case CREATED = 'created';
    case FINALIZED = 'finalized';
    case SENT = 'sent';
    case PAID = 'paid';
    case PAYMENT_RECEIVED = 'payment_received';
    case CANCELLED = 'cancelled';
    case STATUS_CHANGED = 'status_changed';
    case EDITED = 'edited';
}
