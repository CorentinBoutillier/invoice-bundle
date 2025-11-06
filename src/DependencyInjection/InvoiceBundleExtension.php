<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DependencyInjection;

use CorentinBoutillier\InvoiceBundle\Doctrine\Type\MoneyType;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension pour configurer le Bundle de facturation.
 */
class InvoiceBundleExtension extends Extension
{
    public function getAlias(): string
    {
        return 'invoice';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // 1. Process bundle configuration
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // 2. Register Doctrine MoneyType
        if (!Type::hasType('money')) {
            Type::addType('money', MoneyType::class);
        }

        // 3. Set container parameters from configuration
        /** @var array<string, mixed> $accounting */
        $accounting = $config['accounting'];
        $container->setParameter('invoice.accounting', $accounting);

        /** @var array<string, mixed> $pdf */
        $pdf = $config['pdf'];
        $container->setParameter('invoice.pdf', $pdf);

        /** @var array<string, mixed> $facturX */
        $facturX = $config['factur_x'];
        $container->setParameter('invoice.factur_x', $facturX);

        /** @var array<string, mixed> $company */
        $company = $config['company'];
        $container->setParameter('invoice.company', $company);

        /** @var array<string, mixed> $vatRates */
        $vatRates = $config['vat_rates'];
        $container->setParameter('invoice.vat_rates', $vatRates);

        /** @var array<string, mixed> $fiscalYear */
        $fiscalYear = $config['fiscal_year'];
        $container->setParameter('invoice.fiscal_year', $fiscalYear);

        // 4. Load service definitions
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }
}
