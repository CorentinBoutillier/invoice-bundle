<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration du Bundle de facturation.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('invoice');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                // Accounting configuration (FEC export)
                ->arrayNode('accounting')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('customer_account')
                            ->defaultValue('411000')
                            ->info('Customer receivables account (French PCG)')
                        ->end()
                        ->scalarNode('sales_account')
                            ->defaultValue('707000')
                            ->info('Sales revenue account (French PCG)')
                        ->end()
                        ->scalarNode('vat_collected_account')
                            ->defaultValue('445710')
                            ->info('VAT collected account (French PCG)')
                        ->end()
                        ->scalarNode('journal_code')
                            ->defaultValue('VT')
                            ->info('Journal code for sales (e.g., VT for "Ventes")')
                        ->end()
                        ->scalarNode('journal_label')
                            ->defaultValue('Ventes')
                            ->info('Journal label for sales')
                        ->end()
                    ->end()
                ->end()

                // PDF generation and storage configuration
                ->arrayNode('pdf')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage_path')
                            ->defaultValue('%kernel.project_dir%/var/invoices')
                            ->info('Directory path for storing generated PDF invoices')
                        ->end()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable PDF generation on invoice finalization')
                        ->end()
                    ->end()
                ->end()

                // Factur-X electronic invoicing configuration
                ->arrayNode('factur_x')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable Factur-X (PDF/A-3 with embedded XML) generation')
                        ->end()
                        ->enumNode('profile')
                            ->values(['MINIMUM', 'BASIC', 'BASIC_WL', 'EN16931', 'EXTENDED'])
                            ->defaultValue('BASIC')
                            ->info('Factur-X profile (MINIMUM, BASIC, BASIC_WL, EN16931, EXTENDED)')
                        ->end()
                        ->scalarNode('xml_filename')
                            ->defaultValue('factur-x.xml')
                            ->info('Filename for embedded XML in PDF/A-3')
                        ->end()
                    ->end()
                ->end()

                // Company information (optional, for mono-company setups)
                ->arrayNode('company')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')
                            ->defaultNull()
                            ->info('Company name')
                        ->end()
                        ->scalarNode('address')
                            ->defaultNull()
                            ->info('Company full address')
                        ->end()
                        ->scalarNode('siret')
                            ->defaultNull()
                            ->info('Company SIRET number')
                        ->end()
                        ->scalarNode('vat_number')
                            ->defaultNull()
                            ->info('Company VAT number')
                        ->end()
                        ->scalarNode('email')
                            ->defaultNull()
                            ->info('Company contact email')
                        ->end()
                        ->scalarNode('phone')
                            ->defaultNull()
                            ->info('Company phone number')
                        ->end()
                        ->scalarNode('bank_name')
                            ->defaultNull()
                            ->info('Bank name')
                        ->end()
                        ->scalarNode('iban')
                            ->defaultNull()
                            ->info('IBAN for payments')
                        ->end()
                        ->scalarNode('bic')
                            ->defaultNull()
                            ->info('BIC/SWIFT code')
                        ->end()
                    ->end()
                ->end()

                // French VAT rates (default values)
                ->arrayNode('vat_rates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('standard')
                            ->defaultValue(20.0)
                            ->info('Standard VAT rate (20% in France)')
                        ->end()
                        ->floatNode('intermediate')
                            ->defaultValue(10.0)
                            ->info('Intermediate VAT rate (10% in France)')
                        ->end()
                        ->floatNode('reduced')
                            ->defaultValue(5.5)
                            ->info('Reduced VAT rate (5.5% in France)')
                        ->end()
                        ->floatNode('super_reduced')
                            ->defaultValue(2.1)
                            ->info('Super reduced VAT rate (2.1% in France)')
                        ->end()
                    ->end()
                ->end()

                // Fiscal year configuration
                ->arrayNode('fiscal_year')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('start_month')
                            ->defaultValue(1)
                            ->min(1)
                            ->max(12)
                            ->info('Fiscal year start month (1-12, default 1 = January for calendar year)')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
