<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Entity;

use CorentinBoutillier\InvoiceBundle\Doctrine\Type\MoneyType;
use CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository\RepositoryTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\SchemaValidator;

/**
 * Tests de validation du schéma Doctrine.
 *
 * Vérifie que toutes les entités, contraintes, index et types custom
 * sont correctement configurés selon les spécifications du bundle.
 */
final class SchemaValidationTest extends RepositoryTestCase
{
    /**
     * Test 1: Le schéma Doctrine ne doit avoir aucune erreur de mapping.
     */
    public function testSchemaIsValid(): void
    {
        $validator = new SchemaValidator($this->entityManager);
        $errors = $validator->validateMapping();

        $this->assertEmpty(
            $errors,
            'Doctrine schema has mapping errors: '.print_r($errors, true),
        );
    }

    /**
     * Test 2: Le type custom Money doit être enregistré dans Doctrine DBAL.
     */
    public function testMoneyTypeIsRegistered(): void
    {
        $this->assertTrue(
            Type::hasType('money'),
            'Money custom type must be registered in Doctrine DBAL',
        );

        $moneyType = Type::getType('money');
        $this->assertInstanceOf(
            MoneyType::class,
            $moneyType,
            'Money type must be an instance of MoneyType',
        );
    }

    /**
     * Test 3: La table invoice doit avoir une contrainte unique sur la colonne 'number'.
     */
    public function testInvoiceUniqueConstraintOnNumber(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $invoiceTable = $schemaManager->introspectTable('invoice');
        $indexes = $invoiceTable->getIndexes();

        $hasUniqueNumber = false;
        foreach ($indexes as $index) {
            if ($index->isUnique() && \in_array('number', $index->getColumns(), true)) {
                $hasUniqueNumber = true;
                break;
            }
        }

        $this->assertTrue(
            $hasUniqueNumber,
            'Invoice table must have unique constraint on "number" column',
        );
    }

    /**
     * Test 4: La table invoice_sequence doit avoir une contrainte unique
     * sur (company_id, fiscal_year, type).
     */
    public function testInvoiceSequenceUniqueConstraint(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $sequenceTable = $schemaManager->introspectTable('invoice_sequence');
        $indexes = $sequenceTable->getIndexes();

        $hasUniqueSequence = false;
        foreach ($indexes as $index) {
            if ($index->isUnique()) {
                $columns = $index->getColumns();
                // Vérifier que les 3 colonnes sont présentes (ordre peut varier)
                if (3 === \count($columns)
                    && \in_array('company_id', $columns, true)
                    && \in_array('fiscal_year', $columns, true)
                    && \in_array('type', $columns, true)) {
                    $hasUniqueSequence = true;
                    break;
                }
            }
        }

        $this->assertTrue(
            $hasUniqueSequence,
            'InvoiceSequence table must have unique constraint on (company_id, fiscal_year, type)',
        );
    }

    /**
     * Test 5: La table invoice doit avoir une FK vers elle-même (creditedInvoice)
     * avec onDelete: SET NULL.
     */
    public function testInvoiceForeignKeyConstraints(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $invoiceTable = $schemaManager->introspectTable('invoice');
        $foreignKeys = $invoiceTable->getForeignKeys();

        $foundSelfReference = false;
        foreach ($foreignKeys as $fk) {
            if ('invoice' === $fk->getForeignTableName()) {
                $this->assertSame(
                    'SET NULL',
                    $fk->getOption('onDelete'),
                    'Invoice self-reference FK (creditedInvoice) must have onDelete: SET NULL',
                );
                $foundSelfReference = true;
                break;
            }
        }

        $this->assertTrue(
            $foundSelfReference,
            'Invoice table must have a self-reference FK for creditedInvoice',
        );
    }

    /**
     * Test 6: La table invoice_line doit avoir une FK vers invoice avec onDelete: CASCADE.
     */
    public function testInvoiceLineForeignKeyConstraint(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $lineTable = $schemaManager->introspectTable('invoice_line');
        $foreignKeys = $lineTable->getForeignKeys();

        $foundInvoiceFk = false;
        foreach ($foreignKeys as $fk) {
            if ('invoice' === $fk->getForeignTableName()) {
                $this->assertSame(
                    'CASCADE',
                    $fk->getOption('onDelete'),
                    'InvoiceLine FK to Invoice must have onDelete: CASCADE',
                );
                $foundInvoiceFk = true;
                break;
            }
        }

        $this->assertTrue(
            $foundInvoiceFk,
            'InvoiceLine table must have FK to Invoice with CASCADE',
        );
    }

    /**
     * Test 7: La table payment doit avoir une FK vers invoice avec onDelete: CASCADE.
     */
    public function testPaymentForeignKeyConstraint(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $paymentTable = $schemaManager->introspectTable('payment');
        $foreignKeys = $paymentTable->getForeignKeys();

        $foundInvoiceFk = false;
        foreach ($foreignKeys as $fk) {
            if ('invoice' === $fk->getForeignTableName()) {
                $this->assertSame(
                    'CASCADE',
                    $fk->getOption('onDelete'),
                    'Payment FK to Invoice must have onDelete: CASCADE',
                );
                $foundInvoiceFk = true;
                break;
            }
        }

        $this->assertTrue(
            $foundInvoiceFk,
            'Payment table must have FK to Invoice with CASCADE',
        );
    }

    /**
     * Test 8: La table invoice_history doit avoir une FK vers invoice avec onDelete: CASCADE.
     */
    public function testHistoryForeignKeyConstraint(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $historyTable = $schemaManager->introspectTable('invoice_history');
        $foreignKeys = $historyTable->getForeignKeys();

        $foundInvoiceFk = false;
        foreach ($foreignKeys as $fk) {
            if ('invoice' === $fk->getForeignTableName()) {
                $this->assertSame(
                    'CASCADE',
                    $fk->getOption('onDelete'),
                    'InvoiceHistory FK to Invoice must have onDelete: CASCADE',
                );
                $foundInvoiceFk = true;
                break;
            }
        }

        $this->assertTrue(
            $foundInvoiceFk,
            'InvoiceHistory table must have FK to Invoice with CASCADE',
        );
    }

    /**
     * Test 9: La table payment doit avoir une colonne discriminator 'type'
     * pour Single Table Inheritance.
     */
    public function testPaymentDiscriminatorColumn(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $paymentTable = $schemaManager->introspectTable('payment');
        $columns = $paymentTable->getColumns();

        $this->assertArrayHasKey(
            'type',
            $columns,
            'Payment table must have a "type" discriminator column for Single Table Inheritance',
        );

        $typeColumn = $columns['type'];
        $this->assertSame(
            'string',
            $typeColumn->getType()->getName(),
            'Payment discriminator column must be of type STRING',
        );
    }

    /**
     * Test 10: La table invoice doit avoir les colonnes nécessaires pour les index.
     */
    public function testInvoiceIndexesExist(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $invoiceTable = $schemaManager->introspectTable('invoice');
        $columns = array_keys($invoiceTable->getColumns());

        $this->assertContains(
            'company_id',
            $columns,
            'Invoice table must have company_id column for indexing',
        );

        $this->assertContains(
            'fiscal_year',
            $columns,
            'Invoice table must have fiscal_year column for indexing',
        );

        $this->assertContains(
            'status',
            $columns,
            'Invoice table must have status column for indexing',
        );

        $this->assertContains(
            'date',
            $columns,
            'Invoice table must have date column for indexing',
        );
    }
}
