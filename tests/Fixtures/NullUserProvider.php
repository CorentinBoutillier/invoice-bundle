<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Fixtures;

use CorentinBoutillier\InvoiceBundle\DTO\UserData;
use CorentinBoutillier\InvoiceBundle\Provider\UserProviderInterface;

/**
 * Null implementation of UserProviderInterface for tests.
 */
final class NullUserProvider implements UserProviderInterface
{
    public function getCurrentUser(): ?UserData
    {
        return null;
    }
}
