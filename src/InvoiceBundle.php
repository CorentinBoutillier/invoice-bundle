<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle Symfony pour la gestion de factures et avoirs conformes à la réglementation française.
 */
class InvoiceBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
