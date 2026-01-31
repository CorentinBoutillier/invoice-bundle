# Guide d'utilisation - Invoice Bundle

Ce guide présente des exemples concrets d'utilisation du bundle pour les cas d'usage courants et avancés.

## 1. Workflow de base : Créer, Finaliser, Payer

```php
use CorentinBoutillier\InvoiceBundle\DTO\CustomerData;
use CorentinBoutillier\InvoiceBundle\DTO\Money;
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Enum\PaymentMethod;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceFinalizerInterface;
use CorentinBoutillier\InvoiceBundle\Service\InvoiceManagerInterface;
use CorentinBoutillier\InvoiceBundle\Service\PaymentManagerInterface;

// 1. Récupérer les services depuis le container
$invoiceManager = $container->get(InvoiceManagerInterface::class);
$invoiceFinalizer = $container->get(InvoiceFinalizerInterface::class);
$paymentManager = $container->get(PaymentManagerInterface::class);

// 2. Créer une facture (statut DRAFT)
$customerData = new CustomerData(
    name: 'ACME Corporation',
    address: '456 Customer Avenue, 75002 Paris, France',
    email: 'contact@acme.fr',
    phone: '01 98 76 54 32',
    vatNumber: 'FR98765432109',
);

$invoice = $invoiceManager->createInvoice(
    customerData: $customerData,
    date: new \DateTimeImmutable('2025-01-15'),
    paymentTerms: '30 jours net',
);

// 3. Ajouter des lignes avec Money
$unitPrice = Money::fromEuros('150.00');
$quantity = 3;
$vatRate = 20.0;

$line = new InvoiceLine(
    description: 'Consulting Services - Développement Symfony',
    quantity: $quantity,
    unitPrice: $unitPrice,
    vatRate: $vatRate,
);

$invoiceManager->addLine($invoice, $line);

// 4. Finaliser (numéro + PDF + Factur-X si activé)
$invoiceFinalizer->finalize($invoice);

// Résultat :
// - $invoice->getNumber() = "FA-2025-0001"
// - $invoice->getStatus() = InvoiceStatus::FINALIZED
// - PDF stocké : var/invoices/2025/01/FA-2025-0001.pdf

// 5. Enregistrer un paiement
$payment = $paymentManager->recordPayment(
    invoice: $invoice,
    amount: Money::fromEuros('540.00'), // 150€ × 3 × 1.20 (TVA)
    method: PaymentMethod::BANK_TRANSFER,
    date: new \DateTimeImmutable(),
    reference: 'VIR-2025-001',
);

// Le statut passe automatiquement à PAID
// Event InvoicePaidEvent dispatché
```

## 2. Scénarios avancés

### 2.1 Facture multi-TVA

```php
$invoice = $invoiceManager->createInvoice($customerData, $date, $paymentTerms);

// Taux normal (20%)
$invoice->addLine(new InvoiceLine(
    description: 'Produit A - Taux normal',
    quantity: 2,
    unitPrice: Money::fromEuros('100.00'),
    vatRate: 20.0,
));

// Taux réduit (5.5%)
$invoice->addLine(new InvoiceLine(
    description: 'Livre - Taux réduit',
    quantity: 1,
    unitPrice: Money::fromEuros('50.00'),
    vatRate: 5.5,
));

// Taux intermédiaire (10%)
$invoice->addLine(new InvoiceLine(
    description: 'Transport - Taux intermédiaire',
    quantity: 1,
    unitPrice: Money::fromEuros('25.00'),
    vatRate: 10.0,
));

$invoiceFinalizer->finalize($invoice);

// Récupérer les totaux par taux de TVA
$totalsByVat = $invoice->getTotalsByVatRate();
// Retourne : ['20.0' => Money, '5.5' => Money, '10.0' => Money]

foreach ($totalsByVat as $rate => $amount) {
    echo "TVA {$rate}% : {$amount->format('fr_FR')}\n";
}
```

### 2.2 Remise globale

