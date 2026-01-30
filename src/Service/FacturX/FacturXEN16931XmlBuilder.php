<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Service\FacturX;

use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use CorentinBoutillier\InvoiceBundle\Entity\Invoice;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\FacturXProfile;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\TaxCategoryCode;

/**
 * Generates Factur-X XML (UN/CEFACT CrossIndustryInvoice) for EN16931 profile.
 *
 * EN16931 is the full European standard with ~165 business terms.
 * Required for French e-invoicing reform (September 2026).
 *
 * Additional fields compared to BASIC:
 * - BT-10: Buyer reference (mandatory for B2B/B2G)
 * - BT-13: Purchase order reference
 * - BG-8: Buyer postal address (structured)
 * - BG-15: Delivery to address
 * - BG-6: Seller contact
 * - BT-86: Payment service provider BIC
 * - BT-128: Item seller's identifier
 * - BT-134: Item country of origin
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
 */
final class FacturXEN16931XmlBuilder implements FacturXXmlBuilderInterface
{
    // UN/CEFACT CII Namespaces
    private const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    // EN16931 Profile URN
    private const PROFILE_URN = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:en16931';

    /**
     * @phpstan-ignore property.uninitialized (initialized in build())
     */
    private \DOMDocument $dom;

    /**
     * Line ID counter reset per build() call.
     */
    private int $lineId = 0;

