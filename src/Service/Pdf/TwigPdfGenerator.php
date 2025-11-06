<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\Pdf;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère un PDF à partir d'un template Twig en utilisant DomPDF.
 */
final class TwigPdfGenerator implements PdfGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $templatePath = '@Invoice/invoice/pdf.html.twig',
    ) {
    }

    public function generate(Invoice $invoice, CompanyData $companyData): string
    {
        // 1. Render Twig template with invoice data
        $html = $this->twig->render($this->templatePath, [
            'invoice' => $invoice,
            'company' => $companyData,
        ]);

        // 2. Configure DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        // 3. Generate PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Return binary PDF content
        $output = $dompdf->output();
        if (!\is_string($output)) {
            throw new \RuntimeException('Failed to generate PDF');
        }

        return $output;
    }
}
