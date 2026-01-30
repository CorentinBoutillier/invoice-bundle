<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Entity;

use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceStatus;
use CorentinBoutillier\InvoiceBundle\Enum\InvoiceType;
use CorentinBoutillier\InvoiceBundle\Enum\OperationCategory;
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: InvoiceType::class)]
    private InvoiceType $type;

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\Column(type: 'string', length: 50, nullable: true, unique: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $companyId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fiscalYear = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $paymentTerms = null;

    // ========== Global Discount ==========

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $globalDiscountRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $globalDiscountAmountCents = null;

    // ========== Customer Snapshot (NO Relations) ==========

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerName;

    #[ORM\Column(type: 'text')]
    private string $customerAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $customerSiret = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $customerVatNumber = null;

    // ========== Structured Customer Address (BG-8) ==========

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $customerCity = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $customerPostalCode = null;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $customerCountryCode = null;

    // ========== EN16931 - References ==========

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $buyerReference = null;  // BT-10: Buyer reference

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $purchaseOrderReference = null;  // BT-13: Purchase order reference

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $accountingReference = null;  // BT-19: Accounting cost code

    // ========== Operation Category (French 2026) ==========

    #[ORM\Column(type: 'string', length: 10, nullable: true, enumType: OperationCategory::class)]
    private ?OperationCategory $operationCategory = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $vatOnDebits = null;  // TVA sur les débits

    // ========== Delivery Address (BG-15) ==========

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deliveryAddressLine1 = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $deliveryCity = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $deliveryPostalCode = null;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $deliveryCountryCode = null;

    // ========== Company Snapshot (NO Relations) ==========

    #[ORM\Column(type: 'string', length: 255)]
    private string $companyName;

    #[ORM\Column(type: 'text')]
    private string $companyAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $companyEmail = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $companyPhone = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $companySiret = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $companyVatNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $companyBankName = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $companyIban = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $companyBic = null;

    // ========== Collections ==========

    /**
     * @var Collection<int, InvoiceLine>
     */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    // ========== Credit Note Specific ==========

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $creditedInvoice = null;

    // ========== PDF Generation ==========

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pdfGeneratedAt = null;

    // ========== Timestamps ==========

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        InvoiceType $type,
        \DateTimeImmutable $date,
        \DateTimeImmutable $dueDate,
        string $customerName,
        string $customerAddress,
        string $companyName,
        string $companyAddress,
    ) {
        $this->type = $type;
        $this->date = $date;
        $this->dueDate = $dueDate;
        $this->customerName = $customerName;
        $this->customerAddress = $customerAddress;
        $this->companyName = $companyName;
        $this->companyAddress = $companyAddress;

        $this->lines = new ArrayCollection();
        $this->payments = new ArrayCollection();

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): void
    {
        $this->status = $status;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getFiscalYear(): ?int
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(?int $fiscalYear): void
    {
        $this->fiscalYear = $fiscalYear;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): void
    {
        $this->paymentTerms = $paymentTerms;
    }

    // ========== Global Discount Getters/Setters ==========

    public function getGlobalDiscountRate(): ?float
    {
        return $this->globalDiscountRate;
    }

    public function setGlobalDiscountRate(?float $globalDiscountRate): void
    {
        $this->globalDiscountRate = $globalDiscountRate;
    }

    public function getGlobalDiscountAmount(): Money
    {
        // Priorité 1: Montant fixe
        if (null !== $this->globalDiscountAmountCents) {
            return Money::fromCents($this->globalDiscountAmountCents);
        }

        // Priorité 2: Pourcentage
        if (null !== $this->globalDiscountRate) {
            $subtotal = $this->getSubtotalBeforeDiscount();
            $discountMultiplier = $this->globalDiscountRate / 100;

            return $subtotal->multiply($discountMultiplier);
        }

        // Pas de remise
        return Money::zero();
    }

    public function setGlobalDiscountAmount(?Money $money): void
    {
        $this->globalDiscountAmountCents = null === $money ? null : $money->getAmount();
    }

    // ========== Customer Snapshot Getters/Setters ==========

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerAddress(): string
    {
        return $this->customerAddress;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): void
    {
        $this->customerPhone = $customerPhone;
    }

    public function getCustomerSiret(): ?string
    {
        return $this->customerSiret;
    }

    public function setCustomerSiret(?string $customerSiret): void
    {
        $this->customerSiret = $customerSiret;
    }

    public function getCustomerVatNumber(): ?string
    {
        return $this->customerVatNumber;
    }

    public function setCustomerVatNumber(?string $customerVatNumber): void
    {
        $this->customerVatNumber = $customerVatNumber;
    }

    // ========== Structured Customer Address (BG-8) Getters/Setters ==========

    public function getCustomerCity(): ?string
    {
        return $this->customerCity;
    }

    public function setCustomerCity(?string $customerCity): void
    {
        $this->customerCity = $customerCity;
    }

    public function getCustomerPostalCode(): ?string
    {
        return $this->customerPostalCode;
    }

    public function setCustomerPostalCode(?string $customerPostalCode): void
    {
        $this->customerPostalCode = $customerPostalCode;
    }

    public function getCustomerCountryCode(): ?string
    {
        return $this->customerCountryCode;
    }

    public function setCustomerCountryCode(?string $customerCountryCode): void
    {
        $this->customerCountryCode = $customerCountryCode;
    }

    // ========== EN16931 References Getters/Setters ==========

    public function getBuyerReference(): ?string
    {
        return $this->buyerReference;
    }

    public function setBuyerReference(?string $buyerReference): void
    {
        $this->buyerReference = $buyerReference;
    }

    public function getPurchaseOrderReference(): ?string
    {
        return $this->purchaseOrderReference;
    }

    public function setPurchaseOrderReference(?string $purchaseOrderReference): void
    {
        $this->purchaseOrderReference = $purchaseOrderReference;
    }

    public function getAccountingReference(): ?string
    {
        return $this->accountingReference;
    }

    public function setAccountingReference(?string $accountingReference): void
    {
        $this->accountingReference = $accountingReference;
    }

    // ========== Operation Category Getters/Setters ==========

    public function getOperationCategory(): ?OperationCategory
    {
        return $this->operationCategory;
    }

    public function setOperationCategory(?OperationCategory $operationCategory): void
    {
        $this->operationCategory = $operationCategory;
    }

    public function getVatOnDebits(): ?bool
    {
        return $this->vatOnDebits;
    }

    public function setVatOnDebits(?bool $vatOnDebits): void
    {
        $this->vatOnDebits = $vatOnDebits;
    }

    // ========== Delivery Address (BG-15) Getters/Setters ==========

    public function getDeliveryAddressLine1(): ?string
    {
        return $this->deliveryAddressLine1;
    }

    public function setDeliveryAddressLine1(?string $deliveryAddressLine1): void
    {
        $this->deliveryAddressLine1 = $deliveryAddressLine1;
    }

    public function getDeliveryCity(): ?string
    {
        return $this->deliveryCity;
    }

    public function setDeliveryCity(?string $deliveryCity): void
    {
        $this->deliveryCity = $deliveryCity;
    }

    public function getDeliveryPostalCode(): ?string
    {
        return $this->deliveryPostalCode;
    }

    public function setDeliveryPostalCode(?string $deliveryPostalCode): void
    {
        $this->deliveryPostalCode = $deliveryPostalCode;
    }

    public function getDeliveryCountryCode(): ?string
    {
        return $this->deliveryCountryCode;
    }

    public function setDeliveryCountryCode(?string $deliveryCountryCode): void
    {
        $this->deliveryCountryCode = $deliveryCountryCode;
    }

    /**
     * Check if a delivery address is defined.
     */
    public function hasDeliveryAddress(): bool
    {
        return null !== $this->deliveryAddressLine1
            || null !== $this->deliveryCity
            || null !== $this->deliveryPostalCode;
    }

    // ========== Company Snapshot Getters/Setters ==========

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getCompanyAddress(): string
    {
        return $this->companyAddress;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(?string $companyEmail): void
    {
        $this->companyEmail = $companyEmail;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): void
    {
        $this->companyPhone = $companyPhone;
    }

    public function getCompanySiret(): ?string
    {
        return $this->companySiret;
    }

    public function setCompanySiret(?string $companySiret): void
    {
        $this->companySiret = $companySiret;
    }

    public function getCompanyVatNumber(): ?string
    {
        return $this->companyVatNumber;
    }

    public function setCompanyVatNumber(?string $companyVatNumber): void
    {
        $this->companyVatNumber = $companyVatNumber;
    }

    public function getCompanyBankName(): ?string
    {
        return $this->companyBankName;
    }

    public function setCompanyBankName(?string $companyBankName): void
    {
        $this->companyBankName = $companyBankName;
    }

    public function getCompanyIban(): ?string
    {
        return $this->companyIban;
    }

    public function setCompanyIban(?string $companyIban): void
    {
        $this->companyIban = $companyIban;
    }

    public function getCompanyBic(): ?string
    {
        return $this->companyBic;
    }

    public function setCompanyBic(?string $companyBic): void
    {
        $this->companyBic = $companyBic;
    }

    // ========== Lines Management ==========

    /**
     * @return array<int, InvoiceLine>
     */
    public function getLines(): array
    {
        return array_values($this->lines->toArray());
    }

    public function addLine(InvoiceLine $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }
    }

    // ========== Payments Management ==========

    /**
     * @return array<int, Payment>
     */
    public function getPayments(): array
    {
        return array_values($this->payments->toArray());
    }

    public function addPayment(Payment $payment): void
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
        }
    }

    // ========== Calculations (Simple - no global discount) ==========

    public function getSubtotalBeforeDiscount(): Money
    {
        $total = Money::zero();

        foreach ($this->lines as $line) {
            $total = $total->add($line->getTotalBeforeVat());
        }

        return $total;
    }

    public function getTotalVat(): Money
    {
        $globalDiscount = $this->getGlobalDiscountAmount();

        // Si pas de remise globale, calcul simple
        if ($globalDiscount->isZero()) {
            $total = Money::zero();

            foreach ($this->lines as $line) {
                $total = $total->add($line->getVatAmount());
            }

            return $total;
        }

        // Avec remise globale : distribution proportionnelle
        $subtotal = $this->getSubtotalBeforeDiscount();
        if ($subtotal->isZero()) {
            return Money::zero();
        }

        $total = Money::zero();

        foreach ($this->lines as $line) {
            $lineTotal = $line->getTotalBeforeVat();

            // Proportion de cette ligne dans le sous-total
            $proportion = $lineTotal->getAmount() / $subtotal->getAmount();

            // Remise globale appliquée à cette ligne
            $lineDiscount = $globalDiscount->multiply($proportion);

            // Montant HT après remise
            $lineAfterDiscount = $lineTotal->subtract($lineDiscount);

            // TVA sur le montant réduit
            $vatMultiplier = $line->getVatRate() / 100;
            $lineVat = $lineAfterDiscount->multiply($vatMultiplier);

            $total = $total->add($lineVat);
        }

        return $total;
    }

    public function getSubtotalAfterDiscount(): Money
    {
        $subtotal = $this->getSubtotalBeforeDiscount();
        $discount = $this->getGlobalDiscountAmount();

        return $subtotal->subtract($discount);
    }

    public function getTotalIncludingVat(): Money
    {
        return $this->getSubtotalAfterDiscount()->add($this->getTotalVat());
    }

    // ========== Payment Tracking ==========

    public function getTotalPaid(): Money
    {
        $total = Money::zero();

        foreach ($this->payments as $payment) {
            $total = $total->add($payment->getAmount());
        }

        return $total;
    }

    public function getRemainingAmount(): Money
    {
        $total = $this->getTotalIncludingVat();
        $paid = $this->getTotalPaid();

        return $total->subtract($paid);
    }

    public function isFullyPaid(): bool
    {
        $remaining = $this->getRemainingAmount();

        return $remaining->getAmount() <= 0;
    }

    public function isPartiallyPaid(): bool
    {
        $paid = $this->getTotalPaid();
        $remaining = $this->getRemainingAmount();

        // Partiellement payé = paiement > 0 ET reste > 0
        return !$paid->isZero() && $remaining->getAmount() > 0;
    }

    // ========== Due Date & Overdue ==========

    public function isOverdue(?\DateTimeImmutable $referenceDate = null): bool
    {
        $referenceDate = $referenceDate ?? new \DateTimeImmutable();

        // En retard si : date passée ET pas totalement payée
        return $referenceDate > $this->dueDate && !$this->isFullyPaid();
    }

    public function getDaysOverdue(?\DateTimeImmutable $referenceDate = null): int
    {
        $referenceDate = $referenceDate ?? new \DateTimeImmutable();

        // Pas en retard si totalement payée
        if ($this->isFullyPaid()) {
            return 0;
        }

        // Pas encore échue
        if ($referenceDate <= $this->dueDate) {
            return 0;
        }

        // Calcul du nombre de jours
        $interval = $this->dueDate->diff($referenceDate);

        return (int) $interval->days;
    }

    // ========== Credit Note ==========

    public function getCreditedInvoice(): ?self
    {
        return $this->creditedInvoice;
    }

    public function setCreditedInvoice(?self $creditedInvoice): void
    {
        $this->creditedInvoice = $creditedInvoice;
    }

    // ========== PDF Generation ==========

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): void
    {
        $this->pdfPath = $pdfPath;
    }

    public function getPdfGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->pdfGeneratedAt;
    }

    public function setPdfGeneratedAt(?\DateTimeImmutable $pdfGeneratedAt): void
    {
        $this->pdfGeneratedAt = $pdfGeneratedAt;
    }

    public function hasPdf(): bool
    {
        return null !== $this->pdfPath;
    }

    // ========== Timestamps ==========

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