    public function build(Invoice $invoice, CompanyData $companyData): string
    {
        // Reset line counter for each build
        $this->lineId = 0;

        // Create fresh DOMDocument
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
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

    public function getProfile(): FacturXProfile
    {
        return FacturXProfile::EN16931;
    }

    public function supports(FacturXProfile $profile): bool
    {
        return FacturXProfile::EN16931 === $profile;
    }

    private function buildDocumentContext(): \DOMElement
    {
        $context = $this->createElement('rsm:ExchangedDocumentContext');

        // Business process (optional but recommended)
        $businessProcess = $this->createElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $businessProcess->appendChild($this->createElement('ram:ID', 'A1'));
        $context->appendChild($businessProcess);

        // Profile identifier - EN16931
        $guideline = $this->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($this->createElement('ram:ID', self::PROFILE_URN));
        $context->appendChild($guideline);

        return $context;
    }

    private function buildExchangedDocument(Invoice $invoice): \DOMElement
    {
        $doc = $this->createElement('rsm:ExchangedDocument');

        // BT-1: Invoice number
        $doc->appendChild($this->createElement('ram:ID', $invoice->getNumber() ?? ''));

        // BT-3: Document type code (380 = Invoice, 381 = Credit Note)
        $typeCode = InvoiceType::CREDIT_NOTE === $invoice->getType() ? '381' : '380';
        $doc->appendChild($this->createElement('ram:TypeCode', $typeCode));

        // BT-2: Issue date (format 102 = YYYYMMDD)
        $issueDateTime = $this->createElement('ram:IssueDateTime');
        $dateString = $this->createElement('udt:DateTimeString', $invoice->getDate()->format('Ymd'));
        $dateString->setAttribute('format', '102');
        $issueDateTime->appendChild($dateString);
        $doc->appendChild($issueDateTime);

        // BT-22: Notes (if available via payment terms or other source)
        if ($invoice->getPaymentTerms()) {
            $note = $this->createElement('ram:IncludedNote');
            $note->appendChild($this->createElement('ram:Content', $invoice->getPaymentTerms()));
            $doc->appendChild($note);
        }

        return $doc;
    }

    private function buildSupplyChainTradeTransaction(Invoice $invoice, CompanyData $companyData): \DOMElement
    {
        $transaction = $this->createElement('rsm:SupplyChainTradeTransaction');

        // Line Items (must come first according to schema)
        foreach ($invoice->getLines() as $line) {
            $transaction->appendChild($this->buildLineItem($line));
        }

        // Trade Agreement (Seller + Buyer + References)
        $transaction->appendChild($this->buildHeaderTradeAgreement($invoice, $companyData));

        // Trade Delivery (delivery date and address)
        $transaction->appendChild($this->buildHeaderTradeDelivery($invoice));

        // Trade Settlement (Currency, VAT, Totals, Payment)
        $transaction->appendChild($this->buildHeaderTradeSettlement($invoice, $companyData));

        return $transaction;
    }

    private function buildHeaderTradeAgreement(Invoice $invoice, CompanyData $companyData): \DOMElement
    {
        $agreement = $this->createElement('ram:ApplicableHeaderTradeAgreement');

        // BT-10: Buyer reference (mandatory for B2B/B2G in France)
        if ($invoice->getBuyerReference()) {
            $agreement->appendChild($this->createElement('ram:BuyerReference', $invoice->getBuyerReference()));
        }

        // Seller party (BG-4)
        $agreement->appendChild($this->buildSellerTradeParty($companyData));

        // Buyer party (BG-7)
        $agreement->appendChild($this->buildBuyerTradeParty($invoice));

        // BT-13: Purchase order reference
        if ($invoice->getPurchaseOrderReference()) {
            $orderRef = $this->createElement('ram:BuyerOrderReferencedDocument');
            $orderRef->appendChild($this->createElement('ram:IssuerAssignedID', $invoice->getPurchaseOrderReference()));
            $agreement->appendChild($orderRef);
        }

        return $agreement;
    }

    private function buildSellerTradeParty(CompanyData $companyData): \DOMElement
    {
        $seller = $this->createElement('ram:SellerTradeParty');

        // BT-29: Seller identifier (SIRET for French companies)
        if ($companyData->siret) {
            $siretId = $this->createElement('ram:ID', $companyData->siret);
            $siretId->setAttribute('schemeID', '0002');
            $seller->appendChild($siretId);
        }

        // BT-27: Seller name
        $seller->appendChild($this->createElement('ram:Name', $companyData->name));

        // BG-6: Seller contact (EN16931 requirement)
        $contact = $this->createElement('ram:DefinedTradeContact');
        if ($companyData->phone) {
            $phone = $this->createElement('ram:TelephoneUniversalCommunication');
            $phone->appendChild($this->createElement('ram:CompleteNumber', $companyData->phone));
            $contact->appendChild($phone);
        }
        if ($companyData->email) {
            $email = $this->createElement('ram:EmailURIUniversalCommunication');
            $email->appendChild($this->createElement('ram:URIID', $companyData->email));
            $contact->appendChild($email);
        }
        if ($companyData->phone || $companyData->email) {
            $seller->appendChild($contact);
        }

        // BG-5: Seller postal address (structured for EN16931)
        $seller->appendChild($this->buildStructuredPostalAddress(
            $companyData->address,
            $companyData->city ?? null,
            $companyData->postalCode ?? null,
            $companyData->countryCode ?? 'FR',
        ));

        // BT-32: Seller VAT identifier
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

        // BT-46: Buyer identifier (SIRET)
        if ($invoice->getCustomerSiret()) {
            $siretId = $this->createElement('ram:ID', $invoice->getCustomerSiret());
            $siretId->setAttribute('schemeID', '0002');
            $buyer->appendChild($siretId);
        }

        // BT-44: Buyer name
        $buyer->appendChild($this->createElement('ram:Name', $invoice->getCustomerName()));

        // BG-9: Buyer contact (email if available)
        if ($invoice->getCustomerEmail()) {
            $contact = $this->createElement('ram:DefinedTradeContact');
            $email = $this->createElement('ram:EmailURIUniversalCommunication');
            $email->appendChild($this->createElement('ram:URIID', $invoice->getCustomerEmail()));
            $contact->appendChild($email);
            $buyer->appendChild($contact);
        }

        // BG-8: Buyer postal address (structured for EN16931)
        $buyer->appendChild($this->buildStructuredPostalAddress(
            $invoice->getCustomerAddress(),
            $invoice->getCustomerCity(),
            $invoice->getCustomerPostalCode(),
            $invoice->getCustomerCountryCode() ?? 'FR',
        ));

        // BT-48: Buyer VAT identifier
        if ($invoice->getCustomerVatNumber()) {
            $taxReg = $this->createElement('ram:SpecifiedTaxRegistration');
            $vatId = $this->createElement('ram:ID', $invoice->getCustomerVatNumber());
            $vatId->setAttribute('schemeID', 'VA');
            $taxReg->appendChild($vatId);
            $buyer->appendChild($taxReg);
        }

        return $buyer;
    }

    /**
     * Build structured postal address for EN16931.
     *
     * @param string      $addressLine Full address or street line
     * @param string|null $city        City name (BT-52/BT-77)
     * @param string|null $postalCode  Postal code (BT-53/BT-78)
     * @param string      $countryCode ISO 3166-1 alpha-2 country code (BT-55/BT-80)
     */
    private function buildStructuredPostalAddress(
        string $addressLine,
        ?string $city,
        ?string $postalCode,
        string $countryCode,
    ): \DOMElement {
        $address = $this->createElement('ram:PostalTradeAddress');

        // BT-53/BT-78: Postal code (should come before city in EN16931)
        if ($postalCode) {
            $address->appendChild($this->createElement('ram:PostcodeCode', $postalCode));
        }

        // BT-50/BT-75: Address line 1
        $address->appendChild($this->createElement('ram:LineOne', $addressLine));

        // BT-52/BT-77: City name
        if ($city) {
            $address->appendChild($this->createElement('ram:CityName', $city));
        }

        // BT-55/BT-80: Country code (mandatory)
        $address->appendChild($this->createElement('ram:CountryID', $countryCode));

        return $address;
    }

    private function buildHeaderTradeDelivery(Invoice $invoice): \DOMElement
    {
        $delivery = $this->createElement('ram:ApplicableHeaderTradeDelivery');

        // BG-15: Deliver to address (if specified)
        if ($invoice->getDeliveryAddressLine1()) {
            $shipTo = $this->createElement('ram:ShipToTradeParty');
            $shipTo->appendChild($this->buildStructuredPostalAddress(
                $invoice->getDeliveryAddressLine1(),
                $invoice->getDeliveryCity(),
                $invoice->getDeliveryPostalCode(),
                $invoice->getDeliveryCountryCode() ?? 'FR',
            ));
            $delivery->appendChild($shipTo);
        }

        // BT-72: Actual delivery date
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

        // BT-5: Invoice currency code
        $settlement->appendChild($this->createElement('ram:InvoiceCurrencyCode', 'EUR'));

        // BG-16: Payment instructions
        $settlement->appendChild($this->buildPaymentMeans($companyData));

        // BG-20: Document level allowances (global discount)
        if (!$invoice->getGlobalDiscountAmount()->isZero()) {
            $allowance = $this->createElement('ram:SpecifiedTradeAllowanceCharge');
            $allowance->appendChild($this->createElement('ram:ChargeIndicator'))->appendChild(
                $this->createElement('udt:Indicator', 'false')
            );
            $allowance->appendChild($this->createElement(
                'ram:ActualAmount',
                $invoice->getGlobalDiscountAmount()->toEuros()
            ));
            $allowance->appendChild($this->createElement('ram:Reason', 'Remise globale'));
            $settlement->appendChild($allowance);
        }

        // BG-23: VAT breakdown
        foreach ($this->calculateVatBreakdown($invoice) as $vatData) {
            $settlement->appendChild($this->buildApplicableTradeTax($vatData));
        }

        // BT-20: Payment terms
        $paymentTerms = $this->createElement('ram:SpecifiedTradePaymentTerms');

        // Add payment terms description if available
        if ($invoice->getPaymentTerms()) {
            $paymentTerms->appendChild($this->createElement('ram:Description', $invoice->getPaymentTerms()));
        }

        // BT-9: Due date
        $dueDateTime = $this->createElement('ram:DueDateDateTime');
        $dueDateString = $this->createElement('udt:DateTimeString', $invoice->getDueDate()->format('Ymd'));
        $dueDateString->setAttribute('format', '102');
        $dueDateTime->appendChild($dueDateString);
        $paymentTerms->appendChild($dueDateTime);
        $settlement->appendChild($paymentTerms);

        // BG-22: Monetary summation
        $settlement->appendChild($this->buildMonetarySummation($invoice));

        // BG-3: Invoice reference (for credit notes)
        if (InvoiceType::CREDIT_NOTE === $invoice->getType() && $invoice->getCreditedInvoice()) {
            $refDoc = $this->createElement('ram:InvoiceReferencedDocument');
            $refDoc->appendChild($this->createElement(
                'ram:IssuerAssignedID',
                $invoice->getCreditedInvoice()->getNumber() ?? ''
            ));
            $settlement->appendChild($refDoc);
        }

        // BT-19: Accounting reference (buyer accounting reference)
        if ($invoice->getAccountingReference()) {
            $accountingRef = $this->createElement('ram:ReceivableSpecifiedTradeAccountingAccount');
            $accountingRef->appendChild($this->createElement('ram:ID', $invoice->getAccountingReference()));
            $settlement->appendChild($accountingRef);
        }

        return $settlement;
    }

    private function buildPaymentMeans(CompanyData $companyData): \DOMElement
    {
        $paymentMeans = $this->createElement('ram:SpecifiedTradeSettlementPaymentMeans');

        // BT-81: Payment means type code (58 = SEPA Credit Transfer)
        $paymentMeans->appendChild($this->createElement('ram:TypeCode', '58'));

        // BG-17: Credit transfer
        if ($companyData->iban) {
            $creditorAccount = $this->createElement('ram:PayeePartyCreditorFinancialAccount');
            // BT-84: IBAN
            $creditorAccount->appendChild($this->createElement('ram:IBANID', $companyData->iban));
            $paymentMeans->appendChild($creditorAccount);

            // BT-86: BIC (Payment service provider identifier)
            if ($companyData->bic) {
                $financialInstitution = $this->createElement('ram:PayeeSpecifiedCreditorFinancialInstitution');
                $financialInstitution->appendChild($this->createElement('ram:BICID', $companyData->bic));
                $paymentMeans->appendChild($financialInstitution);
            }
        }

        return $paymentMeans;
    }

    /**
     * @param array{rate: string, basis: string, amount: string, categoryCode: string, exemptionReason: ?string} $vatData
     */
    private function buildApplicableTradeTax(array $vatData): \DOMElement
    {
        $tax = $this->createElement('ram:ApplicableTradeTax');

        // BT-117: Tax amount
        $tax->appendChild($this->createElement('ram:CalculatedAmount', $vatData['amount']));

        // BT-118: VAT type code
        $tax->appendChild($this->createElement('ram:TypeCode', 'VAT'));

        // BT-120: Exemption reason (for exempt categories)
        if ($vatData['exemptionReason']) {
            $tax->appendChild($this->createElement('ram:ExemptionReason', $vatData['exemptionReason']));
        }

        // BT-116: Tax base amount
        $tax->appendChild($this->createElement('ram:BasisAmount', $vatData['basis']));

        // BT-118: VAT category code
        $tax->appendChild($this->createElement('ram:CategoryCode', $vatData['categoryCode']));

        // BT-119: VAT rate
        $tax->appendChild($this->createElement('ram:RateApplicablePercent', $vatData['rate']));

        return $tax;
    }

    /**
     * Calculate VAT breakdown grouped by rate and category.
     *
     * @return array<int, array{rate: string, basis: string, amount: string, categoryCode: string, exemptionReason: ?string}>
     */
    private function calculateVatBreakdown(Invoice $invoice): array
    {
        $breakdown = [];

        foreach ($invoice->getLines() as $line) {
            $rate = number_format($line->getVatRate(), 2, '.', '');
            $categoryCode = $line->getTaxCategoryCode()->value;
            $key = $rate.'_'.$categoryCode;

            if (!isset($breakdown[$key])) {
                // Get exemption reason for this category
                $exemptionReason = $this->getExemptionReason($line->getTaxCategoryCode());

                $breakdown[$key] = [
                    'rate' => $rate,
                    'basis' => '0.00',
                    'amount' => '0.00',
                    'categoryCode' => $categoryCode,
                    'exemptionReason' => $exemptionReason,
                ];
            }

            $lineBasis = $line->getTotalBeforeVat();
            $lineVat = $line->getVatAmount();

            $breakdown[$key]['basis'] = number_format(
                (float) $breakdown[$key]['basis'] + ($lineBasis->getAmount() / 100.0),
                2,
                '.',
                '',
            );

            $breakdown[$key]['amount'] = number_format(
                (float) $breakdown[$key]['amount'] + ($lineVat->getAmount() / 100.0),
                2,
                '.',
                '',
            );
        }

        // Apply global discount to basis (proportionally)
        if (!$invoice->getGlobalDiscountAmount()->isZero()) {
            $discountAmount = $invoice->getGlobalDiscountAmount()->getAmount() / 100.0;
            $totalBasis = array_sum(array_map(fn ($v) => (float) $v['basis'], $breakdown));

            foreach ($breakdown as $key => $vatData) {
                $proportion = $totalBasis > 0 ? ((float) $vatData['basis']) / $totalBasis : 0;
                $rateDiscount = $discountAmount * $proportion;

                $newBasis = ((float) $vatData['basis']) - $rateDiscount;
                $newAmount = $newBasis * ((float) $vatData['rate']) / 100;

                $breakdown[$key]['basis'] = number_format($newBasis, 2, '.', '');
                $breakdown[$key]['amount'] = number_format($newAmount, 2, '.', '');
            }
        }

        return array_values($breakdown);
    }

    /**
     * Get VAT exemption reason text for EN16931.
     */
    private function getExemptionReason(TaxCategoryCode $category): ?string
    {
        return match ($category) {
            TaxCategoryCode::EXEMPT => 'Exonération de TVA',
            TaxCategoryCode::REVERSE_CHARGE => 'Autoliquidation - Article 283 du CGI',
            TaxCategoryCode::INTRA_EU => 'Livraison intracommunautaire exonérée - Article 262 ter du CGI',
            TaxCategoryCode::EXPORT => 'Exportation exonérée - Article 262 du CGI',
            TaxCategoryCode::NOT_SUBJECT => 'Opération non soumise à TVA',
            TaxCategoryCode::ZERO_RATE => 'TVA à taux zéro',
            default => null,
        };
    }

    private function buildMonetarySummation(Invoice $invoice): \DOMElement
    {
        $summation = $this->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');

        // BT-106: Sum of invoice line net amounts
        $summation->appendChild($this->createElement(
            'ram:LineTotalAmount',
            $invoice->getSubtotalBeforeDiscount()->toEuros(),
        ));

        // BT-107: Sum of allowances (global discount)
        if (!$invoice->getGlobalDiscountAmount()->isZero()) {
            $summation->appendChild($this->createElement(
                'ram:AllowanceTotalAmount',
                $invoice->getGlobalDiscountAmount()->toEuros(),
            ));
        }

        // BT-108: Sum of charges (none for now)
        $summation->appendChild($this->createElement('ram:ChargeTotalAmount', '0.00'));

        // BT-109: Invoice total without VAT
        $summation->appendChild($this->createElement(
            'ram:TaxBasisTotalAmount',
            $invoice->getSubtotalAfterDiscount()->toEuros(),
        ));

        // BT-110: Invoice total VAT
        $taxTotal = $this->createElement('ram:TaxTotalAmount', $invoice->getTotalVat()->toEuros());
        $taxTotal->setAttribute('currencyID', 'EUR');
        $summation->appendChild($taxTotal);

        // BT-112: Invoice total with VAT
        $summation->appendChild($this->createElement(
            'ram:GrandTotalAmount',
            $invoice->getTotalIncludingVat()->toEuros(),
        ));

        // BT-115: Amount due for payment
        $summation->appendChild($this->createElement(
            'ram:DuePayableAmount',
            $invoice->getTotalIncludingVat()->toEuros(),
        ));

        return $summation;
    }

    private function buildLineItem(InvoiceLine $line): \DOMElement
    {
        $item = $this->createElement('ram:IncludedSupplyChainTradeLineItem');

        // BT-126: Invoice line identifier
        ++$this->lineId;
        $doc = $this->createElement('ram:AssociatedDocumentLineDocument');
        $doc->appendChild($this->createElement('ram:LineID', (string) $this->lineId));
        $item->appendChild($doc);

        // BG-31: Item information
        $product = $this->createElement('ram:SpecifiedTradeProduct');

        // BT-128: Item Seller's identifier
        if ($line->getItemIdentifier()) {
            $product->appendChild($this->createElement('ram:SellerAssignedID', $line->getItemIdentifier()));
        }

        // BT-153: Item name
        $product->appendChild($this->createElement('ram:Name', $line->getDescription()));

        // BT-134: Item country of origin
        if ($line->getCountryOfOrigin()) {
            $origin = $this->createElement('ram:OriginTradeCountry');
            $origin->appendChild($this->createElement('ram:ID', $line->getCountryOfOrigin()));
            $product->appendChild($origin);
        }

        $item->appendChild($product);

        // BG-29: Line Agreement (price details)
        $agreement = $this->createElement('ram:SpecifiedLineTradeAgreement');

        // BT-146: Item net price
        $netPrice = $this->createElement('ram:NetPriceProductTradePrice');
        $netPrice->appendChild($this->createElement('ram:ChargeAmount', $line->getUnitPrice()->toEuros()));
        $agreement->appendChild($netPrice);

        $item->appendChild($agreement);

        // BG-30: Line Delivery (quantity)
        $delivery = $this->createElement('ram:SpecifiedLineTradeDelivery');

        // BT-129: Invoiced quantity + BT-130: Unit code
        $quantity = $this->createElement('ram:BilledQuantity', number_format($line->getQuantity(), 4, '.', ''));
        $quantity->setAttribute('unitCode', $line->getQuantityUnit()->value);
        $delivery->appendChild($quantity);

        $item->appendChild($delivery);

        // BG-30: Line Settlement (VAT and total)
        $settlement = $this->createElement('ram:SpecifiedLineTradeSettlement');

        // BG-30: Line VAT information
        $tax = $this->createElement('ram:ApplicableTradeTax');
        $tax->appendChild($this->createElement('ram:TypeCode', 'VAT'));
        // BT-151: VAT category code
        $tax->appendChild($this->createElement('ram:CategoryCode', $line->getTaxCategoryCode()->value));
        // BT-152: VAT rate
        $tax->appendChild($this->createElement(
            'ram:RateApplicablePercent',
            number_format($line->getVatRate(), 2, '.', '')
        ));
        $settlement->appendChild($tax);

        // BT-131: Invoice line net amount
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
