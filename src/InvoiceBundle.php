<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle;

use CorentinBoutillier\InvoiceBundle\DependencyInjection\InvoiceBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
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

    public function getContainerExtension(): ExtensionInterface
    {
        if (null === $this->extension || false === $this->extension) {
            $this->extension = new InvoiceBundleExtension();
        }

        return $this->extension;
    }
}