```php
$invoice = $invoiceManager->createInvoice($customerData, $date, $paymentTerms);

$invoice->addLine(new InvoiceLine(
    description: 'Service premium',
    quantity: 1,
    unitPrice: Money::fromEuros('1000.00'),
    vatRate: 20.0,
));

// Appliquer une remise globale de 10% (avant TVA)
$invoice->applyGlobalDiscount(discountRate: 10.0);

// Ou une remise en montant fixe
$invoice->applyGlobalDiscount(discountAmount: Money::fromEuros('100.00'));

// Calculs :
// Sous-total HT : 1000€
// Remise 10% : -100€
// Total HT après remise : 900€
// TVA 20% : 180€
// Total TTC : 1080€

echo $invoice->getSubtotalBeforeDiscount();  // 1000.00€
echo $invoice->getGlobalDiscountAmount();    // 100.00€
echo $invoice->getSubtotalAfterDiscount();   // 900.00€
echo $invoice->getTotalVat();                // 180.00€
echo $invoice->getTotalIncludingVat();       // 1080.00€
```

### 2.3 Avoir (Credit Note) pour retour partiel

```php
// Créer un avoir lié à une facture originale
$creditNote = $invoiceManager->createCreditNote(
    originalInvoice: $invoice,
    customerData: $customerData,
    date: new \DateTimeImmutable(),
    paymentTerms: '30 jours net',
);

// Ajouter la ligne de retour
$creditNote->addLine(new InvoiceLine(
    description: 'Retour produit défectueux - Ref FA-2025-0001',
    quantity: 1,
    unitPrice: Money::fromEuros('150.00'),
    vatRate: 20.0,
));

$invoiceFinalizer->finalize($creditNote);

// Résultat :
// - Numéro : AV-2025-0001 (séquence séparée)
// - Type : InvoiceType::CREDIT_NOTE
// - creditedInvoice -> pointe vers la facture originale
```

### 2.4 Paiement partiel

```php
$invoice = $invoiceManager->createInvoice($customerData, $date, $paymentTerms);
$invoice->addLine(new InvoiceLine(
    description: 'Projet au long cours',
    quantity: 1,
    unitPrice: Money::fromEuros('10000.00'),
    vatRate: 20.0,
));

$invoiceFinalizer->finalize($invoice);
// Total TTC : 12 000€

// Premier acompte (50%)
$payment1 = $paymentManager->recordPayment(
    invoice: $invoice,
    amount: Money::fromEuros('6000.00'),
    method: PaymentMethod::BANK_TRANSFER,
    date: new \DateTimeImmutable(),
    notes: 'Acompte 50%',
);
// Statut : PARTIALLY_PAID
// Event : InvoicePartiallyPaidEvent

// Solde (50%)
$payment2 = $paymentManager->recordPayment(
    invoice: $invoice,
    amount: Money::fromEuros('6000.00'),
    method: PaymentMethod::BANK_TRANSFER,
    date: new \DateTimeImmutable('+ 30 days'),
    notes: 'Solde',
);
// Statut : PAID
// Event : InvoicePaidEvent

// Vérifications
echo $invoice->getTotalPaid();        // 12000.00€
echo $invoice->getRemainingAmount();  // 0.00€
var_dump($invoice->isFullyPaid());    // true
```

## 3. Pattern Provider pour multi-société

Pour gérer plusieurs sociétés avec leurs données en base de données :

```php
namespace App\Provider;

use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\DTO\CompanyData;
use App\Repository\CompanyRepository;

class DatabaseCompanyProvider implements CompanyProviderInterface
{
    public function __construct(
        private readonly CompanyRepository $repository,
    ) {}

    public function getCompanyData(?int $companyId = null): CompanyData
    {
        if (null === $companyId) {
            throw new \InvalidArgumentException('Company ID required for multi-company setup');
        }

        $company = $this->repository->find($companyId);

        if (!$company) {
            throw new \RuntimeException("Company #{$companyId} not found");
        }

        return new CompanyData(
            name: $company->getName(),
            address: $company->getFullAddress(),
            siret: $company->getSiret(),
            vatNumber: $company->getVatNumber(),
            email: $company->getEmail(),
            phone: $company->getPhone(),
            bankName: $company->getBankName(),
            iban: $company->getIban(),
            bic: $company->getBic(),
        );
    }
}
```

Enregistrement dans `config/services.yaml` :

```yaml
services:
    App\Provider\DatabaseCompanyProvider:
        tags: ['invoice.company_provider']
```

Utilisation :

```php
// Le bundle injecte automatiquement votre provider
$invoiceFinalizer->finalize($invoice);
// CompanyData récupéré via DatabaseCompanyProvider::getCompanyData()
```

## 4. Points d'extension via Events

Le bundle dispatche des événements pour tous les changements d'état. Vous pouvez créer des subscribers pour ajouter votre logique métier.

