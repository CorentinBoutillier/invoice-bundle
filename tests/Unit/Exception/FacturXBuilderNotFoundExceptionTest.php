<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Exception;

use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Exception\FacturXBuilderNotFoundException;
use PHPUnit\Framework\TestCase;

final class FacturXBuilderNotFoundExceptionTest extends TestCase
{
    public function testConstructorWithProfile(): void
    {
        $exception = new FacturXBuilderNotFoundException(FacturXProfile::EN16931);

        $this->assertSame(FacturXProfile::EN16931, $exception->getProfile());
        $this->assertStringContainsString('EN16931', $exception->getMessage());
        $this->assertStringContainsString('No Factur-X XML builder found', $exception->getMessage());
    }

    public function testMessageIncludesProfileValue(): void
    {
        $exception = new FacturXBuilderNotFoundException(FacturXProfile::EXTENDED);

        $this->assertSame(
            'No Factur-X XML builder found for profile "EXTENDED"',
            $exception->getMessage(),
        );
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new FacturXBuilderNotFoundException(FacturXProfile::BASIC);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new FacturXBuilderNotFoundException(
            FacturXProfile::MINIMUM,
            42,
            $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(42, $exception->getCode());
    }

    public function testGetProfileReturnsCorrectProfile(): void
    {
        $profiles = [
            FacturXProfile::MINIMUM,
            FacturXProfile::BASIC,
            FacturXProfile::BASIC_WL,
            FacturXProfile::EN16931,
            FacturXProfile::EXTENDED,
        ];

        foreach ($profiles as $profile) {
            $exception = new FacturXBuilderNotFoundException($profile);
            $this->assertSame($profile, $exception->getProfile());
        }
    }
}
