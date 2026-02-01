<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\LettrageSequence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des séquences de lettrage.
 *
 * Fournit des méthodes pour récupérer et verrouiller les séquences
 * de manière thread-safe lors de la génération des codes de lettrage.
 *
 * @extends ServiceEntityRepository<LettrageSequence>
 */
class LettrageSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LettrageSequence::class);
    }

    /**
     * Récupère et verrouille la séquence pour une entreprise donnée.
     *
     * Utilise un verrou pessimiste PESSIMISTIC_WRITE pour garantir
     * qu'aucune autre transaction ne peut modifier la séquence
     * tant que la transaction courante n'est pas terminée.
     *
     * @param int|null $companyId ID de l'entreprise (null pour mode mono-entreprise)
     *
     * @return LettrageSequence|null La séquence verrouillée ou null si non trouvée
     */
    public function findForUpdate(?int $companyId): ?LettrageSequence
    {
        $qb = $this->createQueryBuilder('s');

        if (null === $companyId) {
            $qb->where('s.companyId IS NULL');
        } else {
            $qb->where('s.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);

        /** @var LettrageSequence|null */
        return $query->getOneOrNullResult();
    }

    /**
     * Trouve ou crée une séquence pour une entreprise.
     *
     * Si aucune séquence n'existe pour l'entreprise, en crée une nouvelle.
     * ATTENTION : Cette méthode doit être appelée dans une transaction
     * et suivie d'un appel à findForUpdate() pour obtenir le verrou.
     *
     * @param int|null $companyId ID de l'entreprise (null pour mode mono-entreprise)
     *
     * @return LettrageSequence La séquence existante ou nouvellement créée
     */
    public function findOrCreate(?int $companyId): LettrageSequence
    {
        $qb = $this->createQueryBuilder('s');

        if (null === $companyId) {
            $qb->where('s.companyId IS NULL');
        } else {
            $qb->where('s.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var LettrageSequence|null $sequence */
        $sequence = $qb->getQuery()->getOneOrNullResult();

        if (null === $sequence) {
            $sequence = new LettrageSequence($companyId);
            $this->getEntityManager()->persist($sequence);
            $this->getEntityManager()->flush();
        }

        return $sequence;
    }
}
