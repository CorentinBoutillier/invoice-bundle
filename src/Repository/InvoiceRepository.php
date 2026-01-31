<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Find finalized invoices for FEC export within a date range.
     *
     * Includes payments for lettrage generation.
     *
     * @return array<int, Invoice>
     */
    public function findForFecExport(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId,
    ): array {
        // Include all finalized invoices (finalized and beyond, excluding drafts and cancelled)
        $includedStatuses = [
            InvoiceStatus::FINALIZED,
            InvoiceStatus::SENT,
            InvoiceStatus::PAID,
            InvoiceStatus::PARTIALLY_PAID,
            InvoiceStatus::OVERDUE,
        ];

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.payments', 'p')
            ->addSelect('p')
            ->where('i.status IN (:statuses)')
            ->andWhere('i.date >= :startDate')
            ->andWhere('i.date <= :endDate')
            ->setParameter('statuses', $includedStatuses)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.date', 'ASC');

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Find invoices that are overdue (past due date and not fully paid).
     *
     * @return array<int, Invoice>
     */
    public function findOverdueInvoices(
        \DateTimeImmutable $referenceDate,
        ?int $companyId,
    ): array {
        $excludedStatuses = [InvoiceStatus::PAID, InvoiceStatus::CANCELLED];

        $qb = $this->createQueryBuilder('i')
            ->where('i.dueDate < :referenceDate')
            ->andWhere('i.status NOT IN (:excludedStatuses)')
            ->setParameter('referenceDate', $referenceDate)
            ->setParameter('excludedStatuses', $excludedStatuses)
            ->orderBy('i.dueDate', 'ASC');

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Find invoices by status with optional pagination.
     *
     * @return array<int, Invoice>
     */
    public function findByStatus(
        InvoiceStatus $status,
        ?int $companyId,
        ?int $limit,
        ?int $offset,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', $status)
            ->orderBy('i.date', 'DESC');

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== $offset) {
            $qb->setFirstResult($offset);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Find invoices by customer name (and optionally SIRET).
     *
     * Uses snapshot data, not entity relations.
     *
     * @return array<int, Invoice>
     */
    public function findByCustomer(
        string $customerName,
        ?string $customerSiret,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->where('i.customerName = :customerName')
            ->setParameter('customerName', $customerName)
            ->orderBy('i.date', 'DESC');

        if (null !== $customerSiret) {
            $qb->andWhere('i.customerSiret = :customerSiret')
                ->setParameter('customerSiret', $customerSiret);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Find invoices within a date range.
     *
     * @return array<int, Invoice>
     */
    public function findByDateRange(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $companyId,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->where('i.date >= :start')
            ->andWhere('i.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('i.date', 'ASC');

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Find a single invoice by its unique number.
     */
    public function findByNumber(string $number): ?Invoice
    {
        /** @var Invoice|null */
        return $this->createQueryBuilder('i')
            ->where('i.number = :number')
            ->setParameter('number', $number)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find invoices that are not fully paid.
     *
     * @return array<int, Invoice>
     */
    public function findUnpaidInvoices(?int $companyId): array
    {
        $unpaidStatuses = [
            InvoiceStatus::FINALIZED,
            InvoiceStatus::SENT,
            InvoiceStatus::PARTIALLY_PAID,
            InvoiceStatus::OVERDUE,
        ];

        $qb = $this->createQueryBuilder('i')
            ->where('i.status IN (:unpaidStatuses)')
            ->setParameter('unpaidStatuses', $unpaidStatuses)
            ->orderBy('i.dueDate', 'ASC');

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        /** @var array<int, Invoice> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Count invoices in a specific fiscal year.
     */
    public function countByFiscalYear(int $fiscalYear, ?int $companyId): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.fiscalYear = :fiscalYear')
            ->setParameter('fiscalYear', $fiscalYear);

        if (null !== $companyId) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
