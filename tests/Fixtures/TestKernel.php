<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Fixtures;

use CorentinBoutillier\InvoiceBundle\InvoiceBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Kernel Symfony minimal pour les tests.
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TwigBundle(),
            new InvoiceBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/invoice-bundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/invoice-bundle/logs';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => [
                'log' => true,
            ],
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'url' => 'sqlite:///:memory:',
                'charset' => 'utf8',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'enable_lazy_ghost_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'InvoiceBundle' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/../../src/Entity',
                        'prefix' => 'CorentinBoutillier\InvoiceBundle\Entity',
                        'alias' => 'InvoiceBundle',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('twig', [
            'default_path' => '%kernel.project_dir%/../../templates',
            'paths' => [
                '%kernel.project_dir%/../../templates' => 'Invoice',
            ],
        ]);

        // Register repositories as services
        $container->register('CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository')
            ->setClass('CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository')
            ->addArgument(new Reference('doctrine'))
            ->addTag('doctrine.repository_service')
            ->setPublic(true);

        $container->register('CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository')
            ->setClass('CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository')
            ->addArgument(new Reference('doctrine'))
            ->addTag('doctrine.repository_service')
            ->setPublic(true);

        // Register InvoiceNumberGenerator service
        $container->register('CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGenerator')
            ->setClass('CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGenerator')
            ->addArgument(new Reference('CorentinBoutillier\InvoiceBundle\Repository\InvoiceSequenceRepository'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface',
            'CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGenerator',
        )->setPublic(true);

        // Register PaymentManager service
        $container->register('CorentinBoutillier\InvoiceBundle\Service\PaymentManager')
            ->setClass('CorentinBoutillier\InvoiceBundle\Service\PaymentManager')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('event_dispatcher'))
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Service\PaymentManagerInterface',
            'CorentinBoutillier\InvoiceBundle\Service\PaymentManager',
        )->setPublic(true);

        // Register TwigPdfGenerator service
        $container->register('CorentinBoutillier\InvoiceBundle\Service\Pdf\TwigPdfGenerator')
            ->setClass('CorentinBoutillier\InvoiceBundle\Service\Pdf\TwigPdfGenerator')
            ->addArgument(new Reference('twig'))
            ->addArgument('@Invoice/invoice/pdf.html.twig')
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Service\Pdf\PdfGeneratorInterface',
            'CorentinBoutillier\InvoiceBundle\Service\Pdf\TwigPdfGenerator',
        )->setPublic(true);

        // Register ConfigCompanyProvider with test data
        $container->register('CorentinBoutillier\InvoiceBundle\Provider\ConfigCompanyProvider')
            ->setClass('CorentinBoutillier\InvoiceBundle\Provider\ConfigCompanyProvider')
            ->addArgument([
                'name' => 'Test Company SARL',
                'address' => '123 Test Street, 75001 Paris, France',
                'siret' => '12345678901234',
                'vatNumber' => 'FR12345678901',
                'email' => 'contact@testcompany.fr',
                'phone' => '+33 1 23 45 67 89',
                'bankName' => 'Test Bank',
                'iban' => 'FR7612345678901234567890123',
                'bic' => 'TESTFRPP',
            ])
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface',
            'CorentinBoutillier\InvoiceBundle\Provider\ConfigCompanyProvider',
        )->setPublic(true);

        // Register DueDateCalculator service
        $container->register('CorentinBoutillier\InvoiceBundle\Service\DueDateCalculator')
            ->setClass('CorentinBoutillier\InvoiceBundle\Service\DueDateCalculator')
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Service\DueDateCalculatorInterface',
            'CorentinBoutillier\InvoiceBundle\Service\DueDateCalculator',
        )->setPublic(true);

        // Register InvoiceManager service
        $container->register('CorentinBoutillier\InvoiceBundle\Service\InvoiceManager')
            ->setClass('CorentinBoutillier\InvoiceBundle\Service\InvoiceManager')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface'))
            ->addArgument(new Reference('CorentinBoutillier\InvoiceBundle\Service\DueDateCalculatorInterface'))
            ->setPublic(true);

        // Alias interface to implementation
        $container->setAlias(
            'CorentinBoutillier\InvoiceBundle\Service\InvoiceManagerInterface',
            'CorentinBoutillier\InvoiceBundle\Service\InvoiceManager',
        )->setPublic(true);

        // Make EventDispatcherInterface public for tests
        $container->setAlias(
            'Symfony\Component\EventDispatcher\EventDispatcherInterface',
            'event_dispatcher',
        )->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Pas de routes nécessaires pour les tests
    }
}
