# Invoice Bundle

> Bundle Symfony professionnel pour la gestion de factures et avoirs conformes à la réglementation française.

[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](https://phpstan.org/)
[![Code Coverage](https://img.shields.io/badge/coverage-94%25-brightgreen.svg)](coverage/index.html)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-6.4%20%7C%207.x-blue.svg)](https://symfony.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 📋 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Configuration](#️-configuration)
- [Documentation](#-documentation)
- [Tests](#-tests)
- [Qualité du code](#-qualité-du-code)
- [License](#-license)

## ✨ Fonctionnalités

### 🎯 Cœur de métier
- **Factures et avoirs** : Gestion complète du cycle de vie (brouillon → finalisée → payée)
- **Conformité française** : TVA, mentions légales, numérotation par exercice comptable
- **Paiements multiples** : Suivi des règlements partiels et complets
- **Historique d'audit** : Traçabilité complète de tous les changements

### 💰 Calculs financiers précis
- **Money Value Object** : Arithmétique en centimes (integers) pour éviter les erreurs d'arrondi
- **Multi-TVA** : Support des différents taux français (20%, 10%, 5.5%, 2.1%)
- **Remises** : Par ligne et globales (montant fixe ou pourcentage)

### 📄 Génération PDF
- **Templates Twig** : Personnalisables par héritage
- **Factur-X (ZUGFeRD)** : PDF/A-3 avec XML EN 16931 embarqué pour la facturation électronique
- **Stockage flexible** : Filesystem par défaut, extensible (S3, etc.)

### 📊 Export comptable
- **Export FEC** : Fichier des Écritures Comptables à 18 colonnes conforme à la réglementation
- **Plan comptable** : Paramétrable (comptes clients, ventes, TVA)

### 🔢 Numérotation
- **Séquentielle** : Thread-safe par exercice comptable
- **Formats** : `FA-YYYY-XXXX` (factures), `AV-YYYY-XXXX` (avoirs)
- **Exercice fiscal** : Configurable (Janvier-Décembre ou personnalisé)

## 📦 Prérequis

- PHP 8.3 ou supérieur
- Symfony 6.4 ou 7.x
- Doctrine ORM
- Extensions PHP : `ext-dom`, `ext-libxml`, `ext-intl`

## 🚀 Installation

```bash
composer require corentinboutillier/invoice-bundle
```

Le bundle s'enregistre automatiquement si vous utilisez Symfony Flex.

## ⚡ Quick Start

### 1. Configuration minimale

Créez `config/packages/invoice.yaml` :

```yaml
invoice:
    company:
        name: "Ma Société SARL"
        address: "123 rue de Paris, 75002 Paris, France"
        siret: "12345678900012"
        vat_number: "FR12345678901"
        email: "contact@example.com"

    pdf:
        storage_path: "%kernel.project_dir%/var/invoices"
```

### 2. Créer votre première facture

```php
use CorentinBoutillier\InvoiceBundle\DTO\{CustomerData, Money};
use CorentinBoutillier\InvoiceBundle\Entity\InvoiceLine;
use CorentinBoutillier\InvoiceBundle\Service\{
    InvoiceManagerInterface,
    InvoiceFinalizerInterface
};

// Injection des services
public function __construct(
    private InvoiceManagerInterface $invoiceManager,
    private InvoiceFinalizerInterface $invoiceFinalizer,
) {}

// Créer une facture
$invoice = $this->invoiceManager->createInvoice(
    customerData: new CustomerData(
        name: 'Client SARL',
        address: '456 Avenue du Client, 75001 Paris',
        email: 'client@example.com',
    ),
    date: new \DateTimeImmutable(),
    paymentTerms: '30 jours net',
);

// Ajouter des lignes
$invoice->addLine(new InvoiceLine(
    description: 'Prestation de service',
    quantity: 3,
    unitPrice: Money::fromEuros('150.00'),
    vatRate: 20.0,
));

// Finaliser (génère le numéro et le PDF)
$this->invoiceFinalizer->finalize($invoice);

// Résultat : FA-2025-0001 avec PDF stocké
```

## ⚙️ Configuration

### Configuration complète

```yaml
# config/packages/invoice.yaml
invoice:
    company:
        name: "ACME SARL"
        address: "123 rue de Paris, 75002 Paris"
        siret: "12345678900012"
        vat_number: "FR12345678901"
        email: "contact@acme.fr"
        phone: "01 23 45 67 89"
        bank_name: "BNP Paribas"
        iban: "FR7630001007941234567890185"
        bic: "BNPAFRPP"

    vat_rates:
        standard: 20.0        # Taux normal
        intermediate: 10.0
        reduced: 5.5
        super_reduced: 2.1

    pdf:
        enabled: true
        storage_path: "%kernel.project_dir%/var/invoices"

    factur_x:
        enabled: true
        profile: "BASIC"      # MINIMUM|BASIC|EN16931|EXTENDED

    accounting:
        customer_account: "411000"
        sales_account: "707000"
        vat_collected_account: "445710"
        journal_code: "VT"

    fiscal_year:
        start_month: 1        # 1 = Janvier, 11 = Novembre
```

### Multi-société (Provider personnalisé)

Pour gérer plusieurs sociétés, implémentez `CompanyProviderInterface` :

```php
use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;

class DatabaseCompanyProvider implements CompanyProviderInterface
{
    public function getCompanyData(?int $companyId = null): CompanyData
    {
        // Récupérer les données depuis votre base
        $company = $this->repository->find($companyId);

        return new CompanyData(/* ... */);
    }
}
```

Enregistrez-le dans `services.yaml` :

```yaml
App\Provider\DatabaseCompanyProvider:
    tags: ['invoice.company_provider']
```

## 📚 Documentation

- **[USAGE.md](USAGE.md)** - Guide d'utilisation complet avec exemples
  - Workflows avancés (multi-TVA, remises, avoirs)
  - Extension via events et providers
  - Personnalisation des templates PDF
  - Export FEC
  - Bonnes pratiques

- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Décisions architecturales
  - Pattern Provider
  - Money Value Object
  - Event-Driven Design
  - Decoupling strategy

### Money Value Object

Le bundle utilise un Value Object `Money` pour garantir la précision des calculs :

```php
use CorentinBoutillier\InvoiceBundle\DTO\Money;

// Construction
$price = Money::fromEuros('99.99');      // 9999 centimes
$discount = Money::fromCents(1000);       // 10.00€

// Opérations (immutables)
$total = $price->multiply(3);             // 299.97€
$discounted = $total->subtract($discount); // 289.97€

// Formatage
echo $discounted->format('fr_FR');        // "289,97 €"
```

**Pourquoi des centimes ?** Les nombres à virgule flottante créent des erreurs d'arrondi (`0.1 + 0.2 = 0.30000000000000004`). Le stockage en entiers garantit une précision absolue.

## 🧪 Tests

Le bundle dispose d'une suite de tests complète (583 tests, 1463 assertions, 94% de couverture).

```bash
# Tests unitaires et fonctionnels
vendor/bin/phpunit

# Avec couverture de code
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage
```

## 🔍 Qualité du code

### PHPStan - Niveau 9

```bash
vendor/bin/phpstan analyse
```

### PHP CS Fixer

```bash
vendor/bin/php-cs-fixer fix              # Corriger
vendor/bin/php-cs-fixer fix --dry-run    # Vérifier sans corriger
```

### Standards

- **PHPStan niveau 9** : Analyse statique la plus stricte
- **PHP CS Fixer** : Style Symfony avec trailing commas
- **PHP 8.3+** : `declare(strict_types=1)` sur tous les fichiers
- **Couverture** : > 90%

## 🤝 Contribution

Les contributions sont les bienvenues ! Merci de :

1. Respecter les standards de qualité (PHPStan 9, CS Fixer)
2. Ajouter des tests pour toute nouvelle fonctionnalité
3. Suivre le workflow TDD (Red → Green → Refactor)

## 📄 License

Ce projet est sous licence [MIT](LICENSE).

---

**Développé avec ❤️ pour la communauté Symfony française**
