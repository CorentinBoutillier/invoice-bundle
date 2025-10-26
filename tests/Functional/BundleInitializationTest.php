<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional;

use CorentinBoutillier\InvoiceBundle\InvoiceBundle;
use CorentinBoutillier\InvoiceBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;

/**
 * Test d'initialisation du bundle.
 */
class BundleInitializationTest extends TestCase
{
    public function testBundleIsInstantiable(): void
    {
        $bundle = new InvoiceBundle();

        $this->assertInstanceOf(InvoiceBundle::class, $bundle);
    }

    public function testKernelBootsWithBundle(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        // Vérifier que le bundle est chargé
        $bundles = $kernel->getBundles();
        $this->assertArrayHasKey('InvoiceBundle', $bundles);
        $this->assertInstanceOf(InvoiceBundle::class, $bundles['InvoiceBundle']);

        $kernel->shutdown();
    }

    public function testContainerCompiles(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertNotNull($container);
        $this->assertTrue($container->has('doctrine'));

        $kernel->shutdown();
    }
}
