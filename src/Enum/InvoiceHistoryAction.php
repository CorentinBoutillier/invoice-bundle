<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Enum;

enum InvoiceHistoryAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case FINALIZED = 'finalized';
    case SENT = 'sent';
    case PAYMENT_RECORDED = 'payment_recorded';
    case STATUS_CHANGED = 'status_changed';
    case CANCELLED = 'cancelled';
    case PDF_GENERATED = 'pdf_generated';
    case PDF_DOWNLOADED = 'pdf_downloaded';
}
