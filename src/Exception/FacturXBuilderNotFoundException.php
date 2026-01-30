<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Exception;

use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;

/**
 * Exception thrown when no Factur-X builder is found for the requested profile.
 */
final class FacturXBuilderNotFoundException extends \RuntimeException
{
    public function __construct(
        private readonly FacturXProfile $profile,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = \sprintf(
            'No Factur-X XML builder found for profile "%s"',
            $this->profile->value,
        );

        parent::__construct($message, $code, $previous);
    }

    public function getProfile(): FacturXProfile
    {
        return $this->profile;
    }
}
