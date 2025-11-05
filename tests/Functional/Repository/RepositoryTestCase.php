<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository;

use CorentinBoutillier\InvoiceBundle\Tests\Fixtures\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Classe de base pour les tests fonctionnels de repositories.
 */
abstract class RepositoryTestCase extends TestCase
{
    /** @phpstan-ignore property.uninitialized */
    protected TestKernel $kernel;

    /** @phpstan-ignore property.uninitialized */
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();

        $container = $this->kernel->getContainer();
        $doctrine = $container->get('doctrine');
        if (!$doctrine instanceof \Doctrine\Bundle\DoctrineBundle\Registry) {
            throw new \RuntimeException('Doctrine service not found');
        }

        $em = $doctrine->getManager();
        if (!$em instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManager not found');
        }
        $this->entityManager = $em;

        // Créer le schéma de la base de données
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        // Supprimer le schéma
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);

        // Fermer l'EntityManager et le kernel
        $this->entityManager->close();
        $this->kernel->shutdown();

        parent::tearDown();
    }
}
