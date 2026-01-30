<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Validation;

use Atgp\FacturX\XsdValidator;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Exception\FacturXValidationException;

/**
 * Factur-X XML validator using atgp/factur-x library.
 */
class FacturXXmlValidator implements XmlValidatorInterface
{
    public function __construct(
        private readonly XsdValidator $xsdValidator = new XsdValidator(),
    ) {
    }

    public function validate(string $xmlContent, FacturXProfile $profile = FacturXProfile::EN16931): ValidationResult
    {
        if ('' === trim($xmlContent)) {
            return ValidationResult::invalid([
                new ValidationError(
                    message: 'XML content is empty',
                    code: 'EMPTY_XML',
                ),
            ]);
        }

        try {
            $atgpProfile = $profile->getAtgpProfile();
            $isValid = $this->xsdValidator->validate($xmlContent, $atgpProfile);

            if ($isValid) {
                return ValidationResult::valid();
            }

            $errors = $this->convertXmlErrors($this->xsdValidator->getXmlErrors());

            return ValidationResult::invalid($errors);
        } catch (\Exception $e) {
            return ValidationResult::invalid([
                new ValidationError(
                    message: $e->getMessage(),
                    code: 'VALIDATION_ERROR',
                ),
            ]);
        }
    }

    public function validateOrFail(string $xmlContent, FacturXProfile $profile = FacturXProfile::EN16931): void
    {
        $result = $this->validate($xmlContent, $profile);

        if (!$result->isValid) {
            throw new FacturXValidationException($result);
        }
    }

    public function supports(FacturXProfile $profile): bool
    {
        // All Factur-X profiles are supported via atgp/factur-x
        return true;
    }

    /**
     * Convert LibXMLError array to ValidationError array.
     *
     * @param array<\LibXMLError> $xmlErrors
     *
     * @return array<int, ValidationError>
     */
    private function convertXmlErrors(array $xmlErrors): array
    {
        $errors = [];

        foreach ($xmlErrors as $xmlError) {
            $severity = match ($xmlError->level) {
                \LIBXML_ERR_WARNING => ValidationError::SEVERITY_WARNING,
                default => ValidationError::SEVERITY_ERROR,
            };

            $errors[] = new ValidationError(
                message: trim($xmlError->message),
                severity: $severity,
                code: (string) $xmlError->code,
                line: $xmlError->line,
                path: $xmlError->file ?: null,
            );
        }

        return $errors;
    }
}
