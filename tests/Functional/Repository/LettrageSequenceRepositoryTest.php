<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Functional\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;
use CorentinBoutillier\InvoiceBundle\Repository\LettrageSequenceRepository;

/**
 * Tests fonctionnels pour LettrageSequenceRepository.
 *
 * Vérifie le comportement du repository avec verrou pessimiste
 * et la thread-safety de la génération des codes de lettrage.
 */
final class LettrageSequenceRepositoryTest extends RepositoryTestCase
{
    /** @phpstan-ignore property.uninitialized */
    private LettrageSequenceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->entityManager->getRepository(LettrageSequence::class);
        if (!$repository instanceof LettrageSequenceRepository) {
            throw new \RuntimeException('LettrageSequenceRepository not found');
        }
        $this->repository = $repository;
    }

    // ========== findForUpdate() with PESSIMISTIC_WRITE ==========

    public function testFindForUpdateReturnsSequence(): void
    {
        // Créer une séquence
        $sequence = new LettrageSequence(companyId: null);
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Recherche avec verrou (nécessite une transaction)
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(null);

            $this->assertNotNull($result);
            $this->assertNull($result->getCompanyId());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testFindForUpdateReturnsNullWhenNotFound(): void
    {
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(999);

            $this->assertNull($result);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testFindForUpdateFiltersByCompanyId(): void
    {
        // Créer des séquences pour deux entreprises
        $sequence1 = new LettrageSequence(companyId: 1);
        $this->entityManager->persist($sequence1);

        $sequence2 = new LettrageSequence(companyId: 2);
        $this->entityManager->persist($sequence2);

        $this->entityManager->flush();

        // Recherche pour l'entreprise 1
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(1);

            $this->assertNotNull($result);
            $this->assertSame(1, $result->getCompanyId());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    // ========== findOrCreate() ==========

    public function testFindOrCreateReturnsExisting(): void
    {
        // Créer une séquence existante
        $sequence = new LettrageSequence(companyId: 1);
        $sequence->setLastCode(5);
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();

        // Recherche ou création
        $result = $this->repository->findOrCreate(1);

        $this->assertNotNull($result);
        $this->assertSame($sequence->getId(), $result->getId());
        $this->assertSame(5, $result->getLastCode());
    }

    public function testFindOrCreateCreatesNewWhenNotFound(): void
    {
        $countBefore = $this->repository->count([]);

        $result = $this->repository->findOrCreate(42);

        $countAfter = $this->repository->count([]);

        $this->assertNotNull($result);
        $this->assertSame(42, $result->getCompanyId());
        $this->assertSame(0, $result->getLastCode());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testFindOrCreateHandlesNullCompanyId(): void
    {
        // Mode mono-entreprise
        $result = $this->repository->findOrCreate(null);

        $this->assertNotNull($result);
        $this->assertNull($result->getCompanyId());
        $this->assertSame(0, $result->getLastCode());
    }

    // ========== Thread-safety / Concurrency ==========

    public function testIncrementSequenceIsThreadSafe(): void
    {
        // Créer une séquence
        $sequence = new LettrageSequence(companyId: null);
        $this->entityManager->persist($sequence);
        $this->entityManager->flush();
        $initialId = $sequence->getId();

        // Simuler deux incréments concurrents
        // Transaction 1
        $this->entityManager->beginTransaction();
        try {
            $seq1 = $this->repository->findForUpdate(null);
            $this->assertNotNull($seq1);
            $code1 = $seq1->getNextCode();
            $seq1->incrementLastCode();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Transaction 2
        $this->entityManager->beginTransaction();
        try {
            $seq2 = $this->repository->findForUpdate(null);
            $this->assertNotNull($seq2);
            $code2 = $seq2->getNextCode();
            $seq2->incrementLastCode();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        // Vérifier les codes séquentiels
        $this->assertSame(1, $code1);
        $this->assertSame(2, $code2);

        // Vérifier l'état final
        $this->entityManager->clear();
        $final = $this->entityManager->find(LettrageSequence::class, $initialId);
        $this->assertNotNull($final);
        $this->assertSame(2, $final->getLastCode());
    }

    // ========== Isolation par entreprise ==========

    public function testSequencesAreIsolatedByCompany(): void
    {
        // Créer des séquences pour deux entreprises
        $seq1 = new LettrageSequence(companyId: 1);
        $seq1->incrementLastCode();
        $seq1->incrementLastCode();
        $this->entityManager->persist($seq1);

        $seq2 = new LettrageSequence(companyId: 2);
        $this->entityManager->persist($seq2);

        $this->entityManager->flush();

        // Vérifier l'isolation
        $this->assertSame(2, $seq1->getLastCode());
        $this->assertSame(0, $seq2->getLastCode());
    }

    // ========== Edge cases ==========

    public function testFindForUpdateWithNullCompanyIdMatchesNullOnly(): void
    {
        // Créer une séquence avec companyId NULL
        $seqNull = new LettrageSequence(companyId: null);
        $this->entityManager->persist($seqNull);

        // Créer une séquence avec companyId = 1
        $seq1 = new LettrageSequence(companyId: 1);
        $this->entityManager->persist($seq1);

        $this->entityManager->flush();

        // Recherche avec NULL doit retourner la séquence NULL
        $this->entityManager->beginTransaction();
        try {
            $result = $this->repository->findForUpdate(null);

            $this->assertNotNull($result);
            $this->assertNull($result->getCompanyId());

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function testSequenceUniqueConstraintPerCompany(): void
    {
        // Créer une première séquence pour l'entreprise 1
        $seq1 = new LettrageSequence(companyId: 1);
        $this->entityManager->persist($seq1);
        $this->entityManager->flush();

        // findOrCreate doit retourner la même séquence
        $seq2 = $this->repository->findOrCreate(1);

        $this->assertSame($seq1->getId(), $seq2->getId());
    }
}
