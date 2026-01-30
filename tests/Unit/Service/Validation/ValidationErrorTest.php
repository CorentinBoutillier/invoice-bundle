<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\Validation;

use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationError;
use PHPUnit\Framework\TestCase;

class ValidationErrorTest extends TestCase
{
    public function testConstructWithMinimalData(): void
    {
        $error = new ValidationError(message: 'Test error');

        $this->assertSame('Test error', $error->message);
        $this->assertSame(ValidationError::SEVERITY_ERROR, $error->severity);
        $this->assertNull($error->code);
        $this->assertNull($error->line);
        $this->assertNull($error->path);
    }

    public function testConstructWithAllData(): void
    {
        $error = new ValidationError(
            message: 'Element not found',
            severity: ValidationError::SEVERITY_WARNING,
            code: 'XSD-001',
            line: 42,
            path: '/path/to/file.xml',
        );

        $this->assertSame('Element not found', $error->message);
        $this->assertSame(ValidationError::SEVERITY_WARNING, $error->severity);
        $this->assertSame('XSD-001', $error->code);
        $this->assertSame(42, $error->line);
        $this->assertSame('/path/to/file.xml', $error->path);
    }

    public function testIsErrorReturnsTrueForErrors(): void
    {
        $error = new ValidationError(
            message: 'Test',
            severity: ValidationError::SEVERITY_ERROR,
        );

        $this->assertTrue($error->isError());
        $this->assertFalse($error->isWarning());
    }

    public function testIsWarningReturnsTrueForWarnings(): void
    {
        $error = new ValidationError(
            message: 'Test',
            severity: ValidationError::SEVERITY_WARNING,
        );

        $this->assertTrue($error->isWarning());
        $this->assertFalse($error->isError());
    }

    public function testDefaultSeverityIsError(): void
    {
        $error = new ValidationError(message: 'Test');

        $this->assertTrue($error->isError());
    }

    public function testToStringWithMessageOnly(): void
    {
        $error = new ValidationError(message: 'Invalid element');

        $this->assertSame('Invalid element', (string) $error);
    }

    public function testToStringWithCodeAndMessage(): void
    {
        $error = new ValidationError(
            message: 'Invalid element',
            code: 'XSD-001',
        );

        $this->assertSame('[XSD-001] Invalid element', (string) $error);
    }

    public function testToStringWithLineAndMessage(): void
    {
        $error = new ValidationError(
            message: 'Invalid element',
            line: 42,
        );

        $this->assertSame('Line 42: Invalid element', (string) $error);
    }

    public function testToStringWithAllParts(): void
    {
        $error = new ValidationError(
            message: 'Invalid element',
            code: 'XSD-001',
            line: 42,
        );

        $this->assertSame('[XSD-001] Line 42: Invalid element', (string) $error);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(ValidationError::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
