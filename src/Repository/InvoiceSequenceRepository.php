<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\InvoiceSequence;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceSequence>
 */
class InvoiceSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceSequence::class);
    }

    /**
     * Find a sequence with pessimistic write lock for thread-safe operations.
     *
     * This method MUST be used within a transaction to ensure the lock is held
     * until commit.
     */
    public function findForUpdate(
        ?int $companyId,
        int $fiscalYear,
        InvoiceType $type,
    ): ?InvoiceSequence {
        $qb = $this->createQueryBuilder('s')
            ->where('s.fiscalYear = :fiscalYear')
            ->andWhere('s.type = :type')
            ->setParameter('fiscalYear', $fiscalYear)
            ->setParameter('type', $type);

        // Handle NULL company ID
        if (null === $companyId) {
            $qb->andWhere('s.companyId IS NULL');
        } else {
            $qb->andWhere('s.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);

        /** @var InvoiceSequence|null */
        return $query->getOneOrNullResult();
    }

    /**
     * Find a sequence that contains the given date, or NULL if not found.
     */
    public function findByDateContaining(
        ?int $companyId,
        \DateTimeImmutable $date,
        InvoiceType $type,
    ): ?InvoiceSequence {
        $qb = $this->createQueryBuilder('s')
            ->where('s.startDate <= :date')
            ->andWhere('s.endDate >= :date')
            ->andWhere('s.type = :type')
            ->setParameter('date', $date)
            ->setParameter('type', $type);

        // Handle NULL company ID
        if (null === $companyId) {
            $qb->andWhere('s.companyId IS NULL');
        } else {
            $qb->andWhere('s.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var InvoiceSequence|null */
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find an existing sequence for the given date, or create a new one if not found.
     *
     * This method calculates the fiscal year from the invoice date and fiscal year settings,
     * then creates the sequence with appropriate start/end dates.
     */
    public function findOrCreateSequence(
        ?int $companyId,
        \DateTimeImmutable $invoiceDate,
        InvoiceType $type,
        int $fiscalYearStartMonth,
        int $fiscalYearStartDay,
    ): InvoiceSequence {
        // Try to find existing sequence
        $existing = $this->findByDateContaining($companyId, $invoiceDate, $type);
        if (null !== $existing) {
            return $existing;
        }

        // Calculate fiscal year and create new sequence
        $fiscalYear = $this->calculateFiscalYear(
            $invoiceDate,
            $fiscalYearStartMonth,
            $fiscalYearStartDay,
        );

        $startDate = $this->createDateTime(
            $fiscalYear,
            $fiscalYearStartMonth,
            $fiscalYearStartDay,
        );

        // End date is one day before the start of the next fiscal year
        $endDate = $this->createDateTime(
            $fiscalYear + 1,
            $fiscalYearStartMonth,
            $fiscalYearStartDay,
        )->modify('-1 day');

        $sequence = new InvoiceSequence(
            companyId: $companyId,
            fiscalYear: $fiscalYear,
            type: $type,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->getEntityManager()->persist($sequence);
        $this->getEntityManager()->flush();

        return $sequence;
    }

    /**
     * Calculate the fiscal year for a given date.
     *
     * If the invoice date is before the fiscal year start date,
     * it belongs to the previous fiscal year.
     */
    private function calculateFiscalYear(
        \DateTimeImmutable $invoiceDate,
        int $fiscalYearStartMonth,
        int $fiscalYearStartDay,
    ): int {
        $year = (int) $invoiceDate->format('Y');

        // Create the fiscal year start date for this calendar year
        $fiscalYearStart = $this->createDateTime($year, $fiscalYearStartMonth, $fiscalYearStartDay);

        // If invoice date is before fiscal year start, it belongs to previous fiscal year
        if ($invoiceDate < $fiscalYearStart) {
            return $year - 1;
        }

        return $year;
    }

    /**
     * Create a DateTime for a specific date.
     */
    private function createDateTime(int $year, int $month, int $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