### 4.1 Notification email automatique

```php
namespace App\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InvoiceEmailNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceFinalizedEvent::class => 'onInvoiceFinalized',
            InvoicePaidEvent::class => 'onInvoicePaid',
        ];
    }

    public function onInvoiceFinalized(InvoiceFinalizedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $customerEmail = $invoice->getCustomerEmail();

        if (!$customerEmail) {
            return; // Pas d'email client
        }

        $email = (new Email())
            ->from('facturation@acme.fr')
            ->to($customerEmail)
            ->subject("Votre facture {$invoice->getNumber()}")
            ->html("
                <p>Bonjour,</p>
                <p>Veuillez trouver ci-joint votre facture n°{$invoice->getNumber()}.</p>
                <p>Montant TTC : {$invoice->getTotalIncludingVat()->format('fr_FR')}</p>
                <p>Cordialement,<br>L'équipe ACME</p>
            ")
            ->attachFromPath($invoice->getPdfPath());

        $this->mailer->send($email);
    }

    public function onInvoicePaid(InvoicePaidEvent $event): void
    {
        $invoice = $event->getInvoice();

        // Envoyer confirmation de paiement
        // Mettre à jour le système comptable
        // etc.
    }
}
```

### 4.2 Synchronisation comptable

```php
namespace App\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use App\Service\AccountingSystemInterface;

class InvoiceAccountingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccountingSystemInterface $accountingSystem,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceFinalizedEvent::class => 'syncToAccounting',
            InvoicePaidEvent::class => 'recordPaymentInAccounting',
        ];
    }

    public function syncToAccounting(InvoiceFinalizedEvent $event): void
    {
        $invoice = $event->getInvoice();

        // Créer l'écriture comptable (411 client / 707 ventes / 445710 TVA)
        $this->accountingSystem->createInvoiceEntry(
            invoiceNumber: $invoice->getNumber(),
            customerAccount: '411000',
            salesAccount: '707000',
            vatAccount: '445710',
            amountHT: $invoice->getSubtotalAfterDiscount(),
            amountVAT: $invoice->getTotalVat(),
            amountTTC: $invoice->getTotalIncludingVat(),
        );
    }

    public function recordPaymentInAccounting(InvoicePaidEvent $event): void
    {
        $invoice = $event->getInvoice();
        $payment = $event->getPayment();

        // Créer l'écriture de règlement (512 banque / 411 client)
        $this->accountingSystem->createPaymentEntry(
            invoiceNumber: $invoice->getNumber(),
            bankAccount: '512000',
            customerAccount: '411000',
            amount: $payment->getAmount(),
            date: $payment->getDate(),
        );
    }
}
```

## 5. Personnalisation du template PDF

Le bundle utilise Twig pour générer les PDF. Vous pouvez surcharger le template par héritage.

### Créer votre template personnalisé

Créez `templates/bundles/InvoiceBundle/invoice/pdf.html.twig` :

```twig
{% extends "@Invoice/invoice/pdf.html.twig" %}

{# Ajouter un logo #}
{% block company_logo %}
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="{{ asset('images/company-logo.png') }}" alt="Logo" style="max-width: 200px;" />
    </div>
{% endblock %}

{# Personnaliser l'en-tête #}
{% block header %}
    <div style="background: #003366; color: white; padding: 20px;">
        <h1>{{ invoice.type.value == 'INVOICE' ? 'FACTURE' : 'AVOIR' }}</h1>
        <p>N° {{ invoice.number }}</p>
    </div>
{% endblock %}

{# Ajouter un pied de page personnalisé #}
{% block footer %}
    <footer style="margin-top: 50px; padding-top: 20px; border-top: 2px solid #003366;">
        <p><strong>Mentions légales :</strong></p>
        <p>{{ company.name }} - {{ company.siret }} - TVA : {{ company.vatNumber }}</p>
        <p>{{ company.address }}</p>
        <p><strong>Coordonnées bancaires :</strong> {{ company.bankName }} - IBAN : {{ company.iban }}</p>
        <p style="font-size: 10px;">
            En cas de retard de paiement, une pénalité de 3 fois le taux d'intérêt légal sera appliquée.
            Une indemnité forfaitaire de 40€ pour frais de recouvrement sera également due.
        </p>
    </footer>
{% endblock %}
```

Le PDF sera automatiquement généré avec votre template lors de l'appel à `finalize()`.

