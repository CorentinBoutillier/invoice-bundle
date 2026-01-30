<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Pdp;

use CorentinBoutillier\InvoiceBundle\Pdp\PdpCapability;
use PHPUnit\Framework\TestCase;

final class PdpCapabilityTest extends TestCase
{
    public function testAllCapabilitiesExist(): void
    {
        $this->assertSame('transmit', PdpCapability::TRANSMIT->value);
        $this->assertSame('receive', PdpCapability::RECEIVE->value);
        $this->assertSame('status', PdpCapability::STATUS->value);
        $this->assertSame('lifecycle', PdpCapability::LIFECYCLE->value);
        $this->assertSame('health_check', PdpCapability::HEALTH_CHECK->value);
        $this->assertSame('batch_transmit', PdpCapability::BATCH_TRANSMIT->value);
        $this->assertSame('webhooks', PdpCapability::WEBHOOKS->value);
        $this->assertSame('e_reporting', PdpCapability::E_REPORTING->value);
    }

    public function testCapabilityCount(): void
    {
        $this->assertCount(8, PdpCapability::cases());
    }

    public function testCapabilityFromValue(): void
    {
        $this->assertSame(PdpCapability::TRANSMIT, PdpCapability::from('transmit'));
        $this->assertSame(PdpCapability::RECEIVE, PdpCapability::from('receive'));
        $this->assertSame(PdpCapability::E_REPORTING, PdpCapability::from('e_reporting'));
    }

    public function testCapabilityTryFromInvalidValue(): void
    {
        $this->assertNull(PdpCapability::tryFrom('invalid'));
    }
}
