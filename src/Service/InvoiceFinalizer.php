<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service;

use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePdfGeneratedEvent;
use CorentinBoutillier\InvoiceBundle\Exception\InvoiceFinalizationException;
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXConfigProviderInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\FacturXXmlBuilderInterface;
use CorentinBoutillier\InvoiceBundle\Service\FacturX\PdfA3ConverterInterface;
use CorentinBoutillier\InvoiceBundle\Service\NumberGenerator\InvoiceNumberGeneratorInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\PdfGeneratorInterface;
use CorentinBoutillier\InvoiceBundle\Service\Pdf\Storage\PdfStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class InvoiceFinalizer implements InvoiceFinalizerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceNumberGeneratorInterface $numberGenerator,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly PdfStorageInterface $pdfStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompanyProviderInterface $companyProvider,
        private readonly FacturXConfigProviderInterface $facturXConfig,
        private readonly FacturXXmlBuilderInterface $xmlBuilder,
        private readonly PdfA3ConverterInterface $pdfConverter,
    ) {
    }

    public function finalize(Invoice $invoice): void
    {
        // 1. Validations
        $this->validateInvoiceCanBeFinalized($invoice);

        // 2. Retrieve company data
        $companyData = $this->companyProvider->getCompanyData($invoice->getCompanyId());

        // 3. Begin atomic transaction
        $this->entityManager->beginTransaction();

        try {
            // 4. Generate invoice number (with CompanyData for fiscal year calculation)
            $number = $this->numberGenerator->generate($invoice, $companyData);
            $invoice->setNumber($number);
            $invoice->setStatus(InvoiceStatus::FINALIZED);

            // 5. Generate PDF
            $pdfContent = $this->pdfGenerator->generate($invoice, $companyData);

            // 6. Apply Factur-X conversion if enabled (but NOT for credit notes - library bug)
            // See: https://github.com/atgp/factur-x/issues - XPath namespace registration missing
            if ($this->facturXConfig->isEnabled() && InvoiceType::INVOICE === $invoice->getType()) {
                // Generate Factur-X XML from invoice
                $xmlContent = $this->xmlBuilder->build($invoice, $companyData);

                // Convert PDF to PDF/A-3 with embedded XML
                $pdfContent = $this->pdfConverter->embedXml(
                    $pdfContent,
                    $xmlContent,
                    $this->facturXConfig->getProfile(),
                );
            }

            // 7. Store PDF (standard or PDF/A-3 depending on config)
            $pdfPath = $this->pdfStorage->store($invoice, $pdfContent);

            // 8. Record PDF metadata on invoice
            $invoice->setPdfPath($pdfPath);
            $invoice->setPdfGeneratedAt(new \DateTimeImmutable());

            // 9. Flush and commit transaction
            $this->entityManager->flush();
            $this->entityManager->commit();

            // 10. Dispatch events (AFTER successful commit)
            $this->eventDispatcher->dispatch(new InvoiceFinalizedEvent($invoice, $number));
            $this->eventDispatcher->dispatch(new InvoicePdfGeneratedEvent($invoice, $pdfContent));
        } catch (\Exception $e) {
            // Rollback on any failure
            $this->entityManager->rollback();

            throw new InvoiceFinalizationException(
                'Failed to finalize invoice: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function validateInvoiceCanBeFinalized(Invoice $invoice): void
    {
        // Invoice must have at least one line
        if (empty($invoice->getLines())) {
            throw new \InvalidArgumentException('Cannot finalize invoice: invoice must have at least one line');
        }

        // Invoice must not already be finalized (idempotence check)
        if (InvoiceStatus::FINALIZED === $invoice->getStatus()) {
            throw new \InvalidArgumentException('Cannot finalize invoice: invoice is already finalized');
        }

        // Only DRAFT invoices can be finalized
        if (InvoiceStatus::DRAFT !== $invoice->getStatus()) {
            throw new \InvalidArgumentException('Cannot finalize invoice: only DRAFT invoices can be finalized');
        }
    }
}
