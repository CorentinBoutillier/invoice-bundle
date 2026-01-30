<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\Validation;

use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationError;
use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function testValidFactoryMethod(): void
    {
        $result = ValidationResult::valid();

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testInvalidFactoryMethod(): void
    {
        $errors = [
            new ValidationError(message: 'Error 1'),
            new ValidationError(message: 'Error 2'),
        ];

        $result = ValidationResult::invalid($errors);

        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors);
    }

    public function testGetErrorsFiltersOutWarnings(): void
    {
        $errors = [
            new ValidationError(message: 'Error 1', severity: ValidationError::SEVERITY_ERROR),
            new ValidationError(message: 'Warning 1', severity: ValidationError::SEVERITY_WARNING),
            new ValidationError(message: 'Error 2', severity: ValidationError::SEVERITY_ERROR),
        ];

        $result = ValidationResult::invalid($errors);

        $filteredErrors = $result->getErrors();

        $this->assertCount(2, $filteredErrors);
        $this->assertSame('Error 1', $filteredErrors[0]->message);
        $this->assertSame('Error 2', $filteredErrors[1]->message);
    }

    public function testGetWarningsFiltersOutErrors(): void
    {
        $errors = [
            new ValidationError(message: 'Error 1', severity: ValidationError::SEVERITY_ERROR),
            new ValidationError(message: 'Warning 1', severity: ValidationError::SEVERITY_WARNING),
            new ValidationError(message: 'Warning 2', severity: ValidationError::SEVERITY_WARNING),
        ];

        $result = ValidationResult::invalid($errors);

        $warnings = $result->getWarnings();

        $this->assertCount(2, $warnings);
        $this->assertSame('Warning 1', $warnings[0]->message);
        $this->assertSame('Warning 2', $warnings[1]->message);
    }

    public function testHasErrorsReturnsTrueWhenErrorsExist(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error', severity: ValidationError::SEVERITY_ERROR),
        ]);

        $this->assertTrue($result->hasErrors());
    }

    public function testHasErrorsReturnsFalseWhenOnlyWarnings(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Warning', severity: ValidationError::SEVERITY_WARNING),
        ]);

        $this->assertFalse($result->hasErrors());
    }

    public function testHasErrorsReturnsFalseWhenValid(): void
    {
        $result = ValidationResult::valid();

        $this->assertFalse($result->hasErrors());
    }

    public function testHasWarningsReturnsTrueWhenWarningsExist(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Warning', severity: ValidationError::SEVERITY_WARNING),
        ]);

        $this->assertTrue($result->hasWarnings());
    }

    public function testHasWarningsReturnsFalseWhenOnlyErrors(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error', severity: ValidationError::SEVERITY_ERROR),
        ]);

        $this->assertFalse($result->hasWarnings());
    }

    public function testGetErrorCount(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error 1', severity: ValidationError::SEVERITY_ERROR),
            new ValidationError(message: 'Warning', severity: ValidationError::SEVERITY_WARNING),
            new ValidationError(message: 'Error 2', severity: ValidationError::SEVERITY_ERROR),
        ]);

        $this->assertSame(2, $result->getErrorCount());
    }

    public function testGetWarningCount(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error', severity: ValidationError::SEVERITY_ERROR),
            new ValidationError(message: 'Warning 1', severity: ValidationError::SEVERITY_WARNING),
            new ValidationError(message: 'Warning 2', severity: ValidationError::SEVERITY_WARNING),
        ]);

        $this->assertSame(2, $result->getWarningCount());
    }

    public function testGetErrorMessages(): void
    {
        $result = ValidationResult::invalid([
            new ValidationError(message: 'Error 1', code: 'E1'),
            new ValidationError(message: 'Warning', severity: ValidationError::SEVERITY_WARNING),
            new ValidationError(message: 'Error 2', line: 10),
        ]);

        $messages = $result->getErrorMessages();

        $this->assertCount(2, $messages);
        $this->assertSame('[E1] Error 1', $messages[0]);
        $this->assertSame('Line 10: Error 2', $messages[1]);
    }

    public function testGetErrorMessagesReturnsEmptyArrayWhenValid(): void
    {
        $result = ValidationResult::valid();

        $this->assertSame([], $result->getErrorMessages());
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(ValidationResult::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
