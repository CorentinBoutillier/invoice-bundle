# Architecture du Bundle Invoice

## Principes de conception

### Minimalisme
Le bundle fournit le **strict minimum** d'entités nécessaires à la facturation.

### Découplage total
Le bundle n'a **AUCUNE relation** vers les entités de l'application cliente.

## Entités fournies par le bundle

### Entités gérées
- ✅ `Invoice` (Facture et Avoir - avec type discriminant)
- ✅ `InvoiceLine` (Ligne de facture/avoir)
- ✅ `Payment` (Paiement - extensible par l'app)

### Entités NON gérées
- ❌ `Company` / Société
- ❌ `Customer` / Client
- ❌ `User` / Utilisateur
- ❌ `Product` / Service

## Stratégie de données

### Invoice = Document immuable et autonome

L'entité `Invoice` contient un **snapshot** (copie) des données au moment de la création :
- Nom et adresse de la société émettrice
- Nom et adresse du client
- Montants, dates, lignes de facture

**Aucune relation Doctrine** vers les entités externes (Company, Customer, etc.)

### Pourquoi cette approche ?

1. **Conformité légale** - Une facture est un document archivable et immuable
2. **Indépendance** - Le bundle ne dépend d'aucune entité externe
3. **Immutabilité** - Même si le client change d'adresse, la facture conserve l'ancienne
4. **Simplicité** - Pas de configuration de relations complexes

## Relations inverses (optionnelles)

Si l'application cliente **souhaite** lier ses entités aux factures, **c'est elle qui gère** :

```php
// Dans l'app cliente (optionnel)
class Customer {
    #[ORM\OneToMany(targetEntity: Invoice::class)]
    private Collection $invoices;
}
```

Le bundle n'impose rien, l'app cliente décide.

## Cas d'usage multi/mono société

### Configuration
```yaml
# Mono-société
invoice:
    multi_company: false

# Multi-sociétés
invoice:
    multi_company: true
```

Le bundle s'adapte mais ne gère pas l'entité Company.

---

## Décisions prises

### Factures et Avoirs
Une **seule entité `Invoice`** avec un champ `type` (INVOICE ou CREDIT_NOTE) :
- Structure identique
- Code simplifié
- Numérotation distincte via configuration (FA-xxx pour factures, AV-xxx pour avoirs)
- Avoir = référence optionnelle vers facture créditée

### Configuration des données société (émettrice)

**Système de Provider** pour la flexibilité maximale :

#### Interface
```php
interface CompanyProviderInterface {
    public function getCompanyData(?int $companyId = null): CompanyData;
}
```

#### CompanyData (DTO)
```php
class CompanyData {
    public string $name;
    public string $address;
    public ?string $siret;
    public ?string $vatNumber;
    public ?string $email;
    public ?string $phone;
    public ?string $logo;
    public ?string $legalForm;  // SARL, SAS, etc.
    public ?string $capital;
    public ?string $rcs;
}
```

#### Cas d'usage

**Mono-société simple (config YAML) :**
```yaml
invoice:
    company:
        name: "ACME SARL"
        address: "123 rue de Paris"
        siret: "12345678900012"
        # ...
```
→ Crée automatiquement un `ConfigCompanyProvider`

**Mono/Multi-sociétés (BDD custom) :**
```php
class DatabaseCompanyProvider implements CompanyProviderInterface {
    public function getCompanyData(?int $companyId = null): CompanyData {
        $company = $this->repository->find($companyId ?? $this->defaultId);
        return new CompanyData(...);
    }
}
```
→ Enregistrer avec le tag `invoice.company_provider`

**Avantages :**
- ✅ Mono-société simple = config YAML
- ✅ Mono-société BDD = provider custom
- ✅ Multi-sociétés = provider custom
- ✅ Source de données au choix (BDD, API, fichier, etc.)

### Configuration des données client

**Pas de provider** pour les clients, l'application passe directement les données via un **DTO typé** :

#### CustomerData (DTO)
```php
class CustomerData {
    public string $name;
    public string $address;
    public ?string $email;
    public ?string $phone;
    public ?string $siret;        // Si client pro
    public ?string $vatNumber;    // Si TVA intra-communautaire
}
```

#### Utilisation
```php
// L'application crée le DTO depuis son entité Customer
$customerData = new CustomerData(
    name: $customer->getName(),
    address: $customer->getFullAddress(),
    email: $customer->getEmail(),
    // ...
);

// Et le passe au service
$invoiceManager->createInvoice($customerData, ...);
```

**Pourquoi pas de CustomerProvider ?**
- Les clients peuvent venir de sources variées (BDD, API, formulaire)
- L'app a déjà l'entité Customer en main au moment de créer la facture
- Plus simple et direct
- Évite une abstraction inutile

### Workflow & États des factures

**Enum fixe fournie par le bundle :**

```php
enum InvoiceStatus: string {
    case DRAFT = 'draft';                    // Brouillon (modifiable)
    case FINALIZED = 'finalized';            // Finalisée (numéro attribué, non modifiable)
    case SENT = 'sent';                      // Envoyée au client
    case PAID = 'paid';                      // Payée intégralement
    case PARTIALLY_PAID = 'partially_paid';  // Partiellement payée
    case OVERDUE = 'overdue';                // En retard de paiement
    case CANCELLED = 'cancelled';            // Annulée
}
```

**Utilisation dans l'entité :**
```php
#[ORM\Column(enumType: InvoiceStatus::class)]
private InvoiceStatus $status;
```

**Avantage :** États standardisés, simples et cohérents pour tous les projets

---

### Système de numérotation

**Complexité métier :**
- Séquence par **exercice comptable** (pas année civile)
- Exercice comptable **configurable par société** (dates début/fin variables)
- Reset à 1 à chaque nouvel exercice
- Séquence **par société** (obligatoire)
- Format **overridable**

#### Configuration exercice comptable

**Via CompanyProvider :**
```php
class CompanyData {
    // ... autres champs
    public readonly int $fiscalYearStartMonth;  // Ex: 11 (novembre)
    public readonly int $fiscalYearStartDay;    // Ex: 1
}
```

**Exemple :**
- Société A : exercice de janvier à décembre (standard)
- Société B : exercice de novembre à octobre
- Société C : 1er exercice juillet-novembre (5 mois), puis novembre-octobre

#### Format de numérotation

**Format par défaut :**
- Factures : `FA-{YEAR}-{SEQUENCE}`  → `FA-2025-0001`
- Avoirs : `AV-{YEAR}-{SEQUENCE}`    → `AV-2025-0001`

**Variables disponibles :**
- `{YEAR}` - Année de l'exercice comptable
- `{SEQUENCE}` - Numéro séquentiel (padded)
- `{COMPANY_ID}` - ID de la société (si multi-company)
- `{TYPE}` - Type (INVOICE ou CREDIT_NOTE)

**Configuration :**
```yaml
invoice:
    numbering:
        invoice_format: 'FA-{YEAR}-{SEQUENCE}'    # Par défaut
        credit_note_format: 'AV-{YEAR}-{SEQUENCE}'
        sequence_padding: 4                        # 0001, 0002, etc.
```

**Override via service custom :**
```php
interface InvoiceNumberGeneratorInterface {
    public function generate(
        Invoice $invoice,
        CompanyData $company,
        int $fiscalYear
    ): string;
}

class CustomNumberGenerator implements InvoiceNumberGeneratorInterface {
    public function generate(...): string {
        // Format custom de l'app
        return sprintf('CUSTOM-%d-%04d', $fiscalYear, $sequence);
    }
}
```

#### Table de séquences

**Entité dédiée pour garantir l'unicité :**
```php
#[ORM\Entity]
#[UniqueConstraint(columns: ['company_id', 'fiscal_year', 'type'])]
class InvoiceSequence {
    private ?int $companyId;           // NULL si mono-société
    private int $fiscalYear;           // 2025, 2026, etc.
    private InvoiceType $type;         // INVOICE ou CREDIT_NOTE
    private int $lastNumber = 0;       // Dernier numéro utilisé
}
```

**Avantages :**
- ✅ Gestion exercices comptables complexes
- ✅ Séquences isolées par société
- ✅ Format configurable
- ✅ Overridable complètement si besoin
- ✅ Thread-safe (transaction + lock)

### TVA et taux applicables

**Taux par défaut : France**

Le bundle fournit les taux français par défaut mais ils sont **configurables**.

#### Configuration par défaut

```yaml
invoice:
    vat_rates:
        normal:
            rate: 20.0
            label: "TVA normale 20%"
        intermediate:
            rate: 10.0
            label: "TVA intermédiaire 10%"
        reduced:
            rate: 5.5
            label: "TVA réduite 5,5%"
        super_reduced:
            rate: 2.1
            label: "TVA super réduite 2,1%"
        exempt:
            rate: 0.0
            label: "Exonéré de TVA"
```

#### Configuration custom (autre pays)

```yaml
# Exemple : Belgique
invoice:
    vat_rates:
        standard:
            rate: 21.0
            label: "TVA standard 21%"
        reduced:
            rate: 6.0
            label: "TVA réduite 6%"
        parking:
            rate: 12.0
            label: "TVA parking 12%"
        exempt:
            rate: 0.0
            label: "Exonéré"
```

#### Utilisation dans InvoiceLine

```php
#[ORM\Entity]
class InvoiceLine {
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $vatRate;  // 20.00, 10.00, etc.

    #[ORM\Column(nullable: true)]
    private ?string $vatLabel;  // "TVA normale 20%"

    // Calculs automatiques
    private string $amountExcludingVat;
    private string $vatAmount;
    private string $amountIncludingVat;
}
```

#### Cas particuliers supportés

**TVA intra-communautaire :**
```php
$line->setVatRate(0.0);
$line->setVatLabel("Auto-liquidation - Art. 283-2 du CGI");
```

**Mentions légales TVA :**
- Exonération : "TVA non applicable, art. 293 B du CGI"
- Auto-entrepreneur : "TVA non applicable - Article 293B du CGI"
- Reverse charge : "Auto-liquidation"

**Avantages :**
- ✅ Taux français par défaut (prêt à l'emploi)
- ✅ Configurable pour autres pays
- ✅ Support cas particuliers (intra-UE, auto-liquidation)
- ✅ Labels personnalisables

---

### Mentions légales françaises

**Toutes les mentions sont configurables** (pas de valeurs en dur).

#### Mentions obligatoires en France

**Via CompanyProvider :**
```php
class CompanyData {
    // ... champs existants

    // Mentions légales
    public readonly ?string $siret;
    public readonly ?string $vatNumber;
    public readonly ?string $rcs;           // "Paris B 123 456 789"
    public readonly ?string $legalForm;     // "SARL", "SAS", etc.
    public readonly ?string $capital;       // "10 000 €"
}
```

**Via configuration YAML :**
```yaml
invoice:
    legal_mentions:
        # Pénalités de retard (obligatoire)
        late_payment_penalty:
            rate: 13.0  # Taux BCE + 10 points (actuellement ~13-14%)
            label: "En cas de retard de paiement, seront exigibles, conformément à l'article L. 441-10 du code de commerce, une indemnité calculée sur la base de trois fois le taux de l'intérêt légal en vigueur ainsi qu'une indemnité forfaitaire pour frais de recouvrement de 40 euros."

        # Indemnité forfaitaire de recouvrement (obligatoire)
        recovery_fee: 40.00  # En euros

        # Escompte (obligatoire de mentionner, même si = néant)
        early_payment_discount:
            enabled: false
            rate: 0.0      # % de réduction
            days: 0        # Nombre de jours pour en bénéficier
            label: "Escompte pour règlement anticipé : néant"

        # CGV (non obligatoire)
        terms_of_sale:
            enabled: false
            url: null      # URL vers les CGV si applicable
            text: null     # Ou texte custom
```

#### Exemple avec escompte activé

```yaml
invoice:
    legal_mentions:
        early_payment_discount:
            enabled: true
            rate: 2.0      # 2% de réduction
            days: 8        # Si paiement sous 8 jours
            label: "Escompte de 2% pour règlement sous 8 jours"
```

#### Génération automatique sur la facture

Les mentions sont ajoutées automatiquement lors de la génération PDF :
- Infos société (SIRET, RCS, Capital, forme juridique)
- Pénalités de retard
- Indemnité forfaitaire 40€
- Escompte (ou "néant")
- CGV si configurées

**Avantages :**
- ✅ Toutes les mentions configurables
- ✅ Taux de pénalités modifiable (suit le taux BCE)
- ✅ Escompte optionnel
- ✅ Conforme législation française
- ✅ Extensible pour autres mentions

---

### Génération PDF

**Le bundle fournit un template Twig par défaut**, overridable par l'application.

#### Template par défaut

**Fichier fourni par le bundle :**
```
bundle/templates/invoice/pdf.html.twig
```

**Contient :**
- En-tête avec logo et infos société
- Infos client
- Tableau des lignes de facture
- Totaux (HT, TVA, TTC)
- Mentions légales
- Footer

#### Override du template

**Configuration :**
```yaml
invoice:
    pdf:
        template: '@App/invoice/custom_pdf.html.twig'  # Override
        # template: '@Invoice/invoice/pdf.html.twig'  # Par défaut
        logo_path: '%kernel.project_dir%/public/logo.png'
        footer_text: "Merci pour votre confiance"
```

**L'app peut créer son propre template :**
```twig
{# templates/invoice/custom_pdf.html.twig #}
{% extends '@Invoice/invoice/pdf.html.twig' %}

{% block header %}
    {# Override juste l'en-tête #}
    <div class="custom-header">...</div>
{% endblock %}
```

#### Variables disponibles dans le template

```twig
{# Données de la facture #}
{{ invoice.number }}
{{ invoice.date }}
{{ invoice.dueDate }}
{{ invoice.status }}

{# Société émettrice #}
{{ company.name }}
{{ company.address }}
{{ company.siret }}
{{ company.vatNumber }}
{{ company.logo }}

{# Client #}
{{ customer.name }}
{{ customer.address }}

{# Lignes de facture #}
{% for line in invoice.lines %}
    {{ line.description }}
    {{ line.quantity }}
    {{ line.unitPrice }}
    {{ line.vatRate }}
    {{ line.total }}
{% endfor %}

{# Totaux #}
{{ invoice.totalExcludingVat }}
{{ invoice.totalVat }}
{{ invoice.totalIncludingVat }}

{# Mentions légales #}
{{ legalMentions.latePaymentPenalty }}
{{ legalMentions.recoveryFee }}
{{ legalMentions.earlyPaymentDiscount }}
```

#### Service de génération

```php
interface PdfGeneratorInterface {
    public function generate(Invoice $invoice): string; // Retourne le PDF en binaire
}

class TwigPdfGenerator implements PdfGeneratorInterface {
    public function __construct(
        private Twig $twig,
        private Dompdf $dompdf,
        private string $template
    ) {}

    public function generate(Invoice $invoice): string {
        $html = $this->twig->render($this->template, [
            'invoice' => $invoice,
            'company' => $companyData,
            'customer' => $customerData,
            'legalMentions' => $legalMentions,
        ]);

        return $this->dompdf->generatePdf($html);
    }
}
```

#### Personnalisation

**Couleurs et styles (CSS dans le template) :**
```yaml
invoice:
    pdf:
        primary_color: '#3498db'
        secondary_color: '#2c3e50'
        font_family: 'DejaVu Sans'
```

**Accessible dans le template :**
```twig
<style>
    .header { background-color: {{ pdf_config.primary_color }}; }
    body { font-family: {{ pdf_config.font_family }}; }
</style>
```

**Avantages :**
- ✅ Template Twig professionnel fourni
- ✅ Overridable facilement
- ✅ Héritage Twig possible (override partiel)
- ✅ Personnalisation couleurs/logo
- ✅ Multi-langues supporté (via traductions Twig)

---

### Events & extensibilité

**Le bundle dispatch des events à chaque action importante** pour permettre aux applications de s'accrocher au workflow.

#### Events du cycle de vie

**Création et modification :**
```php
class InvoiceCreatedEvent {
    public function __construct(
        public readonly Invoice $invoice
    ) {}
}

class InvoiceUpdatedEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $changedFields  // Liste des champs modifiés
    ) {}
}
```

**Finalisation :**
```php
class InvoiceFinalizedEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $number  // Numéro attribué
    ) {}
}
```

**Changements d'état :**
```php
class InvoiceStatusChangedEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoiceStatus $oldStatus,
        public readonly InvoiceStatus $newStatus
    ) {}
}
```

**Paiement :**
```php
class InvoicePaidEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly \DateTimeImmutable $paidAt
    ) {}
}

class InvoicePartiallyPaidEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $amountPaid,  // Montant du paiement partiel
        public readonly string $remainingAmount
    ) {}
}
```

**Retard de paiement :**
```php
class InvoiceOverdueEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $daysOverdue
    ) {}
}
```

**Annulation :**
```php
class InvoiceCancelledEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $reason = null
    ) {}
}
```

**Avoirs (Credit Notes) :**
```php
class CreditNoteCreatedEvent {
    public function __construct(
        public readonly Invoice $creditNote,
        public readonly ?Invoice $originalInvoice = null  // Facture créditée
    ) {}
}
```

**PDF :**
```php
class InvoicePdfGeneratedEvent {
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $pdfContent  // Binaire du PDF
    ) {}
}
```

#### Utilisation dans l'application

**Exemple : Envoi d'email automatique**
```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceEmailSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceFinalizedEvent::class => 'onInvoiceFinalized',
            InvoicePaidEvent::class => 'onInvoicePaid',
        ];
    }

    public function onInvoiceFinalized(InvoiceFinalizedEvent $event): void
    {
        $invoice = $event->invoice;
        // Envoyer l'email avec le PDF au client
        $this->mailer->sendInvoice($invoice);
    }

    public function onInvoicePaid(InvoicePaidEvent $event): void
    {
        // Envoyer un reçu de paiement
        $this->mailer->sendPaymentReceipt($event->invoice);
    }
}
```

**Exemple : Logging / Audit**
```php
class InvoiceAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => 'logCreation',
            InvoiceStatusChangedEvent::class => 'logStatusChange',
            InvoiceCancelledEvent::class => 'logCancellation',
        ];
    }

    public function logStatusChange(InvoiceStatusChangedEvent $event): void
    {
        $this->logger->info('Invoice status changed', [
            'invoice_number' => $event->invoice->getNumber(),
            'old_status' => $event->oldStatus->value,
            'new_status' => $event->newStatus->value,
        ]);
    }
}
```

**Exemple : Intégration comptable**
```php
class AccountingIntegrationSubscriber implements EventSubscriberInterface
{
    public function onInvoiceFinalized(InvoiceFinalizedEvent $event): void
    {
        // Synchroniser avec le logiciel comptable
        $this->accountingSoftware->syncInvoice($event->invoice);
    }

    public function onInvoicePaid(InvoicePaidEvent $event): void
    {
        // Enregistrer le paiement dans la compta
        $this->accountingSoftware->recordPayment($event->invoice);
    }
}
```

**Avantages :**
- ✅ Point d'accroche pour toutes les actions importantes
- ✅ Découplage total (le bundle ne gère pas l'email, la compta, etc.)
- ✅ Extensible à l'infini
- ✅ Audit trail facile à implémenter
- ✅ Intégrations tierces simplifiées

---

### Stockage des PDFs

#### Quand générer le PDF ?

**À la finalisation de la facture** (attribution du numéro).

Le PDF est généré dans une **transaction atomique** :
1. Attribution du numéro (InvoiceSequence)
2. Génération du PDF
3. Stockage du PDF
4. Mise à jour de Invoice avec le path du PDF

**Si une étape échoue → ROLLBACK complet** (y compris la séquence).

```php
// Service de finalisation
public function finalize(Invoice $invoice): void
{
    $this->entityManager->beginTransaction();

    try {
        // 1. Attribuer le numéro
        $number = $this->numberGenerator->generate($invoice);
        $invoice->setNumber($number);
        $invoice->setStatus(InvoiceStatus::FINALIZED);

        // 2. Générer le PDF
        $pdfContent = $this->pdfGenerator->generate($invoice);

        // 3. Stocker le PDF
        $pdfPath = $this->pdfStorage->store($invoice, $pdfContent);

        // 4. Enregistrer le path
        $invoice->setPdfPath($pdfPath);
        $invoice->setPdfGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
        $this->entityManager->commit();

        // 5. Event après succès
        $this->eventDispatcher->dispatch(new InvoiceFinalizedEvent($invoice, $number));

    } catch (\Exception $e) {
        $this->entityManager->rollback();
        throw new InvoiceFinalizationException(
            'Failed to finalize invoice: ' . $e->getMessage(),
            previous: $e
        );
    }
}
```

#### Où stocker les PDFs ?

**Abstraction via StorageProvider** pour supporter filesystem, cloud, etc.

**Interface :**
```php
interface PdfStorageInterface {
    public function store(Invoice $invoice, string $pdfContent): string; // Retourne le path/URL
    public function retrieve(string $path): string; // Retourne le contenu PDF
    public function exists(string $path): bool;
    public function delete(string $path): void;
}
```

**Implémentation par défaut : Filesystem**
```php
class FilesystemPdfStorage implements PdfStorageInterface
{
    public function __construct(
        private string $basePath = '%kernel.project_dir%/var/invoices'
    ) {}

    public function store(Invoice $invoice, string $pdfContent): string
    {
        // Organiser par année/mois pour performance
        $year = $invoice->getDate()->format('Y');
        $month = $invoice->getDate()->format('m');

        $directory = sprintf('%s/%s/%s', $this->basePath, $year, $month);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new StorageException("Cannot create directory: $directory");
            }
        }

        $filename = $invoice->getNumber() . '.pdf';
        $path = sprintf('%s/%s', $directory, $filename);

        if (file_put_contents($path, $pdfContent) === false) {
            throw new StorageException("Cannot write PDF to: $path");
        }

        // Retourner le path relatif
        return sprintf('%s/%s/%s', $year, $month, $filename);
    }
}
```

**Implémentation cloud : S3**
```php
class S3PdfStorage implements PdfStorageInterface
{
    public function __construct(
        private S3Client $s3Client,
        private string $bucket
    ) {}

    public function store(Invoice $invoice, string $pdfContent): string
    {
        $key = sprintf(
            'invoices/%s/%s/%s.pdf',
            $invoice->getDate()->format('Y'),
            $invoice->getDate()->format('m'),
            $invoice->getNumber()
        );

        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $pdfContent,
            'ContentType' => 'application/pdf',
        ]);

        return $key;
    }
}
```

#### Configuration

```yaml
invoice:
    pdf_storage:
        type: 'filesystem'  # ou 's3', 'gcs', 'custom'

        # Filesystem
        filesystem:
            base_path: '%kernel.project_dir%/var/invoices'

        # S3
        s3:
            bucket: 'my-invoices-bucket'
            region: 'eu-west-1'

        # Custom provider (service ID)
        custom:
            service: 'app.custom_pdf_storage'
```

**Provider custom dans l'app :**
```php
// L'app peut fournir son propre storage
class CustomPdfStorage implements PdfStorageInterface {
    // Implémentation custom (API tierce, etc.)
}

// config/services.yaml
services:
    app.custom_pdf_storage:
        class: App\Service\CustomPdfStorage
        tags: ['invoice.pdf_storage']
```

#### Structure de l'entité Invoice

```php
#[ORM\Entity]
class Invoice {
    // ... autres champs

    #[ORM\Column(nullable: true)]
    private ?string $pdfPath = null;  // Chemin relatif ou URL

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pdfGeneratedAt = null;

    public function getPdfPath(): ?string {
        return $this->pdfPath;
    }

    public function hasPdf(): bool {
        return $this->pdfPath !== null;
    }
}
```

#### Immutabilité du PDF

**Le PDF est figé à la finalisation** :
- Une fois généré, le PDF n'est **jamais régénéré**
- Conforme à la législation (document archivable)
- Si le template change → anciens PDFs restent inchangés
- Seules les nouvelles factures utilisent le nouveau template

**Exceptions possibles (mais découragées) :**
Si absolument nécessaire, l'app peut régénérer via un service dédié :
```php
// Service spécial pour cas exceptionnels
class PdfRegenerationService {
    public function regenerate(Invoice $invoice): void {
        // Vérifications strictes
        if ($invoice->getStatus() === InvoiceStatus::PAID) {
            throw new Exception('Cannot regenerate PDF for paid invoice');
        }

        // Backup de l'ancien PDF
        $this->backupOldPdf($invoice);

        // Régénération
        $newPdf = $this->pdfGenerator->generate($invoice);
        $this->pdfStorage->store($invoice, $newPdf);
    }
}
```

#### Naming et organisation des fichiers

**Structure par défaut :**
```
var/invoices/
├── 2025/
│   ├── 01/
│   │   ├── FA-2025-0001.pdf
│   │   ├── FA-2025-0002.pdf
│   │   └── AV-2025-0001.pdf
│   ├── 02/
│   │   └── FA-2025-0023.pdf
│   └── ...
└── 2026/
    └── ...
```

**Avantages :**
- ✅ Transaction atomique (numéro + PDF indissociables)
- ✅ Rollback si échec de stockage
- ✅ Storage flexible (filesystem, S3, GCS, custom)
- ✅ PDF immutable (conforme législation)
- ✅ Organisation par année/mois (performance)
- ✅ Configurable facilement

---

### Gestion des paiements

**Entité Payment séparée**, extensible par l'application.

#### Entité Payment

```php
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['payment' => Payment::class])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(nullable: true)]
    private ?string $reference = null;  // Référence transaction/virement

    #[ORM\Column(nullable: true)]
    private ?string $notes = null;
}

enum PaymentMethod: string {
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case CHECK = 'check';
    case CASH = 'cash';
    case DIRECT_DEBIT = 'direct_debit';
    case OTHER = 'other';
}
```

#### Relation avec Invoice

```php
#[ORM\Entity]
class Invoice {
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Payment::class, cascade: ['persist'])]
    private Collection $payments;

    // Calculs automatiques
    public function getTotalPaid(): string {
        return array_reduce(
            $this->payments->toArray(),
            fn($sum, $payment) => bcadd($sum, $payment->getAmount(), 2),
            '0.00'
        );
    }

    public function getRemainingAmount(): string {
        return bcsub($this->getTotalIncludingVat(), $this->getTotalPaid(), 2);
    }

    public function isFullyPaid(): bool {
        return bccomp($this->getRemainingAmount(), '0.00', 2) === 0;
    }

    public function isPartiallyPaid(): bool {
        $paid = $this->getTotalPaid();
        return bccomp($paid, '0.00', 2) > 0
            && bccomp($paid, $this->getTotalIncludingVat(), 2) < 0;
    }
}
```

#### Extension par l'application

**L'app peut étendre Payment pour ajouter des champs custom :**

```php
// Dans l'application cliente
#[ORM\Entity]
class AppPayment extends Payment
{
    #[ORM\Column(nullable: true)]
    private ?string $stripePaymentIntentId;

    #[ORM\Column(nullable: true)]
    private ?string $internalAccountingCode;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $recordedBy;
}
```

**Configuration :**
```yaml
# config/packages/invoice.yaml
invoice:
    payment_class: App\Entity\AppPayment  # Classe custom
```

#### Service d'enregistrement de paiement

```php
class PaymentManager
{
    public function recordPayment(
        Invoice $invoice,
        string $amount,
        \DateTimeImmutable $paidAt,
        PaymentMethod $method,
        ?string $reference = null
    ): Payment {
        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmount($amount);
        $payment->setPaidAt($paidAt);
        $payment->setMethod($method);
        $payment->setReference($reference);

        $invoice->addPayment($payment);

        // Mise à jour automatique du statut
        if ($invoice->isFullyPaid()) {
            $invoice->setStatus(InvoiceStatus::PAID);
            $this->eventDispatcher->dispatch(new InvoicePaidEvent($invoice, $paidAt));
        } elseif ($invoice->isPartiallyPaid()) {
            $invoice->setStatus(InvoiceStatus::PARTIALLY_PAID);
            $this->eventDispatcher->dispatch(new InvoicePartiallyPaidEvent(
                $invoice,
                $amount,
                $invoice->getRemainingAmount()
            ));
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
```

**Avantages :**
- ✅ Historique complet des paiements
- ✅ Support paiements partiels natif
- ✅ Extensible par l'app (Stripe, PayPal, etc.)
- ✅ Calculs automatiques (montant payé, reste à payer)
- ✅ Events déclenchés automatiquement

---

### Date d'échéance et conditions de paiement

**Champs obligatoires sur la facture**.

#### Entité Invoice

```php
#[ORM\Entity]
class Invoice {
    #[ORM\Column]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column]
    private string $paymentTerms;  // "30 jours net", "45 jours fin de mois"

    public function isOverdue(): bool {
        if ($this->status === InvoiceStatus::PAID) {
            return false;
        }
        return new \DateTimeImmutable() > $this->dueDate;
    }

    public function getDaysOverdue(): int {
        if (!$this->isOverdue()) {
            return 0;
        }
        return (new \DateTimeImmutable())->diff($this->dueDate)->days;
    }
}
```

#### Service de calcul de date d'échéance

```php
interface DueDateCalculatorInterface {
    public function calculate(
        \DateTimeImmutable $invoiceDate,
        string $paymentTerms
    ): \DateTimeImmutable;
}

class DueDateCalculator implements DueDateCalculatorInterface {
    public function calculate(
        \DateTimeImmutable $invoiceDate,
        string $paymentTerms
    ): \DateTimeImmutable {
        return match($paymentTerms) {
            'comptant' => $invoiceDate,
            '30 jours net' => $invoiceDate->modify('+30 days'),
            '45 jours fin de mois' => $this->endOfMonth($invoiceDate->modify('+45 days')),
            '60 jours fin de mois' => $this->endOfMonth($invoiceDate->modify('+60 days')),
            default => $invoiceDate->modify('+30 days'), // Par défaut
        };
    }

    private function endOfMonth(\DateTimeImmutable $date): \DateTimeImmutable {
        return $date->modify('last day of this month');
    }
}
```

#### Configuration

```yaml
invoice:
    payment_terms:
        default: '30 jours net'
        available:
            - 'comptant'
            - '30 jours net'
            - '45 jours fin de mois'
            - '60 jours fin de mois'
```

**Avantages :**
- ✅ Calcul automatique date échéance
- ✅ Détection factures en retard
- ✅ Conformité légale (mention obligatoire)

---

### Informations bancaires

**Ajout dans CompanyData**.

```php
class CompanyData {
    // ... champs existants

    // Informations bancaires
    public readonly ?string $bankName;
    public readonly ?string $iban;
    public readonly ?string $bic;
}
```

**Configuration YAML :**
```yaml
invoice:
    company:
        name: "ACME SARL"
        # ... autres champs
        bank_name: "Crédit Agricole"
        iban: "FR76 1234 5678 9012 3456 7890 123"
        bic: "AGRIFRPP123"
```

**Affichage sur PDF :** Coordonnées bancaires automatiquement incluses.

---

### Remises et Réductions

**Support des remises par ligne ET remise globale sur facture**.

#### Sur InvoiceLine

```php
#[ORM\Entity]
class InvoiceLine {
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $discountRate = null;  // En % (ex: 10.00)

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;  // Montant fixe

    // Calculs
    public function getUnitPriceAfterDiscount(): string {
        $price = $this->unitPrice;

        if ($this->discountAmount !== null) {
            return bcsub($price, $this->discountAmount, 2);
        }

        if ($this->discountRate !== null) {
            $discount = bcmul($price, bcdiv($this->discountRate, '100', 4), 2);
            return bcsub($price, $discount, 2);
        }

        return $price;
    }

    public function getTotalBeforeVat(): string {
        return bcmul($this->getUnitPriceAfterDiscount(), (string)$this->quantity, 2);
    }
}
```

#### Sur Invoice (remise globale)

```php
#[ORM\Entity]
class Invoice {
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $globalDiscountRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $globalDiscountAmount = null;

    // Calculs
    public function getSubtotalBeforeDiscount(): string {
        // Somme des lignes après remises individuelles
        return array_reduce(
            $this->lines->toArray(),
            fn($sum, $line) => bcadd($sum, $line->getTotalBeforeVat(), 2),
            '0.00'
        );
    }

    public function getGlobalDiscountValue(): string {
        $subtotal = $this->getSubtotalBeforeDiscount();

        if ($this->globalDiscountAmount !== null) {
            return $this->globalDiscountAmount;
        }

        if ($this->globalDiscountRate !== null) {
            return bcmul($subtotal, bcdiv($this->globalDiscountRate, '100', 4), 2);
        }

        return '0.00';
    }

    public function getTotalExcludingVat(): string {
        return bcsub(
            $this->getSubtotalBeforeDiscount(),
            $this->getGlobalDiscountValue(),
            2
        );
    }
}
```

**Avantages :**
- ✅ Remises par ligne (produit soldé)
- ✅ Remise globale facture (client fidèle)
- ✅ Flexibilité % ou montant fixe
- ✅ Calculs automatiques

---

### Références externes

**Le bundle ne fournit pas de champs de référence**.

L'application peut étendre Invoice si besoin :

```php
// Dans l'app cliente
#[ORM\Entity]
class AppInvoice extends Invoice {
    #[ORM\Column(nullable: true)]
    private ?string $customerReference;

    #[ORM\Column(nullable: true)]
    private ?string $purchaseOrderNumber;

    #[ORM\Column(nullable: true)]
    private ?string $quoteNumber;
}
```

**Rationale :** Chaque projet a ses propres besoins de références.

---

### Conservation légale

**Le bundle ne gère pas la conservation légale**.

L'application est responsable de :
- Soft delete si nécessaire
- Archivage après X années
- Politique de rétention

**Rationale :** Varie selon secteur d'activité et obligations légales spécifiques.

---

### Unités de mesure

**Champ flexible sur InvoiceLine**.

```php
#[ORM\Entity]
class InvoiceLine {
    #[ORM\Column(nullable: true)]
    private ?string $unit = null;  // 'hours', 'days', 'units', 'kg', etc.

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $quantity;
}
```

**L'application décide du contenu** :
- Services : `'hours'`, `'days'`, `'lump_sum'`
- Produits : `'units'`, `'kg'`, `'m²'`, `'litres'`
- Ou laisser vide si non applicable

**Affichage sur facture :**
```
Développement web    10 heures × 80,00 € = 800,00 €
Serveur cloud         1 mois   × 50,00 € =  50,00 €
```

**Avantages :**
- ✅ Flexible (pas d'enum restrictive)
- ✅ Support tous types de facturation
- ✅ Optionnel

---

### Multi-devises

**Devise par défaut : EUR**, modifiable par l'application.

**Le bundle ne gère PAS les taux de change** (responsabilité de l'app).

```php
#[ORM\Entity]
class Invoice {
    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';  // Code ISO 4217 (EUR, USD, GBP, etc.)
}

class InvoiceLine {
    // Tous les montants sont dans la devise de la facture
    private string $unitPrice;
    // ...
}
```

**Configuration :**
```yaml
invoice:
    default_currency: 'EUR'
```

**Si l'app veut gérer plusieurs devises :**
- Stocker le taux de change au moment de la facture
- Étendre Invoice pour ajouter `exchangeRate`, `baseCurrency`, etc.
- Gérer la conversion dans l'app

**Rationale :** Les taux de change fluctuent, c'est métier spécifique.

---

### Export comptable (FEC)

**Le bundle fournit un service d'export FEC** (Fichier des Écritures Comptables).

#### Format FEC

Format légal français pour contrôle fiscal :
- CSV avec séparateur `|`
- 18 colonnes normalisées
- Encodage UTF-8

**Colonnes obligatoires :**
```
JournalCode|JournalLib|EcritureNum|EcritureDate|CompteNum|CompteLib|
CompAuxNum|CompAuxLib|PieceRef|PieceDate|EcritureLib|Debit|Credit|
EcritureLet|DateLet|ValidDate|Montantdevise|Idevise
```

#### Service d'export

```php
interface FecExporterInterface {
    public function export(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId = null
    ): string; // Retourne le contenu CSV
}

class FecExporter implements FecExporterInterface {
    public function export(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?int $companyId = null
    ): string {
        $invoices = $this->invoiceRepository->findForFecExport(
            $startDate,
            $endDate,
            $companyId
        );

        $lines = [];
        foreach ($invoices as $invoice) {
            // Ligne de débit (client)
            $lines[] = $this->createFecLine(
                invoice: $invoice,
                accountNumber: '411000', // Compte client
                debit: $invoice->getTotalIncludingVat(),
                credit: '0.00'
            );

            // Ligne de crédit (vente)
            $lines[] = $this->createFecLine(
                invoice: $invoice,
                accountNumber: '707000', // Compte vente
                debit: '0.00',
                credit: $invoice->getTotalExcludingVat()
            );

            // Ligne TVA
            if ($invoice->getTotalVat() !== '0.00') {
                $lines[] = $this->createFecLine(
                    invoice: $invoice,
                    accountNumber: '445710', // TVA collectée
                    debit: '0.00',
                    credit: $invoice->getTotalVat()
                );
            }
        }

        return $this->formatFecCsv($lines);
    }
}
```

**Configuration des comptes comptables :**
```yaml
invoice:
    accounting:
        customer_account: '411000'
        sales_account: '707000'
        vat_collected_account: '445710'
        vat_deductible_account: '445660'
```

**Export via commande :**
```bash
php bin/console invoice:export:fec 2024-01-01 2024-12-31 --output=fec_2024.txt
```

**Avantages :**
- ✅ Conformité légale française
- ✅ Export direct pour contrôle fiscal
- ✅ Comptes comptables configurables
- ✅ Compatible logiciels compta

---

### Audit trail & Historique

**Historique complet de toutes les actions importantes**.

#### Timestamps sur Invoice

```php
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Invoice {
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

#### Entité InvoiceHistory

**Historique détaillé des changements :**

```php
#[ORM\Entity]
class InvoiceHistory {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(enumType: InvoiceHistoryAction::class)]
    private InvoiceHistoryAction $action;

    #[ORM\Column]
    private \DateTimeImmutable $performedAt;

    #[ORM\Column(nullable: true)]
    private ?string $userId = null;  // ID utilisateur (string pour flexibilité)

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;  // Données contextuelles (ancien/nouveau statut, etc.)

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;
}

enum InvoiceHistoryAction: string {
    case CREATED = 'created';
    case UPDATED = 'updated';
    case FINALIZED = 'finalized';
    case SENT = 'sent';
    case PAYMENT_RECORDED = 'payment_recorded';
    case STATUS_CHANGED = 'status_changed';
    case CANCELLED = 'cancelled';
    case PDF_GENERATED = 'pdf_generated';
    case PDF_DOWNLOADED = 'pdf_downloaded';
    case EXPORTED = 'exported';
}
```

#### Enregistrement automatique via EventSubscriber

```php
class InvoiceHistorySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => 'onInvoiceCreated',
            InvoiceFinalizedEvent::class => 'onInvoiceFinalized',
            InvoiceStatusChangedEvent::class => 'onStatusChanged',
            InvoicePaidEvent::class => 'onPaymentRecorded',
            // ... tous les events
        ];
    }

    public function onInvoiceCreated(InvoiceCreatedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::CREATED,
            data: ['status' => $event->invoice->getStatus()->value]
        );
    }

    public function onStatusChanged(InvoiceStatusChangedEvent $event): void
    {
        $this->recordHistory(
            invoice: $event->invoice,
            action: InvoiceHistoryAction::STATUS_CHANGED,
            data: [
                'old_status' => $event->oldStatus->value,
                'new_status' => $event->newStatus->value,
            ]
        );
    }

    private function recordHistory(
        Invoice $invoice,
        InvoiceHistoryAction $action,
        ?array $data = null,
        ?string $comment = null
    ): void {
        $history = new InvoiceHistory();
        $history->setInvoice($invoice);
        $history->setAction($action);
        $history->setPerformedAt(new \DateTimeImmutable());
        $history->setUserId($this->getCurrentUserId());
        $history->setData($data);
        $history->setComment($comment);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
```

**Interface pour récupérer l'utilisateur :**
```php
interface UserProviderInterface {
    public function getCurrentUserId(): ?string;
}

// L'app implémente
class AppUserProvider implements UserProviderInterface {
    public function getCurrentUserId(): ?string {
        $user = $this->security->getUser();
        return $user?->getId();
    }
}
```

**Avantages :**
- ✅ Traçabilité complète
- ✅ Audit légal
- ✅ Timeline de la facture
- ✅ Qui a fait quoi et quand
- ✅ Données contextuelles JSON

---

### Facturation électronique (Factur-X)

**Support du format Factur-X** (PDF + XML embarqué).

#### Qu'est-ce que Factur-X ?

Format hybride :
- PDF lisible par humain
- XML structuré embarqué dans le PDF (norme EN 16931)
- Compatible Chorus Pro (B2G obligatoire)
- Futur standard B2B en France

#### Architecture

```php
interface FacturXGeneratorInterface {
    public function generate(Invoice $invoice): string; // Retourne PDF avec XML embarqué
}

class FacturXGenerator implements FacturXGeneratorInterface {
    public function generate(Invoice $invoice): string
    {
        // 1. Générer le PDF classique
        $pdfContent = $this->pdfGenerator->generate($invoice);

        // 2. Générer le XML conforme EN 16931
        $xmlContent = $this->generateFacturXml($invoice);

        // 3. Embarquer le XML dans le PDF (norme PDF/A-3)
        $facturXPdf = $this->embedXmlInPdf($pdfContent, $xmlContent);

        return $facturXPdf;
    }

    private function generateFacturXml(Invoice $invoice): string
    {
        // Génération XML selon norme EN 16931
        // https://www.fnfe-mpe.org/factur-x/
        $xml = new \DOMDocument('1.0', 'UTF-8');

        // Structure XML Factur-X
        // <rsm:CrossIndustryInvoice>
        //   <rsm:ExchangedDocumentContext>...
        //   <rsm:ExchangedDocument>...
        //   <rsm:SupplyChainTradeTransaction>...

        return $xml->saveXML();
    }
}
```

#### Configuration

```yaml
invoice:
    facturx:
        enabled: true
        profile: 'BASIC'  # MINIMUM, BASIC, EN16931, EXTENDED
        generate_on_finalize: true  # Générer automatiquement à la finalisation
```

**Profiles Factur-X :**
- **MINIMUM** : Métadonnées basiques
- **BASIC** : Informations essentielles (recommandé)
- **EN16931** : Conformité totale norme européenne
- **EXTENDED** : Toutes les données possibles

#### Service dédié

```php
class InvoiceManager {
    public function finalize(Invoice $invoice): void
    {
        $this->entityManager->beginTransaction();

        try {
            // ... numérotation

            // Générer PDF ou Factur-X selon config
            if ($this->config['facturx']['enabled']) {
                $pdfContent = $this->facturxGenerator->generate($invoice);
                $invoice->setIsFacturX(true);
            } else {
                $pdfContent = $this->pdfGenerator->generate($invoice);
            }

            // ... stockage

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
```

**Avantages :**
- ✅ Conformité Chorus Pro (B2G)
- ✅ Prêt pour obligation B2B 2026-2027
- ✅ Automatisation comptable
- ✅ Interopérabilité européenne

---

### Sécurité / Droits d'accès

**Le bundle ne gère PAS les droits d'accès**.

L'application est responsable de :
- Contrôle d'accès (Voters Symfony)
- Rôles utilisateurs
- Permissions métier

**Rationale :** Chaque app a sa propre gestion utilisateurs et ses règles métier spécifiques.

**Exemple d'implémentation dans l'app :**
```php
// Dans l'application cliente
class InvoiceVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Invoice
            && in_array($attribute, ['VIEW', 'EDIT', 'FINALIZE', 'DELETE']);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $invoice = $subject;

        return match($attribute) {
            'FINALIZE' => $invoice->getStatus() === InvoiceStatus::DRAFT
                && $user->hasRole('ROLE_INVOICE_FINALIZE'),
            'DELETE' => $invoice->getStatus() === InvoiceStatus::DRAFT
                && $user->hasRole('ROLE_INVOICE_DELETE'),
            default => true,
        };
    }
}
```

---

### Chiffrement & Signature électronique

**Hors scope du bundle.**

**Non obligatoire légalement** pour la plupart des cas d'usage :
- Conformité basique française = pas de chiffrement requis
- Immutabilité et numérotation suffisent

**Si nécessaire (secteur public, secteurs réglementés) :**
- L'application peut implémenter une signature électronique
- Extension possible via l'entité Invoice ou post-traitement du PDF
- Factur-X supporte la signature dans le XML

**Rationale :**
- Complexité (gestion certificats, coûts)
- Cas d'usage minoritaire (B2G, santé, banque)
- L'app étend si besoin spécifique

---

## Architecture complète définie ✅

Toutes les décisions architecturales ont été prises et documentées :

- ✅ Entités minimalistes (Invoice, InvoiceLine, Payment, InvoiceHistory)
- ✅ Découplage total (pas de relations externes)
- ✅ CompanyProvider (flexible)
- ✅ CustomerData (DTO simple)
- ✅ Workflow & états (Enum fixe)
- ✅ Numérotation (exercice comptable, configurable)
- ✅ TVA (taux FR par défaut, configurable)
- ✅ Mentions légales (tout configurable)
- ✅ PDF (template Twig overridable)
- ✅ Stockage PDF (transaction atomique, storage flexible, immutable)
- ✅ Events (extensibilité maximale)
- ✅ Paiements (entité extensible, paiements partiels)
- ✅ Échéances (calcul automatique, détection retards)
- ✅ Infos bancaires (IBAN/BIC)
- ✅ Remises (par ligne + globale)
- ✅ Unités de mesure (flexible)
- ✅ Multi-devises (EUR par défaut, pas de taux de change)
- ✅ Export FEC (conformité fiscale française)
- ✅ Audit trail complet (InvoiceHistory)
- ✅ Factur-X (PDF + XML embarqué, Chorus Pro)

### Hors scope (responsabilité application)
- ❌ Chiffrement/Signature électronique (sauf besoins spécifiques B2G)
- ❌ Gestion des utilisateurs et droits d'accès
- ❌ Conservation légale/archivage automatique
- ❌ Références externes métier (devis, commandes)
- ❌ Taux de change multi-devises
