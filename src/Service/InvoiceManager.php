<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\CreditNoteCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCancelledEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceUpdatedEvent;
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service de gestion des factures et avoirs.
 */
final class InvoiceManager implements InvoiceManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompanyProviderInterface $companyProvider,
        private readonly DueDateCalculatorInterface $dueDateCalculator,
    ) {
    }

    public function createInvoice(
        CustomerData $customerData,
        \DateTimeImmutable $date,
        string $paymentTerms,
        ?int $companyId = null,
        ?\DateTimeImmutable $dueDate = null,
        string $currency = 'EUR',
    ): Invoice {
        // Validate customer data
        $this->validateCustomerData($customerData);

        // Get company data from provider
        $companyData = $this->companyProvider->getCompanyData($companyId);

        // Validate company data
        $this->validateCompanyData($companyData);

        // Calculate or use custom due date
        $calculatedDueDate = $dueDate ?? $this->dueDateCalculator->calculate($date, $paymentTerms);

        // Validate due date
        $this->validateDueDate($date, $calculatedDueDate);

        // Create invoice with snapshots
        $invoice = new Invoice(
            type: InvoiceType::INVOICE,
            date: $date,
            dueDate: $calculatedDueDate,
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: $companyData->name,
            companyAddress: $companyData->address,
        );

        // Set optional customer fields
        $invoice->setCustomerEmail($customerData->email);
        $invoice->setCustomerPhone($customerData->phone);
        $invoice->setCustomerSiret($customerData->siret);
        $invoice->setCustomerVatNumber($customerData->vatNumber);

        // Set optional company fields
        $invoice->setCompanyEmail($companyData->email);
        $invoice->setCompanyPhone($companyData->phone);
        $invoice->setCompanySiret($companyData->siret);
        $invoice->setCompanyVatNumber($companyData->vatNumber);
        $invoice->setCompanyBankName($companyData->bankName);
        $invoice->setCompanyIban($companyData->iban);
        $invoice->setCompanyBic($companyData->bic);

        // Set company ID if multi-company
        $invoice->setCompanyId($companyId);

        // Set payment terms
        $invoice->setPaymentTerms($paymentTerms);

        // Set status to DRAFT
        $invoice->setStatus(InvoiceStatus::DRAFT);

        // Persist and flush
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($invoice));

        return $invoice;
    }

    public function createCreditNote(
        CustomerData $customerData,
        \DateTimeImmutable $date,
        string $paymentTerms,
        ?Invoice $creditedInvoice = null,
        ?int $companyId = null,
        ?\DateTimeImmutable $dueDate = null,
        string $currency = 'EUR',
    ): Invoice {
        // Validate customer data
        $this->validateCustomerData($customerData);

        // Get company data from provider
        $companyData = $this->companyProvider->getCompanyData($companyId);

        // Validate company data
        $this->validateCompanyData($companyData);

        // Calculate or use custom due date
        $calculatedDueDate = $dueDate ?? $this->dueDateCalculator->calculate($date, $paymentTerms);

        // Validate due date
        $this->validateDueDate($date, $calculatedDueDate);

        // Create credit note with snapshots
        $creditNote = new Invoice(
            type: InvoiceType::CREDIT_NOTE,
            date: $date,
            dueDate: $calculatedDueDate,
            customerName: $customerData->name,
            customerAddress: $customerData->address,
            companyName: $companyData->name,
            companyAddress: $companyData->address,
        );

        // Set optional customer fields
        $creditNote->setCustomerEmail($customerData->email);
        $creditNote->setCustomerPhone($customerData->phone);
        $creditNote->setCustomerSiret($customerData->siret);
        $creditNote->setCustomerVatNumber($customerData->vatNumber);

        // Set optional company fields
        $creditNote->setCompanyEmail($companyData->email);
        $creditNote->setCompanyPhone($companyData->phone);
        $creditNote->setCompanySiret($companyData->siret);
        $creditNote->setCompanyVatNumber($companyData->vatNumber);
        $creditNote->setCompanyBankName($companyData->bankName);
        $creditNote->setCompanyIban($companyData->iban);
        $creditNote->setCompanyBic($companyData->bic);

        // Link to credited invoice if provided
        if (null !== $creditedInvoice) {
            $creditNote->setCreditedInvoice($creditedInvoice);
        }

        // Set company ID if multi-company
        $creditNote->setCompanyId($companyId);

        // Set payment terms
        $creditNote->setPaymentTerms($paymentTerms);

        // Set status to DRAFT
        $creditNote->setStatus(InvoiceStatus::DRAFT);

        // Persist and flush
        $this->entityManager->persist($creditNote);
        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(new CreditNoteCreatedEvent($creditNote, $creditedInvoice));

        return $creditNote;
    }

    public function addLine(Invoice $invoice, InvoiceLine $line): void
    {
        // Validate invoice is DRAFT
        $this->validateInvoiceIsDraft($invoice, 'add line to');

        // Add line to invoice
        $invoice->addLine($line);

        // Flush (cascade will persist line)
        $this->entityManager->flush();
    }

    public function updateInvoice(Invoice $invoice, array $data): void
    {
        // Validate invoice is DRAFT
        $this->validateInvoiceIsDraft($invoice, 'update');

        // Track changed fields
        $changedFields = [];

        // Apply all changes from data array
        foreach ($data as $field => $value) {
            $changed = $this->applyFieldChange($invoice, $field, $value);
            if ($changed) {
                $changedFields[] = $field;
            }
        }

        // Only flush and dispatch if fields actually changed
        if ([] !== $changedFields) {
            $this->entityManager->flush();

            // Dispatch event with changed fields
            $this->eventDispatcher->dispatch(new InvoiceUpdatedEvent($invoice, $changedFields));
        }
    }

    public function cancelInvoice(Invoice $invoice, ?string $reason = null): void
    {
        // Validate invoice can be cancelled
        $this->validateInvoiceCanBeCancelled($invoice);

        // Set status to CANCELLED
        $invoice->setStatus(InvoiceStatus::CANCELLED);

        // Flush
        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(new InvoiceCancelledEvent($invoice, $reason));
    }

    /**
     * Valide les données client.
     *
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    private function validateCustomerData(CustomerData $customerData): void
    {
        if ('' === trim($customerData->name)) {
            throw new \InvalidArgumentException('Customer name cannot be empty');
        }

        if ('' === trim($customerData->address)) {
            throw new \InvalidArgumentException('Customer address cannot be empty');
        }
    }

    /**
     * Valide les données société.
     *
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    private function validateCompanyData(\CorentinBoutillier\InvoiceBundle\DTO\CompanyData $companyData): void
    {
        if ('' === trim($companyData->name)) {
            throw new \InvalidArgumentException('Company name cannot be empty');
        }

        if ('' === trim($companyData->address)) {
            throw new \InvalidArgumentException('Company address cannot be empty');
        }
    }

    /**
     * Valide que la date d'échéance est >= à la date de facture.
     *
     * @throws \InvalidArgumentException Si la date d'échéance est invalide
     */
    private function validateDueDate(\DateTimeImmutable $invoiceDate, \DateTimeImmutable $dueDate): void
    {
        if ($dueDate < $invoiceDate) {
            throw new \InvalidArgumentException('Due date cannot be before invoice date');
        }
    }

    /**
     * Valide que la facture est en status DRAFT.
     *
     * @throws \InvalidArgumentException Si la facture n'est pas DRAFT
     */
    private function validateInvoiceIsDraft(Invoice $invoice, string $operation): void
    {
        if (InvoiceStatus::DRAFT !== $invoice->getStatus()) {
            throw new \InvalidArgumentException(
                \sprintf('Cannot %s invoice: invoice must be in DRAFT status', $operation),
            );
        }
    }

    /**
     * Valide qu'une facture peut être annulée.
     *
     * @throws \InvalidArgumentException Si la facture ne peut être annulée
     */
    private function validateInvoiceCanBeCancelled(Invoice $invoice): void
    {
        if (InvoiceStatus::CANCELLED === $invoice->getStatus()) {
            throw new \InvalidArgumentException('Cannot cancel invoice: invoice is already cancelled');
        }

        if (InvoiceStatus::DRAFT !== $invoice->getStatus()) {
            throw new \InvalidArgumentException('Cannot cancel invoice: only DRAFT invoices can be cancelled');
        }
    }

    /**
     * Applique un changement de champ sur une facture.
     *
     * @phpstan-param mixed $value
     *
     * @return bool True si le champ a changé, false sinon
     */
    private function applyFieldChange(Invoice $invoice, string $field, mixed $value): bool
    {
        return match ($field) {
            // Customer fields (those with setters)
            // @phpstan-ignore argument.type
            'customerEmail' => $this->updateIfChanged($invoice->getCustomerEmail(), $value, fn ($v) => $invoice->setCustomerEmail($v)),
            // @phpstan-ignore argument.type
            'customerPhone' => $this->updateIfChanged($invoice->getCustomerPhone(), $value, fn ($v) => $invoice->setCustomerPhone($v)),
            // @phpstan-ignore argument.type
            'customerSiret' => $this->updateIfChanged($invoice->getCustomerSiret(), $value, fn ($v) => $invoice->setCustomerSiret($v)),
            // @phpstan-ignore argument.type
            'customerVatNumber' => $this->updateIfChanged($invoice->getCustomerVatNumber(), $value, fn ($v) => $invoice->setCustomerVatNumber($v)),

            // Invoice fields
            // @phpstan-ignore argument.type
            'paymentTerms' => $this->updateIfChanged($invoice->getPaymentTerms(), $value, fn ($v) => $invoice->setPaymentTerms($v)),
            // @phpstan-ignore argument.type
            'dueDate' => $this->updateIfChanged($invoice->getDueDate(), $value, fn ($v) => $invoice->setDueDate($v)),
            // @phpstan-ignore argument.type
            'globalDiscountRate' => $this->updateIfChanged($invoice->getGlobalDiscountRate(), $value, fn ($v) => $invoice->setGlobalDiscountRate($v)),

            default => false,
        };
    }

    /**
     * Met à jour un champ si la valeur a changé.
     *
     * @param callable(mixed): void $setter
     *
     * @return bool True si mis à jour, false sinon
     */
    private function updateIfChanged(mixed $currentValue, mixed $newValue, callable $setter): bool
    {
        if ($currentValue === $newValue) {
            return false;
        }

        $setter($newValue);

        return true;
    }
}
