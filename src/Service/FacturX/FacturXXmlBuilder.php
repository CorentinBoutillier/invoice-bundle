<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;

/**
 * Generates Factur-X XML (UN/CEFACT CrossIndustryInvoice) from Invoice entity.
 *
 * Implements BASIC profile with essential invoice data:
 * - Document metadata (number, date, type)
 * - Seller/Buyer information (snapshots)
 * - Line items with VAT
 * - Totals and VAT breakdown
 */
final class FacturXXmlBuilder implements FacturXXmlBuilderInterface
{
    // UN/CEFACT CII Namespaces
    private const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private \DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
    }

    public function build(Invoice $invoice, CompanyData $companyData): string
    {
        $this->dom->formatOutput = true;

        // Root element with namespaces
        $root = $this->dom->createElementNS(self::NS_RSM, 'rsm:CrossIndustryInvoice');
        if (false === $root) {
            throw new \RuntimeException('Failed to create root XML element');
        }

        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::NS_RAM);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::NS_UDT);
        $this->dom->appendChild($root);

        // Build sections
        $root->appendChild($this->buildDocumentContext());
        $root->appendChild($this->buildExchangedDocument($invoice));
        $root->appendChild($this->buildSupplyChainTradeTransaction($invoice, $companyData));

        $xml = $this->dom->saveXML();
        if (false === $xml) {
            throw new \RuntimeException('Failed to generate XML');
        }

        return $xml;
    }

    private function buildDocumentContext(): \DOMElement
    {
        $context = $this->createElement('rsm:ExchangedDocumentContext');

        $guideline = $this->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($this->createElement('ram:ID', 'urn:factur-x.eu:1p0:basic'));
        $context->appendChild($guideline);

        return $context;
    }

    private function buildExchangedDocument(Invoice $invoice): \DOMElement
    {
        $doc = $this->createElement('rsm:ExchangedDocument');

        // Invoice number
        $doc->appendChild($this->createElement('ram:ID', $invoice->getNumber() ?? ''));

        // Document type code (380 = Invoice, 381 = Credit Note)
        $typeCode = InvoiceType::CREDIT_NOTE === $invoice->getType() ? '381' : '380';
        $doc->appendChild($this->createElement('ram:TypeCode', $typeCode));

        // Issue date (format 102 = YYYYMMDD)
        $issueDateTime = $this->createElement('ram:IssueDateTime');
        $dateString = $this->createElement('udt:DateTimeString', $invoice->getDate()->format('Ymd'));
        $dateString->setAttribute('format', '102');
        $issueDateTime->appendChild($dateString);
        $doc->appendChild($issueDateTime);

        return $doc;
    }

    private function buildSupplyChainTradeTransaction(Invoice $invoice, CompanyData $companyData): \DOMElement
    {
        $transaction = $this->createElement('rsm:SupplyChainTradeTransaction');

        // Trade Agreement (Seller + Buyer)
        $transaction->appendChild($this->buildHeaderTradeAgreement($invoice, $companyData));

        // Trade Delivery (delivery date - optional in BASIC)
        $transaction->appendChild($this->buildHeaderTradeDelivery($invoice));

        // Trade Settlement (Currency, VAT, Totals, Payment)
        $transaction->appendChild($this->buildHeaderTradeSettlement($invoice, $companyData));

        // Line Items
        foreach ($invoice->getLines() as $line) {
            $transaction->appendChild($this->buildLineItem($line));
        }

        return $transaction;
    }

    private function buildHeaderTradeAgreement(Invoice $invoice, CompanyData $companyData): \DOMElement
    {
        $agreement = $this->createElement('ram:ApplicableHeaderTradeAgreement');

        $agreement->appendChild($this->buildSellerTradeParty($companyData));
        $agreement->appendChild($this->buildBuyerTradeParty($invoice));

        return $agreement;
    }

    private function buildSellerTradeParty(CompanyData $companyData): \DOMElement
    {
        $seller = $this->createElement('ram:SellerTradeParty');

        // Company name
        $seller->appendChild($this->createElement('ram:Name', $companyData->name));

        // SIRET (French company ID, scheme 0002)
        if ($companyData->siret) {
            $siretId = $this->createElement('ram:ID', $companyData->siret);
            $siretId->setAttribute('schemeID', '0002');
            $seller->appendChild($siretId);
        }

        // Postal address
        $seller->appendChild($this->buildPostalAddress($companyData->address));

        // VAT number
        if ($companyData->vatNumber) {
            $taxReg = $this->createElement('ram:SpecifiedTaxRegistration');
            $vatId = $this->createElement('ram:ID', $companyData->vatNumber);
            $vatId->setAttribute('schemeID', 'VA');
            $taxReg->appendChild($vatId);
            $seller->appendChild($taxReg);
        }

        return $seller;
    }

    private function buildBuyerTradeParty(Invoice $invoice): \DOMElement
    {
        $buyer = $this->createElement('ram:BuyerTradeParty');

        // Customer name
        $buyer->appendChild($this->createElement('ram:Name', $invoice->getCustomerName()));

        // Customer SIRET (if available)
        if ($invoice->getCustomerSiret()) {
            $siretId = $this->createElement('ram:ID', $invoice->getCustomerSiret());
            $siretId->setAttribute('schemeID', '0002');
            $buyer->appendChild($siretId);
        }

        // Postal address
        $buyer->appendChild($this->buildPostalAddress($invoice->getCustomerAddress()));

        // VAT number
        if ($invoice->getCustomerVatNumber()) {
            $taxReg = $this->createElement('ram:SpecifiedTaxRegistration');
            $vatId = $this->createElement('ram:ID', $invoice->getCustomerVatNumber());
            $vatId->setAttribute('schemeID', 'VA');
            $taxReg->appendChild($vatId);
            $buyer->appendChild($taxReg);
        }

        return $buyer;
    }

    private function buildPostalAddress(string $fullAddress): \DOMElement
    {
        $address = $this->createElement('ram:PostalTradeAddress');

        // Put full address in LineOne (simplified for BASIC profile)
        $address->appendChild($this->createElement('ram:LineOne', $fullAddress));

        return $address;
    }

    private function buildHeaderTradeDelivery(Invoice $invoice): \DOMElement
    {
        $delivery = $this->createElement('ram:ApplicableHeaderTradeDelivery');

        // Actual delivery date = invoice date (simplified)
        $occurrenceDateTime = $this->createElement('ram:ActualDeliverySupplyChainEvent');
        $dateTime = $this->createElement('ram:OccurrenceDateTime');
        $dateString = $this->createElement('udt:DateTimeString', $invoice->getDate()->format('Ymd'));
        $dateString->setAttribute('format', '102');
        $dateTime->appendChild($dateString);
        $occurrenceDateTime->appendChild($dateTime);
        $delivery->appendChild($occurrenceDateTime);

        return $delivery;
    }

    private function buildHeaderTradeSettlement(Invoice $invoice, CompanyData $companyData): \DOMElement
    {
        $settlement = $this->createElement('ram:ApplicableHeaderTradeSettlement');

        // Currency code
        $settlement->appendChild($this->createElement('ram:InvoiceCurrencyCode', 'EUR'));

        // Credit note reference (if applicable)
        if (InvoiceType::CREDIT_NOTE === $invoice->getType() && $invoice->getCreditedInvoice()) {
            $refDoc = $this->createElement('ram:InvoiceReferencedDocument');
            $refDoc->appendChild($this->createElement('ram:IssuerAssignedID', $invoice->getCreditedInvoice()->getNumber() ?? ''));
            $settlement->appendChild($refDoc);
        }

        // VAT breakdown by rate
        foreach ($this->calculateVatBreakdown($invoice) as $vatData) {
            $settlement->appendChild($this->buildApplicableTradeTax($vatData));
        }

        // Payment terms (due date)
        $paymentTerms = $this->createElement('ram:SpecifiedTradePaymentTerms');
        $dueDateTime = $this->createElement('ram:DueDateDateTime');
        $dueDateString = $this->createElement('udt:DateTimeString', $invoice->getDueDate()->format('Ymd'));
        $dueDateString->setAttribute('format', '102');
        $dueDateTime->appendChild($dueDateString);
        $paymentTerms->appendChild($dueDateTime);
        $settlement->appendChild($paymentTerms);

        // Payment means (bank transfer with IBAN)
        if ($companyData->iban) {
            $paymentMeans = $this->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
            $paymentMeans->appendChild($this->createElement('ram:TypeCode', '58')); // 58 = SEPA Credit Transfer

            $creditorAccount = $this->createElement('ram:PayeePartyCreditorFinancialAccount');
            $creditorAccount->appendChild($this->createElement('ram:IBANID', $companyData->iban));
            $paymentMeans->appendChild($creditorAccount);

            $settlement->appendChild($paymentMeans);
        }

        // Monetary summation (totals)
        $settlement->appendChild($this->buildMonetarySummation($invoice));

        return $settlement;
    }

    /**
     * @param array{rate: string, basis: string, amount: string} $vatData
     */
    private function buildApplicableTradeTax(array $vatData): \DOMElement
    {
        $tax = $this->createElement('ram:ApplicableTradeTax');

        $tax->appendChild($this->createElement('ram:CalculatedAmount', $vatData['amount']));
        $tax->appendChild($this->createElement('ram:TypeCode', 'VAT'));
        $tax->appendChild($this->createElement('ram:BasisAmount', $vatData['basis']));
        $tax->appendChild($this->createElement('ram:CategoryCode', 'S')); // S = Standard rate
        $tax->appendChild($this->createElement('ram:RateApplicablePercent', $vatData['rate']));

        return $tax;
    }

    /**
     * Calculate VAT breakdown grouped by rate.
     *
     * @return array<int, array{rate: string, basis: string, amount: string}>
     */
    private function calculateVatBreakdown(Invoice $invoice): array
    {
        $breakdown = [];

        foreach ($invoice->getLines() as $line) {
            $rate = number_format($line->getVatRate(), 2, '.', '');

            if (!isset($breakdown[$rate])) {
                $breakdown[$rate] = [
                    'rate' => $rate,
                    'basis' => '0.00',
                    'amount' => '0.00',
                ];
            }

            $lineBasis = $line->getTotalBeforeVat();
            $lineVat = $line->getVatAmount();

            $breakdown[$rate]['basis'] = number_format(
                (float) $breakdown[$rate]['basis'] + ($lineBasis->getAmount() / 100.0),
                2,
                '.',
                '',
            );

            $breakdown[$rate]['amount'] = number_format(
                (float) $breakdown[$rate]['amount'] + ($lineVat->getAmount() / 100.0),
                2,
                '.',
                '',
            );
        }

        // Apply global discount to basis (proportionally)
        if (!$invoice->getGlobalDiscountAmount()->isZero()) {
            $discountAmount = $invoice->getGlobalDiscountAmount()->getAmount() / 100.0;
            $totalBasis = array_sum(array_map(fn ($v) => (float) $v['basis'], $breakdown));

            foreach ($breakdown as $rate => $vatData) {
                $proportion = $totalBasis > 0 ? ((float) $vatData['basis']) / $totalBasis : 0;
                $rateDiscount = $discountAmount * $proportion;

                $newBasis = ((float) $vatData['basis']) - $rateDiscount;
                $newAmount = $newBasis * ((float) $vatData['rate']) / 100;

                $breakdown[$rate]['basis'] = number_format($newBasis, 2, '.', '');
                $breakdown[$rate]['amount'] = number_format($newAmount, 2, '.', '');
            }
        }

        return array_values($breakdown);
    }

    private function buildMonetarySummation(Invoice $invoice): \DOMElement
    {
        $summation = $this->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');

        // Line total (before discount)
        $summation->appendChild($this->createElement(
            'ram:LineTotalAmount',
            $invoice->getSubtotalBeforeDiscount()->toEuros(),
        ));

        // Global discount (if any)
        if (!$invoice->getGlobalDiscountAmount()->isZero()) {
            $summation->appendChild($this->createElement(
                'ram:AllowanceTotalAmount',
                $invoice->getGlobalDiscountAmount()->toEuros(),
            ));
        }

        // Tax basis total (after discount)
        $summation->appendChild($this->createElement(
            'ram:TaxBasisTotalAmount',
            $invoice->getSubtotalAfterDiscount()->toEuros(),
        ));

        // Total VAT
        $taxTotal = $this->createElement('ram:TaxTotalAmount', $invoice->getTotalVat()->toEuros());
        $taxTotal->setAttribute('currencyID', 'EUR');
        $summation->appendChild($taxTotal);

        // Grand total (TTC)
        $summation->appendChild($this->createElement(
            'ram:GrandTotalAmount',
            $invoice->getTotalIncludingVat()->toEuros(),
        ));

        // Due payable amount (same as grand total if no partial payments)
        $summation->appendChild($this->createElement(
            'ram:DuePayableAmount',
            $invoice->getTotalIncludingVat()->toEuros(),
        ));

        return $summation;
    }

    private function buildLineItem(InvoiceLine $line): \DOMElement
    {
        $item = $this->createElement('ram:IncludedSupplyChainTradeLineItem');

        // Line ID (sequential)
        /** @var int $lineId */
        static $lineId = 1;
        $doc = $this->createElement('ram:AssociatedDocumentLineDocument');
        $doc->appendChild($this->createElement('ram:LineID', (string) $lineId++));
        $item->appendChild($doc);

        // Product description
        $product = $this->createElement('ram:SpecifiedTradeProduct');
        $product->appendChild($this->createElement('ram:Name', $line->getDescription()));
        $item->appendChild($product);

        // Line Agreement (unit price)
        $agreement = $this->createElement('ram:SpecifiedLineTradeAgreement');
        $netPrice = $this->createElement('ram:NetPriceProductTradePrice');
        $netPrice->appendChild($this->createElement('ram:ChargeAmount', $line->getUnitPrice()->toEuros()));
        $agreement->appendChild($netPrice);
        $item->appendChild($agreement);

        // Line Delivery (quantity)
        $delivery = $this->createElement('ram:SpecifiedLineTradeDelivery');
        $quantity = $this->createElement('ram:BilledQuantity', number_format($line->getQuantity(), 4, '.', ''));
        $quantity->setAttribute('unitCode', 'HUR'); // HUR = hours (default, could be configurable)
        $delivery->appendChild($quantity);
        $item->appendChild($delivery);

        // Line Settlement (VAT, total)
        $settlement = $this->createElement('ram:SpecifiedLineTradeSettlement');

        // VAT
        $tax = $this->createElement('ram:ApplicableTradeTax');
        $tax->appendChild($this->createElement('ram:TypeCode', 'VAT'));
        $tax->appendChild($this->createElement('ram:CategoryCode', 'S'));
        $tax->appendChild($this->createElement('ram:RateApplicablePercent', number_format($line->getVatRate(), 2, '.', '')));
        $settlement->appendChild($tax);

        // Line total
        $monetarySummation = $this->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
        $monetarySummation->appendChild($this->createElement('ram:LineTotalAmount', $line->getTotalBeforeVat()->toEuros()));
        $settlement->appendChild($monetarySummation);

        $item->appendChild($settlement);

        return $item;
    }

    private function createElement(string $name, string $value = ''): \DOMElement
    {
        // Extract namespace prefix and local name
        if (str_contains($name, ':')) {
            [$prefix, $localName] = explode(':', $name, 2);
            $namespace = match ($prefix) {
                'rsm' => self::NS_RSM,
                'ram' => self::NS_RAM,
                'udt' => self::NS_UDT,
                default => throw new \InvalidArgumentException("Unknown namespace prefix: {$prefix}"),
            };

            $element = $this->dom->createElementNS($namespace, $name);
        } else {
            $element = $this->dom->createElement($name);
        }

        if (false === $element) {
            throw new \RuntimeException(\sprintf('Failed to create DOM element "%s"', $name));
        }

        if ('' !== $value) {
            $element->appendChild($this->dom->createTextNode($value));
        }

        return $element;
    }
}