## 6. Export FEC (Fichier des Écritures Comptables)

Le bundle supporte l'export FEC conforme à la réglementation française.

### Caractéristiques

- **18 colonnes** conformes au format légal
- **Séparateur pipe** (`|`)
- **Format français** : virgule comme séparateur décimal (`1200,00`)
- **Lettrage automatique** : rapprochement factures/paiements (colonnes EcritureLet, DateLet)
- **Journal Banque** : écritures de règlement automatiques (compte 512000)
- **Multi-TVA** : une ligne par taux de TVA distinct
- **EcritureNum unique** : même numéro pour toutes les lignes d'une écriture

```php
use CorentinBoutillier\InvoiceBundle\Service\Fec\FecExporterInterface;

$fecExporter = $container->get(FecExporterInterface::class);

// Export pour une période donnée (exercice fiscal)
$startDate = new \DateTimeImmutable('2025-01-01');
$endDate = new \DateTimeImmutable('2025-12-31');

$csvContent = $fecExporter->export($startDate, $endDate);

// Sauvegarder le fichier avec le nom légal
// Format : SIREN + "FEC" + date de clôture
file_put_contents('123456789FEC20251231.txt', $csvContent);
```

### Structure des écritures

Pour chaque facture, le FEC génère :
1. **Écriture de vente (journal VT)** :
   - Ligne client (411000) - débit TTC
   - Ligne ventes (707000) - crédit HT
   - Ligne(s) TVA (445710, etc.) - crédit TVA par taux

2. **Écriture de règlement (journal BQ)** si la facture a des paiements :
   - Ligne banque (512000) - débit
   - Ligne client (411000) - crédit avec lettrage

### Lettrage

Le lettrage lie automatiquement les écritures de facturation et de paiement :
- Code alphabétique (A, B, C... AA, AB...)
- Date de lettrage = date du dernier paiement
- Permet de vérifier que toutes les créances sont soldées

### Via commande CLI

```bash
# Export vers un fichier
php bin/console invoice:export-fec 2025 --output=123456789FEC20251231.txt

# Export vers stdout
php bin/console invoice:export-fec 2025

# Export pour une société spécifique (multi-company)
php bin/console invoice:export-fec 2025 --company-id=42
```

## 7. PDP - Plateforme de Dématérialisation Partenaire

Le bundle fournit une architecture extensible pour transmettre les factures vers les PDP.

**Important** : Seul le `NullConnector` (simulation) est fourni. Vous devez implémenter votre propre connecteur pour Chorus Pro, Pennylane, ou autre PDP.

### Architecture

```
┌─────────────────┐     ┌──────────────┐     ┌─────────────────┐
│ InvoiceFinalizer│────▶│ PdpDispatcher│────▶│ VotreConnector  │
│                 │     │              │     │ (à implémenter) │
└─────────────────┘     └──────────────┘     └─────────────────┘
                               │
                               ▼
                        ┌──────────────┐
                        │ NullConnector│ (défaut - simulation/tests)
                        └──────────────┘
```

### Transmission manuelle

```php
use CorentinBoutillier\InvoiceBundle\Pdp\PdpDispatcherInterface;

$pdpDispatcher = $container->get(PdpDispatcherInterface::class);

// Transmettre une facture finalisée (utilise le connecteur par défaut)
$result = $pdpDispatcher->transmit(
    invoice: $invoice,
    pdfContent: $pdfContent,
    xmlContent: $xmlContent,
);

// Ou avec un connecteur spécifique
$result = $pdpDispatcher->transmit(
    invoice: $invoice,
    connectorId: 'mon_connecteur',
    pdfContent: $pdfContent,
);

if ($result->success) {
    echo "Transmission réussie : " . $result->transmissionId;
} else {
    echo "Erreur : " . $result->message;
}
```

### Transmission automatique à la finalisation

```yaml
# config/packages/invoice.yaml
invoice:
    pdp:
        enabled: true
        default_connector: "null"           # Votre connecteur une fois implémenté
        auto_send_on_finalize: false        # Activer une fois le connecteur prêt
```

### Suivi des transmissions

L'entité `InvoiceTransmission` enregistre toutes les transmissions :

