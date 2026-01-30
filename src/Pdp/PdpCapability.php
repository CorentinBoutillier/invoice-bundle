<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Pdp;

/**
 * Capabilities that a PDP connector may support.
 *
 * Not all PDP platforms support all features. This enum allows
 * querying connector capabilities before attempting operations.
 */
enum PdpCapability: string
{
    /**
     * Can transmit invoices to recipients.
     */
    case TRANSMIT = 'transmit';

    /**
     * Can receive invoices from suppliers.
     */
    case RECEIVE = 'receive';

    /**
     * Can query transmission status.
     */
    case STATUS = 'status';

    /**
     * Can retrieve invoice lifecycle events.
     */
    case LIFECYCLE = 'lifecycle';

    /**
     * Supports health check / connectivity test.
     */
    case HEALTH_CHECK = 'health_check';

    /**
     * Supports batch transmission of multiple invoices.
     */
    case BATCH_TRANSMIT = 'batch_transmit';

    /**
     * Supports webhook notifications.
     */
    case WEBHOOKS = 'webhooks';

    /**
     * Supports e-reporting to tax authorities.
     */
    case E_REPORTING = 'e_reporting';
}
