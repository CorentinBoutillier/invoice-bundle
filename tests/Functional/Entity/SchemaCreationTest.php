<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Entity;

use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Tests de création du schéma Doctrine.
 *
 * Vérifie que le schéma peut être créé, détruit et maintenu sans erreurs.
 * Pattern standard pour les bundles Symfony : pas de fichiers migration,
 * le schéma est généré par l'application consommatrice.
 */
final class SchemaCreationTest extends RepositoryTestCase
{
    /**
     * Test 1: Le schéma peut être créé sans erreurs et toutes les tables existent.
     */
    public function testSchemaCanBeCreated(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Drop et recrée le schéma pour tester la création propre
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Vérifier que toutes les tables attendues existent
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map(
            static fn ($table) => $table->getName(),
            $schemaManager->listTables(),
        );

        $this->assertContains('invoice', $tables, 'Table "invoice" must exist');
        $this->assertContains('invoice_line', $tables, 'Table "invoice_line" must exist');
        $this->assertContains('payment', $tables, 'Table "payment" must exist');
        $this->assertContains('invoice_sequence', $tables, 'Table "invoice_sequence" must exist');
        $this->assertContains('invoice_history', $tables, 'Table "invoice_history" must exist');
    }

    /**
     * Test 2: Le schéma peut être détruit sans erreurs.
     */
    public function testSchemaCanBeDropped(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Le schéma existe déjà (créé dans setUp), on le détruit
        $schemaTool->dropSchema($metadata);

        // Vérifier qu'aucune table du bundle n'existe
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map(
            static fn ($table) => $table->getName(),
            $schemaManager->listTables(),
        );

        $this->assertNotContains('invoice', $tables, 'Table "invoice" should not exist after drop');
        $this->assertNotContains('invoice_line', $tables, 'Table "invoice_line" should not exist after drop');
        $this->assertNotContains('payment', $tables, 'Table "payment" should not exist after drop');
        $this->assertNotContains('invoice_sequence', $tables, 'Table "invoice_sequence" should not exist after drop');
        $this->assertNotContains('invoice_history', $tables, 'Table "invoice_history" should not exist after drop');

        // Recréer le schéma pour les tests suivants
        $schemaTool->createSchema($metadata);
    }

    /**
     * Test 3: Après création, le schéma ne doit avoir aucune mise à jour en attente.
     *
     * Cela garantit que les métadonnées Doctrine correspondent exactement
     * au schéma créé (pas de différence entre annotations et SQL généré).
     */
    public function testSchemaUpdateIsEmpty(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // S'assurer que le schéma est à jour
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Vérifier qu'aucune mise à jour n'est nécessaire
        $sqls = $schemaTool->getUpdateSchemaSql($metadata);

        $this->assertEmpty(
            $sqls,
            'Schema should be up-to-date after creation. Found pending SQL: '.print_r($sqls, true),
        );
    }
}