```php
use CorentinBoutillier\InvoiceBundle\Repository\InvoiceTransmissionRepository;

// Récupérer l'historique des transmissions via le repository
$transmissionRepository = $container->get(InvoiceTransmissionRepository::class);
$transmissions = $transmissionRepository->findByInvoice($invoice);

foreach ($transmissions as $transmission) {
    echo $transmission->getTransmissionId();   // ID PDP
    echo $transmission->getStatus()->value;    // pending, submitted, accepted, rejected...
    echo $transmission->getConnectorId();      // votre_connecteur
    echo $transmission->getCreatedAt()->format('Y-m-d H:i:s');
}
```

### Événements

```php
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmittedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceTransmissionFailedEvent;

class PdpNotificationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceTransmittedEvent::class => 'onTransmitted',
            InvoiceTransmissionFailedEvent::class => 'onFailed',
        ];
    }

    public function onTransmitted(InvoiceTransmittedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $result = $event->getResult();
        // Notification de succès
    }

    public function onFailed(InvoiceTransmissionFailedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $errorMessage = $event->getErrorMessage();
        // Alerte d'échec
    }
}
```

### Implémenter un connecteur personnalisé

```php
use CorentinBoutillier\InvoiceBundle\Pdp\PdpConnectorInterface;
use CorentinBoutillier\InvoiceBundle\Pdp\PdpCapability;
use CorentinBoutillier\InvoiceBundle\Pdp\Dto\TransmissionResult;

class ChorusProConnector implements PdpConnectorInterface
{
    public function getId(): string
    {
        return 'chorus_pro';
    }

    public function getName(): string
    {
        return 'Chorus Pro';
    }

    public function getCapabilities(): array
    {
        return [
            PdpCapability::TRANSMIT,
            PdpCapability::STATUS,
            PdpCapability::RECEIVE,
        ];
    }

    public function transmit(
        Invoice $invoice,
        ?string $pdfContent = null,
        ?string $xmlContent = null,
    ): TransmissionResult {
        // Appeler l'API Chorus Pro
        $response = $this->client->deposerFlux($invoice, $pdfContent);

        return new TransmissionResult(
            success: true,
            transmissionId: $response->getIdFlux(),
            message: 'Facture déposée avec succès',
        );
    }

    // ... autres méthodes
}
```

## 8. E-Reporting

Le bundle prépare les données pour la déclaration e-reporting à l'administration fiscale.

### Créer une transaction e-reporting

```php
use CorentinBoutillier\InvoiceBundle\EReporting\EReportingServiceInterface;

$eReportingService = $container->get(EReportingServiceInterface::class);

// Créer une transaction à partir d'une facture
$transaction = $eReportingService->createTransactionFromInvoice($invoice);

echo $transaction->transactionType->value;  // B2B_FRANCE, B2C, etc.
echo $transaction->totalExcludingVat;       // "1000.00"
echo $transaction->totalVat;                // "200.00"
```

### Obtenir un résumé pour une période

```php
use CorentinBoutillier\InvoiceBundle\EReporting\Enum\ReportingFrequency;

// Collecter les transactions de la période
$transactions = [];
foreach ($invoices as $invoice) {
    $transactions[] = $eReportingService->createTransactionFromInvoice($invoice);
}

// Générer le résumé
$summary = $eReportingService->getSummary(
    periodStart: new \DateTimeImmutable('2025-01-01'),
    periodEnd: new \DateTimeImmutable('2025-01-31'),
    frequency: ReportingFrequency::MONTHLY,
    transactions: $transactions,
);

echo $summary->totalExcludingVat;      // Total HT de la période
echo $summary->totalVat;               // Total TVA de la période
echo $summary->transactionCount;       // Nombre de transactions

// Détail par taux de TVA
foreach ($summary->vatByRate as $rate => $amount) {
    echo "TVA {$rate}% : {$amount}\n";
}
```

### Vérifier si une facture nécessite l'e-reporting

```php
if ($eReportingService->requiresEReporting($invoice)) {
    // La facture doit être déclarée
    $transaction = $eReportingService->createTransactionFromInvoice($invoice);
}
```

## 9. Bonnes pratiques

### 9.1 Validation des données client

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomerDataFactory
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    public function createFromForm(array $data): CustomerData
    {
        // Valider les données avant de créer le DTO
        $customerData = new CustomerData(
            name: $data['name'] ?? throw new \InvalidArgumentException('Name required'),
            address: $data['address'] ?? throw new \InvalidArgumentException('Address required'),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
        );

        // Valider le format du numéro de TVA si présent
        if ($customerData->vatNumber && !$this->isValidVatNumber($customerData->vatNumber)) {
            throw new \InvalidArgumentException('Invalid VAT number format');
        }

        return $customerData;
    }

    private function isValidVatNumber(string $vat): bool
    {
        // Format français : FR + 2 chiffres clé + 9 chiffres SIREN
        return (bool) preg_match('/^FR[0-9]{11}$/', $vat);
    }
}
```

### 9.2 Gestion des erreurs de finalisation

```php
use CorentinBoutillier\InvoiceBundle\Exception\InvoiceFinalizationException;

