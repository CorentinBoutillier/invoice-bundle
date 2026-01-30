<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Repository;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceTransmission;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpStatusCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for InvoiceTransmission entity.
 *
 * @extends ServiceEntityRepository<InvoiceTransmission>
 *
 * @method InvoiceTransmission|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvoiceTransmission|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvoiceTransmission[]    findAll()
 * @method InvoiceTransmission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceTransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceTransmission::class);
    }

    /**
     * Find the latest transmission for an invoice.
     */
    public function findLatestForInvoice(Invoice $invoice): ?InvoiceTransmission
    {
        /** @var InvoiceTransmission|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find all transmissions for an invoice.
     *
     * @return InvoiceTransmission[]
     */
    public function findByInvoice(Invoice $invoice): array
    {
        /** @var InvoiceTransmission[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find transmission by PDP transmission ID.
     */
    public function findByTransmissionId(string $transmissionId): ?InvoiceTransmission
    {
        return $this->findOneBy(['transmissionId' => $transmissionId]);
    }

    /**
     * Find all pending transmissions (not in terminal state).
     *
     * @return InvoiceTransmission[]
     */
    public function findPending(): array
    {
        $terminalStatuses = [
            PdpStatusCode::PAID->value,
            PdpStatusCode::REJECTED->value,
            PdpStatusCode::REFUSED->value,
            PdpStatusCode::FAILED->value,
            PdpStatusCode::CANCELLED->value,
        ];

        /** @var InvoiceTransmission[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status NOT IN (:terminal)')
            ->setParameter('terminal', $terminalStatuses)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find transmissions needing retry (failed with retry count below limit).
     *
     * @return InvoiceTransmission[]
     */
    public function findNeedingRetry(int $maxRetries = 3): array
    {
        /** @var InvoiceTransmission[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status = :failed')
            ->andWhere('t.retryCount < :maxRetries')
            ->setParameter('failed', PdpStatusCode::FAILED->value)
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('t.lastRetryAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find all transmissions by connector.
     *
     * @return InvoiceTransmission[]
     */
    public function findByConnector(string $connectorId): array
    {
        return $this->findBy(
            ['connectorId' => $connectorId],
            ['createdAt' => 'DESC'],
        );
    }

    /**
     * Find transmissions by status.
     *
     * @return InvoiceTransmission[]
     */
    public function findByStatus(PdpStatusCode $status): array
    {
        return $this->findBy(
            ['status' => $status],
            ['createdAt' => 'DESC'],
        );
    }

    /**
     * Count transmissions by status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var array<int, array{status: PdpStatusCode, count: string|int}> $result */
        $result = $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as count')
            ->groupBy('t.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $status = $row['status'];
            $counts[$status->value] = (int) $row['count'];
        }

        return $counts;
    }
}
