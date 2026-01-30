<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Service\Validation;

use Atgp\FacturX\XsdValidator;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Exception\FacturXValidationException;
use CorentinBoutillier\InvoiceBundle\Service\Validation\FacturXXmlValidator;
use CorentinBoutillier\InvoiceBundle\Service\Validation\ValidationError;
use PHPUnit\Framework\TestCase;

class FacturXXmlValidatorTest extends TestCase
{
    public function testValidateWithEmptyXmlReturnsInvalid(): void
    {
        $validator = new FacturXXmlValidator();

        $result = $validator->validate('');

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertSame('XML content is empty', $result->errors[0]->message);
        $this->assertSame('EMPTY_XML', $result->errors[0]->code);
    }

    public function testValidateWithWhitespaceOnlyXmlReturnsInvalid(): void
    {
        $validator = new FacturXXmlValidator();

        $result = $validator->validate('   ');

        $this->assertFalse($result->isValid);
        $this->assertSame('EMPTY_XML', $result->errors[0]->code);
    }

    public function testValidateWithValidXmlReturnsValid(): void
    {
        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')->willReturn(true);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $result = $validator->validate('<xml>test</xml>');

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testValidateWithInvalidXmlReturnsErrors(): void
    {
        $libXmlError = new \LibXMLError();
        $libXmlError->level = \LIBXML_ERR_ERROR;
        $libXmlError->code = 1845;
        $libXmlError->message = "Element 'Invoice': Missing required element";
        $libXmlError->line = 42;
        $libXmlError->file = '';

        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')->willReturn(false);
        $mockXsdValidator->method('getXmlErrors')->willReturn([$libXmlError]);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $result = $validator->validate('<xml>invalid</xml>');

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertSame("Element 'Invoice': Missing required element", $result->errors[0]->message);
        $this->assertSame('1845', $result->errors[0]->code);
        $this->assertSame(42, $result->errors[0]->line);
        $this->assertSame(ValidationError::SEVERITY_ERROR, $result->errors[0]->severity);
    }

    public function testValidateWithWarningsReturnsWarnings(): void
    {
        $libXmlWarning = new \LibXMLError();
        $libXmlWarning->level = \LIBXML_ERR_WARNING;
        $libXmlWarning->code = 123;
        $libXmlWarning->message = 'Optional element missing';
        $libXmlWarning->line = 10;
        $libXmlWarning->file = '';

        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')->willReturn(false);
        $mockXsdValidator->method('getXmlErrors')->willReturn([$libXmlWarning]);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $result = $validator->validate('<xml>test</xml>');

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertSame(ValidationError::SEVERITY_WARNING, $result->errors[0]->severity);
    }

    public function testValidateWithExceptionReturnsError(): void
    {
        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')
            ->willThrowException(new \Exception('Unexpected validation error'));

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $result = $validator->validate('<xml>test</xml>');

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertSame('Unexpected validation error', $result->errors[0]->message);
        $this->assertSame('VALIDATION_ERROR', $result->errors[0]->code);
    }

    public function testValidateUsesCorrectProfile(): void
    {
        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->expects($this->once())
            ->method('validate')
            ->with('<xml>test</xml>', 'basic')
            ->willReturn(true);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $validator->validate('<xml>test</xml>', FacturXProfile::BASIC);
    }

    public function testValidateOrFailDoesNotThrowWhenValid(): void
    {
        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')->willReturn(true);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        // Should not throw
        $validator->validateOrFail('<xml>test</xml>');

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testValidateOrFailThrowsWhenInvalid(): void
    {
        $libXmlError = new \LibXMLError();
        $libXmlError->level = \LIBXML_ERR_ERROR;
        $libXmlError->code = 1;
        $libXmlError->message = 'Invalid XML';
        $libXmlError->line = 1;
        $libXmlError->file = '';

        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->method('validate')->willReturn(false);
        $mockXsdValidator->method('getXmlErrors')->willReturn([$libXmlError]);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $this->expectException(FacturXValidationException::class);
        $this->expectExceptionMessage('Invalid XML');

        $validator->validateOrFail('<xml>invalid</xml>');
    }

    public function testSupportsReturnsTrue(): void
    {
        $validator = new FacturXXmlValidator();

        $this->assertTrue($validator->supports(FacturXProfile::MINIMUM));
        $this->assertTrue($validator->supports(FacturXProfile::BASIC));
        $this->assertTrue($validator->supports(FacturXProfile::BASIC_WL));
        $this->assertTrue($validator->supports(FacturXProfile::EN16931));
        $this->assertTrue($validator->supports(FacturXProfile::EXTENDED));
    }

    public function testDefaultProfileIsEN16931(): void
    {
        $mockXsdValidator = $this->createMock(XsdValidator::class);
        $mockXsdValidator->expects($this->once())
            ->method('validate')
            ->with('<xml>test</xml>', 'en16931')
            ->willReturn(true);

        $validator = new FacturXXmlValidator($mockXsdValidator);

        $validator->validate('<xml>test</xml>');
    }
}