try {
    $invoiceFinalizer->finalize($invoice);
} catch (InvoiceFinalizationException $e) {
    // Gestion spécifique des erreurs de finalisation
    match ($e->getMessage()) {
        'Invoice must be in DRAFT status' =>
            $logger->warning("Tentative de finalisation d'une facture déjà finalisée"),
        'Invoice must have at least one line' =>
            throw new \RuntimeException('Impossible de finaliser une facture vide'),
        default =>
            $logger->error("Erreur de finalisation : {$e->getMessage()}")
    };
}
```

### 9.3 Utilisation correcte de Money

```php
// ✅ BON : Utiliser fromEuros() pour les saisies utilisateur
$userInput = '99.99';
$price = Money::fromEuros($userInput);

// ✅ BON : Utiliser fromCents() pour les valeurs en base
$centsFromDb = 9999;
$price = Money::fromCents($centsFromDb);

// ✅ BON : Toutes les opérations sont immutables
$original = Money::fromEuros('100.00');
$discounted = $original->multiply(0.9);  // Nouvelle instance
var_dump($original->toEuros());   // 100.00 (inchangé)
var_dump($discounted->toEuros()); // 90.00

// ❌ MAUVAIS : Ne pas utiliser float directement
$price = 99.99;  // Erreurs d'arrondi possibles
$total = $price * 1.2;  // ❌

// ✅ BON : Toujours passer par Money
$price = Money::fromEuros('99.99');
$total = $price->multiply(1.2);  // ✅
```

### 9.4 Subscribe aux événements pour l'audit

```php
namespace App\EventSubscriber;

use CorentinBoutillier\InvoiceBundle\Event\InvoiceCreatedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoiceFinalizedEvent;
use CorentinBoutillier\InvoiceBundle\Event\InvoicePaidEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $auditLogger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => 'logCreation',
            InvoiceFinalizedEvent::class => 'logFinalization',
            InvoicePaidEvent::class => 'logPayment',
        ];
    }

    public function logCreation(InvoiceCreatedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $this->auditLogger->info('Invoice created', [
            'customer' => $invoice->getCustomerName(),
            'date' => $invoice->getDate()->format('Y-m-d'),
        ]);
    }

    public function logFinalization(InvoiceFinalizedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $this->auditLogger->info('Invoice finalized', [
            'number' => $invoice->getNumber(),
            'total' => $invoice->getTotalIncludingVat()->toEuros(),
        ]);
    }

    public function logPayment(InvoicePaidEvent $event): void
    {
        $invoice = $event->getInvoice();
        $payment = $event->getPayment();
        $this->auditLogger->info('Invoice paid', [
            'number' => $invoice->getNumber(),
            'amount' => $payment->getAmount()->toEuros(),
            'method' => $payment->getMethod()->value,
        ]);
    }
}
```

### 9.5 Override de template : utiliser includes pour la réutilisabilité

```twig
{# templates/bundles/InvoiceBundle/invoice/pdf.html.twig #}
{% extends "@Invoice/invoice/pdf.html.twig" %}

{# Plutôt que de tout redéfinir, utiliser des includes #}
{% block header %}
    {% include 'invoice/_pdf_header.html.twig' %}
{% endblock %}

{% block footer %}
    {% include 'invoice/_pdf_footer.html.twig' %}
{% endblock %}
```

```twig
{# templates/invoice/_pdf_header.html.twig #}
<div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="Logo" />
    <h1>{{ invoice.type.value == 'INVOICE' ? 'FACTURE' : 'AVOIR' }}</h1>
</div>
```

Cette approche permet de :
- Réutiliser les fragments entre différents templates
- Faciliter la maintenance
- Tester les fragments indépendamment

---

## Pour aller plus loin

- Consultez **ARCHITECTURE.md** pour comprendre les décisions de conception
- Consultez **README.md** pour la configuration complète
- Explorez les tests fonctionnels dans `tests/Functional/` pour plus d'exemples
