<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Validation;

use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;

/**
 * Interface for Factur-X XML validation.
 */
interface XmlValidatorInterface
{
    /**
     * Validate XML content against Factur-X schema.
     *
     * @param string            $xmlContent The XML content to validate
     * @param FacturXProfile    $profile    The Factur-X profile to validate against
     */
    public function validate(string $xmlContent, FacturXProfile $profile = FacturXProfile::EN16931): ValidationResult;

    /**
     * Validate XML and throw exception if invalid.
     *
     * @throws \CorentinBoutillier\InvoiceBundle\Exception\FacturXValidationException
     */
    public function validateOrFail(string $xmlContent, FacturXProfile $profile = FacturXProfile::EN16931): void;

    /**
     * Check if validator supports the given profile.
     */
    public function supports(FacturXProfile $profile): bool;
}
