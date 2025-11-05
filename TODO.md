# TODO - Invoice Bundle Implementation (TDD & DRY)

## Principes
- ✅ **TDD** : Test-Driven Development - Tests AVANT le code
- ✅ **DRY** : Don't Repeat Yourself - Éviter toute duplication
- ✅ **Qualité dès le début** : PHPStan 9, CS Fixer, couverture 100%

---

## Progression globale
- [x] Phase 0 : Setup Qualité & Tests (5 tâches) - Tâches 1-5
- [x] Phase 1 : Enums - TDD (8 tâches) - Tâches 6-13
- [x] Phase 2 : DTOs - TDD (4 tâches) - Tâches 14-17
- [x] Phase 2.5 : Money Value Object - TDD (3 tâches) - Tâches 18-20
- [x] Phase 3 : Entités - TDD (22 tâches) - Tâches 21-42
- [x] Phase 4 : Repositories - TDD (4 tâches) - Tâches 43-46
- [x] Phase 5 : Providers & Interfaces - TDD (5 tâches) - Tâches 47-51
- [x] Phase 6 : Events & Subscribers - TDD (3 tâches) - Tâches 52-54
- [~] Phase 7 : Services Métier - TDD (12 tâches) - Tâches 55-66 (10/12 complétées - 83.3%)
- [ ] Phase 8 : Features Avancées - TDD (8 tâches) - Tâches 67-74
- [ ] Phase 9 : Configuration & Intégration - TDD (5 tâches) - Tâches 75-79
- [ ] Phase 10 : Documentation & Validation finale (4 tâches) - Tâches 80-83

---

## 🔧 Phase 0 : Setup Qualité & Tests

**Objectif** : Préparer l'environnement qualité AVANT d'écrire du code

- [x] 1. Vérifier et ajuster phpstan.neon (niveau 9)
  - Règles strictes activées
  - Exclusions justifiées uniquement

- [x] 2. Vérifier et ajuster .php-cs-fixer.dist.php
  - Règles Symfony
  - declare(strict_types=1)
  - Trailing commas

- [x] 3. Configurer phpunit.xml.dist
  - Bootstrap
  - Couverture de code
  - Strict mode

- [x] 4. Créer le TestKernel et bootstrap pour tests
  - Déjà fait, vérifier

- [x] 5. Valider que les outils fonctionnent
  - `make phpstan` → OK
  - `make cs-check` → OK
  - `make test-unit` → OK (même vide)

---

## 📐 Phase 1 : Enums - TDD

### InvoiceStatus

- [x] 6. TEST : Écrire les tests pour InvoiceStatus
  - `tests/Unit/Enum/InvoiceStatusTest.php`
  - Tous les cas disponibles
  - Values correctes

- [x] 7. CODE : Implémenter InvoiceStatus
  - `src/Enum/InvoiceStatus.php`
  - Les tests doivent passer

### InvoiceType

- [x] 8. TEST : Écrire les tests pour InvoiceType
  - `tests/Unit/Enum/InvoiceTypeTest.php`

- [x] 9. CODE : Implémenter InvoiceType
  - `src/Enum/InvoiceType.php`

### PaymentMethod

- [x] 10. TEST : Écrire les tests pour PaymentMethod
  - `tests/Unit/Enum/PaymentMethodTest.php`

- [x] 11. CODE : Implémenter PaymentMethod
  - `src/Enum/PaymentMethod.php`

### InvoiceHistoryAction

- [x] 12. TEST : Écrire les tests pour InvoiceHistoryAction
  - `tests/Unit/Enum/InvoiceHistoryActionTest.php`

- [x] 13. CODE : Implémenter InvoiceHistoryAction
  - `src/Enum/InvoiceHistoryAction.php`

**✓ Validation Phase 1** : PHPStan + CS Fixer + Tests 100%

---

## 📦 Phase 2 : DTOs - TDD

### CompanyData

- [x] 14. TEST : Écrire les tests pour CompanyData
  - `tests/Unit/DTO/CompanyDataTest.php`
  - Construction
  - Tous les champs

- [x] 15. CODE : Implémenter CompanyData
  - `src/DTO/CompanyData.php`
  - Readonly properties

### CustomerData

- [x] 16. TEST : Écrire les tests pour CustomerData
  - `tests/Unit/DTO/CustomerDataTest.php`

- [x] 17. CODE : Implémenter CustomerData
  - `src/DTO/CustomerData.php`

**✓ Validation Phase 2** : PHPStan + CS Fixer + Tests 100%

---

## 💰 Phase 2.5 : Money Value Object - TDD

**Objectif** : Créer une classe Money immutable pour gérer les calculs monétaires avec précision absolue (integers = centimes)

**Décision architecturale** :
- ❌ **Pas de BCMath** (extension PHP non requise)
- ✅ **Integers** : Stockage en centimes (ex: 1500 = 15.00€)
- ✅ **Value Object** : Pattern DDD, classe immutable
- ✅ **Type safety** : Impossible de mélanger centimes et euros

### Money DTO

- [x] 18. TEST : Écrire les tests pour Money
  - `tests/Unit/DTO/MoneyTest.php`
  - Construction : `fromCents()`, `fromEuros()`, `zero()`
  - Opérations arithmétiques (immutables) : `add()`, `subtract()`, `multiply()`, `divide()`
  - Comparaisons : `equals()`, `isZero()`, `isPositive()`, `isNegative()`, `greaterThan()`, `lessThan()`
  - Formatage : `toEuros()`, `__toString()`, `format()`
  - Cas limites : montants négatifs (avoirs), arrondi sur multiply/divide
  - Immutabilité : vérifier que les opérations ne modifient pas l'objet original

- [x] 19. CODE : Implémenter Money
  - `src/DTO/Money.php`
  - Readonly class avec propriété `int $amount` (centimes)
  - Factory methods pour construction
  - Toutes les méthodes arithmétiques (retournent nouveau Money)
  - Méthodes de comparaison
  - Méthodes de formatage
  - `implements \Stringable`
  - Les tests doivent passer

- [x] 20. CODE : Créer MoneyType Doctrine (optionnel mais recommandé)
  - `src/Doctrine/Type/MoneyType.php`
  - Custom type pour stocker Money en integer (centimes)
  - `convertToDatabaseValue()` : Money → int
  - `convertToPHPValue()` : int → Money
  - Enregistrement du type dans config

**✓ Validation Phase 2.5** : PHPStan 9 (0 erreurs) + CS Fixer (0 fichiers) + Tests 100% (66/66 passent, 238 assertions)

---

## 🗄️ Phase 3 : Entités - TDD

**Note importante** : Toutes les entités utilisent maintenant le Value Object `Money` pour les montants

### InvoiceSequence

- [x] 21. TEST : Écrire les tests pour InvoiceSequence
  - `tests/Unit/Entity/InvoiceSequenceTest.php`
  - Contrainte unique
  - Incrémentation

- [x] 22. CODE : Implémenter InvoiceSequence
  - `src/Entity/InvoiceSequence.php`

### InvoiceLine (calculs simples d'abord avec Money)

- [x] 23. TEST : Tests pour InvoiceLine (sans remises)
  - `tests/Unit/Entity/InvoiceLineTest.php`
  - Création avec Money
  - Total HT simple (quantité × prix Money)

- [x] 24. CODE : Implémenter InvoiceLine (structure de base)
  - `src/Entity/InvoiceLine.php`
  - Champs en centimes (int), pas encore de calculs complexes
  - Getters retournent Money

- [x] 25. TEST : Tests pour remises sur InvoiceLine
  - Remise en %
  - Remise en montant fixe (Money)
  - Prix après remise (Money)
  - Total HT après remise (Money)

- [x] 26. CODE : Ajouter les méthodes de calcul des remises
  - `getUnitPriceAfterDiscount()` : retourne Money
  - `getTotalBeforeVat()` : retourne Money

- [x] 27. TEST : Tests pour TVA sur InvoiceLine
  - Calcul montant TVA (Money)
  - Total TTC (Money)

- [x] 28. CODE : Ajouter les méthodes de calcul TVA
  - `getVatAmount()` : retourne Money
  - `getTotalIncludingVat()` : retourne Money

### Payment

- [x] 29. TEST : Tests pour Payment
  - `tests/Unit/Entity/PaymentTest.php`
  - Création avec Money
  - Relation Invoice

- [x] 30. CODE : Implémenter Payment
  - `src/Entity/Payment.php`
  - Montant en centimes (int), getter retourne Money
  - Extensible (SINGLE_TABLE inheritance)

### Invoice (structure puis calculs avec Money)

- [x] 31. TEST : Tests pour Invoice (structure de base)
  - `tests/Unit/Entity/InvoiceTest.php`
  - Création
  - Ajout de lignes
  - Ajout de paiements

- [x] 32. CODE : Implémenter Invoice (structure de base)
  - `src/Entity/Invoice.php`
  - Champs, relations, pas encore de calculs
  - Remises globales en centimes (int)

- [x] 33. TEST : Tests pour calculs simples Invoice
  - Sous-total (somme lignes HT) → Money
  - Total TVA → Money
  - Total TTC → Money

- [x] 34. CODE : Implémenter calculs simples
  - `getSubtotalBeforeDiscount()` : retourne Money
  - `getTotalVat()` : retourne Money
  - `getTotalIncludingVat()` : retourne Money

- [x] 35. TEST : Tests pour remise globale Invoice
  - Remise globale %
  - Remise globale montant (Money)
  - Total après remise globale (Money)

- [x] 36. CODE : Implémenter remise globale
  - `getGlobalDiscountAmount()` : retourne Money
  - `getSubtotalAfterDiscount()` : retourne Money
  - `getTotalVat()` avec distribution proportionnelle

- [x] 37. TEST : Tests pour paiements Invoice
  - Total payé (Money)
  - Reste à payer (Money)
  - isFullyPaid()
  - isPartiallyPaid()

- [x] 38. CODE : Implémenter méthodes paiements
  - `getTotalPaid()` : retourne Money
  - `getRemainingAmount()` : retourne Money
  - `isFullyPaid()`
  - `isPartiallyPaid()`

- [x] 39. TEST : Tests pour échéances Invoice
  - isOverdue()
  - getDaysOverdue()

- [x] 40. CODE : Implémenter méthodes échéances
  - `isOverdue()`
  - `getDaysOverdue()`

### InvoiceHistory

- [x] 41. TEST : Tests pour InvoiceHistory
  - `tests/Unit/Entity/InvoiceHistoryTest.php`
  - Création
  - Données JSON

- [x] 42. CODE : Implémenter InvoiceHistory
  - `src/Entity/InvoiceHistory.php`

**✅ Validation Phase 3** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (260 tests, 570 assertions)

---

## 📂 Phase 4 : Repositories - TDD ✅

- [x] 43. TEST : Tests pour InvoiceRepository
  - `tests/Functional/Repository/InvoiceRepositoryTest.php`
  - 14 tests fonctionnels (business queries, FEC export, snapshots-based customer search)

- [x] 44. CODE : Implémenter InvoiceRepository
  - `src/Repository/InvoiceRepository.php`
  - 8 méthodes optimisées avec QueryBuilder (multi-company support)

- [x] 45. TEST : Tests pour InvoiceSequenceRepository
  - `tests/Functional/Repository/InvoiceSequenceRepositoryTest.php`
  - 15 tests incluant thread-safety, pessimistic locking, fiscal year calculations

- [x] 46. CODE : Implémenter InvoiceSequenceRepository
  - `src/Repository/InvoiceSequenceRepository.php`
  - PESSIMISTIC_WRITE lock, fiscal year logic, NULL handling

**Note** : PaymentRepository et InvoiceHistoryRepository basiques (pas de tests si pas de logique custom)

**✅ Validation Phase 4** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (292 tests, 655 assertions)

---

## 🔌 Phase 5 : Providers & Interfaces - TDD ✅

### CompanyProvider

- [x] 47. TEST : Tests pour ConfigCompanyProvider
  - `tests/Unit/Provider/ConfigCompanyProviderTest.php`
  - 10 tests (minimal config, complete config, fiscal year, multi-company exception)

- [x] 48. CODE : Créer interface CompanyProviderInterface + implémentation
  - `src/Provider/CompanyProviderInterface.php`
  - `src/Provider/ConfigCompanyProvider.php`
  - Type-safe config mapping, mono-company only

### UserProvider

- [x] 49. CODE : Créer UserData DTO + UserProviderInterface (interface-only)
  - `src/DTO/UserData.php` (id, name, email)
  - `src/Provider/UserProviderInterface.php`
  - Pas d'implémentation bundle (responsabilité app)

### DueDateCalculator

- [x] 50. TEST : Tests pour DueDateCalculator
  - `tests/Unit/Service/DueDateCalculatorTest.php`
  - 21 tests (comptant, jours net, fin de mois, edge cases, leap year, fallback)

- [x] 51. CODE : Créer interface + implémentation DueDateCalculator
  - `src/Service/DueDateCalculatorInterface.php`
  - `src/Service/DueDateCalculator.php`
  - Regex parsing, end-of-month helper, French payment terms

**✅ Validation Phase 5** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (323 tests, 708 assertions)

---

## 🔔 Phase 6 : Events & Subscribers - TDD

### InvoiceHistorySubscriber (TDD)

- [x] 52. TEST : Tests pour InvoiceHistorySubscriber
  - `tests/Unit/EventSubscriber/InvoiceHistorySubscriberTest.php`
  - Mock des Events (définir leur structure dans les tests)
  - Mock EventDispatcher
  - Vérifier enregistrement history pour chaque type d'event

- [x] 53. CODE : Créer les Events nécessaires
  - `src/Event/InvoiceCreatedEvent.php`
  - `src/Event/InvoiceUpdatedEvent.php`
  - `src/Event/InvoiceFinalizedEvent.php`
  - `src/Event/InvoiceStatusChangedEvent.php`
  - `src/Event/InvoicePaidEvent.php`
  - `src/Event/InvoicePartiallyPaidEvent.php`
  - `src/Event/InvoiceOverdueEvent.php`
  - `src/Event/InvoiceCancelledEvent.php`
  - `src/Event/CreditNoteCreatedEvent.php`
  - `src/Event/InvoicePdfGeneratedEvent.php`
  - Structure définie par les tests

- [x] 54. CODE : Implémenter InvoiceHistorySubscriber
  - `src/EventSubscriber/InvoiceHistorySubscriber.php`
  - Les tests doivent passer

**✅ Validation Phase 6** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (341 tests, 746 assertions)

---

## ⚙️ Phase 7 : Services Métier - TDD

### InvoiceNumberGenerator

- [x] 55. TEST : Tests pour InvoiceNumberGenerator
  - `tests/Functional/Service/NumberGenerator/InvoiceNumberGeneratorTest.php`
  - Format par défaut
  - Exercice comptable
  - Séquence par société
  - Thread-safe (concurrence)
  - Les tests définissent le contrat de l'interface

- [x] 56. CODE : Créer interface + implémentation InvoiceNumberGenerator
  - `src/Service/NumberGenerator/InvoiceNumberGeneratorInterface.php`
  - `src/Service/NumberGenerator/InvoiceNumberGenerator.php`
  - Lock Doctrine
  - Calcul exercice comptable
  - Les tests doivent passer

**✅ Validation Task 55-56** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (17 tests, 52 assertions)

### PaymentManager

- [x] 57. TEST : Tests pour PaymentManager
  - `tests/Functional/Service/PaymentManagerTest.php`
  - 18 tests couvrant : enregistrement, status updates (PAID/PARTIALLY_PAID), events (InvoicePaidEvent/InvoicePartiallyPaidEvent)
  - Validation (DRAFT/CANCELLED rejetés), optional fields (reference, notes)
  - Edge cases (overpayment, zero payment, multiple partial payments)
  - Les tests définissent le contrat

- [x] 58. CODE : Implémenter PaymentManager
  - `src/Service/PaymentManager.php` + `PaymentManagerInterface.php`
  - recordPayment(): création Payment, lien Invoice, update status, dispatch events
  - Validation status, EntityManager persistence, EventDispatcher integration
  - Les tests passent (18/18)

**✅ Validation Task 57-58** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (376 tests, 848 assertions)

### PdfGenerator

- [x] 59. TEST : Tests pour TwigPdfGenerator
  - `tests/Functional/Service/Pdf/TwigPdfGeneratorTest.php`
  - Génération PDF
  - Contenu présent (données facture)
  - Format correct
  - Les tests définissent le contrat de l'interface

- [x] 60. CODE : Créer template Twig + interface + implémentation
  - `templates/invoice/pdf.html.twig` (blocs overridables)
  - `src/Service/Pdf/PdfGeneratorInterface.php`
  - `src/Service/Pdf/TwigPdfGenerator.php`
  - Integration DomPDF
  - Les tests doivent passer

**✅ Validation Task 59-60** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (388 tests, 873 assertions)

### PdfStorage

- [x] 61. TEST : Tests pour FilesystemPdfStorage
  - `tests/Functional/Service/Pdf/Storage/FilesystemPdfStorageTest.php`
  - Store
  - Retrieve
  - Organisation par date
  - Les tests définissent le contrat de l'interface

- [x] 62. CODE : Créer interface + implémentation FilesystemPdfStorage
  - `src/Service/Pdf/Storage/PdfStorageInterface.php`
  - `src/Service/Pdf/Storage/FilesystemPdfStorage.php`
  - Les tests doivent passer

**✅ Validation Task 61-62** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (409 tests, 916 assertions)

### InvoiceManager

- [x] 63. TEST : Tests pour InvoiceManager (complet)
  - `tests/Functional/Service/InvoiceManagerTest.php`
  - 47 tests couvrant toutes les opérations:
    * Création facture/avoir avec snapshots (15 tests)
    * Ajout lignes avec validation (10 tests)
    * Modification brouillon champs mutables (7 tests)
    * Annulation DRAFT uniquement (7 tests)
    * Validations strictes (8 tests)

- [x] 64. CODE : Implémenter InvoiceManager (complet)
  - `src/Service/InvoiceManager.php` + `InvoiceManagerInterface.php`
  - Toutes les méthodes implémentées:
    * createInvoice() - Snapshots company/customer data
    * createCreditNote() - Avec lien facture optionnel
    * addLine() - DRAFT uniquement
    * updateInvoice() - Champs mutables uniquement (email, phone, terms, dueDate, discount)
    * cancelInvoice() - DRAFT uniquement avec raison optionnelle
  - Validation stricte (noms/adresses requis, due date >= invoice date)
  - Events dispatched (4 types: Created, Updated, Cancelled, CreditNote)
  - Les tests passent (47/47)

**Note**: Tasks 65-68 initialement prévues (mise à jour/annulation séparées) ont été intégrées dans l'implémentation complète Tasks 63-64

**✅ Validation Task 63-64** : PHPStan niveau 9 (0 erreurs) + CS Fixer (100%) + Tests 100% (456 tests, 1003 assertions)

### InvoiceFinalizer

- [ ] 65. TEST : Tests pour InvoiceFinalizer
  - `tests/Functional/Service/InvoiceFinalizerTest.php`
  - Finalisation complète
  - Transaction atomique
  - Rollback sur échec PDF
  - Rollback sur échec storage
  - Numéro attribué
  - PDF généré et stocké
  - Events

- [ ] 66. CODE : Implémenter InvoiceFinalizer
  - `src/Service/InvoiceFinalizer.php`
  - Transaction complète
  - Gestion erreurs
  - Les tests doivent passer

**✓ Validation Phase 7** : PHPStan + CS Fixer + Tests 100%

---

## 🚀 Phase 8 : Features Avancées - TDD

### Factur-X

- [ ] 67. TEST : Tests pour FacturXGenerator
  - `tests/Functional/Service/FacturX/FacturXGeneratorTest.php`
  - Génération XML
  - Embarquement dans PDF
  - Validation format EN 16931
  - Les tests définissent le contrat de l'interface

- [ ] 68. CODE : Créer interface + implémentation FacturXGenerator
  - `src/Service/FacturX/FacturXGeneratorInterface.php`
  - `src/Service/FacturX/FacturXGenerator.php`
  - XML EN 16931
  - Profiles (BASIC, etc.)
  - Les tests doivent passer

- [ ] 69. TEST : Tests pour intégration Factur-X dans InvoiceFinalizer
  - Factur-X activé → PDF avec XML
  - Factur-X désactivé → PDF standard

- [ ] 70. CODE : Intégrer Factur-X dans InvoiceFinalizer
  - Option config facturx.enabled
  - Utiliser FacturXGenerator si activé
  - Les tests doivent passer

### Export FEC

- [ ] 71. TEST : Tests pour FecExporter
  - `tests/Functional/Service/Fec/FecExporterTest.php`
  - Format CSV correct
  - 18 colonnes conformes
  - Séparateur |
  - Calculs corrects (montants Money)
  - Les tests définissent le contrat de l'interface

- [ ] 72. CODE : Créer interface + implémentation FecExporter
  - `src/Service/Fec/FecExporterInterface.php`
  - `src/Service/Fec/FecExporter.php`
  - Les tests doivent passer

- [ ] 73. TEST : Tests pour ExportFecCommand
  - `tests/Functional/Command/ExportFecCommandTest.php`
  - Arguments (exercice, société)
  - Output généré
  - Contenu valide

- [ ] 74. CODE : Implémenter ExportFecCommand
  - `src/Command/ExportFecCommand.php`
  - Les tests doivent passer

**✓ Validation Phase 8** : PHPStan + CS Fixer + Tests 100%

---

## 🔧 Phase 9 : Configuration & Intégration - TDD

- [ ] 75. TEST : Tests d'intégration pour configuration bundle
  - `tests/Functional/DependencyInjection/InvoiceBundleExtensionTest.php`
  - Chargement des paramètres YAML
  - Valeurs par défaut
  - Services autowirés
  - Aliases corrects
  - Enregistrement MoneyType Doctrine

- [ ] 76. CODE : Compléter Configuration.php + services.yaml
  - `src/DependencyInjection/Configuration.php`
  - `config/services.yaml`
  - Tous les paramètres YAML
  - Autowiring complet
  - Tags et Aliases
  - Les tests doivent passer

- [ ] 77. TEST : Tests pour schéma Doctrine
  - `tests/Functional/Entity/SchemaValidationTest.php`
  - Validation du schéma
  - Contraintes uniques
  - Index
  - Type Money enregistré

- [ ] 78. CODE : Créer les migrations Doctrine
  - Pour toutes les entités
  - Script propre
  - Les tests doivent passer

- [ ] 79. TEST : Test d'intégration complet end-to-end
  - `tests/Functional/Integration/CompleteInvoiceWorkflowTest.php`
  - Créer facture → Finaliser → Payer → Export FEC
  - Workflow complet avec tous les services
  - Vérifier calculs Money corrects

**✓ Validation Phase 9** : PHPStan + CS Fixer + Tests 100%

---

## 📚 Phase 10 : Documentation & Validation finale

- [ ] 80. Mettre à jour README.md
  - Installation
  - Configuration
  - Utilisation avec Money
  - Tests

- [ ] 81. Créer USAGE.md
  - Exemples concrets avec Money
  - Cas d'usage
  - Extension

- [ ] 82. VALIDATION FINALE : PHPStan niveau 9
  - 0 erreurs
  - 0 warnings

- [ ] 83. VALIDATION FINALE : Couverture de code > 90%
  - `make test-coverage`
  - Vérifier toutes les branches

---

## 📊 Statistiques

- **Total tâches** : 83 (Tasks 65-68 fusionnées dans 63-64)
- **Tâches complétées** : 64 (Phases 0-6 + Tasks 55-64)
- **Progression** : 77.1%

**Phase 7 Résultats (Tasks 55-64)** :
- InvoiceNumberGenerator : Génération numéros fiscaux thread-safe (17 tests)
- PaymentManager : Gestion paiements avec events (18 tests)
- TwigPdfGenerator : Génération PDF avec DomPDF + templates Twig (12 tests)
- FilesystemPdfStorage : Stockage filesystem avec flock + sécurité (21 tests)
- InvoiceManager : Gestion complète factures/avoirs avec snapshots (47 tests)
- 456 tests au total (1003 assertions)
- PHPStan niveau 9 : 0 erreurs
- CS Fixer : 100% conforme

**Phase 3 Résultats** :
- 5 entités implémentées (InvoiceSequence, InvoiceLine, Payment, Invoice, InvoiceHistory)
- 260 tests unitaires (570 assertions)
- PHPStan niveau 9 : 0 erreurs
- CS Fixer : 100% conforme
- Conformité légale française : TVA calculée après remise globale

---

## 🎯 Prochaine étape

👉 **Phase 7 - Tâche 65** : TEST - Écrire les tests pour InvoiceFinalizer

**Points clés InvoiceFinalizer** :
- Transaction atomique complète (numéro + PDF + storage)
- Rollback automatique sur échec
- Coordination InvoiceNumberGenerator + PdfGenerator + PdfStorage
- Events dispatched (InvoiceFinalizedEvent, InvoicePdfGeneratedEvent)

## 📐 Principes TDD appliqués

Pour chaque composant :
1. ✅ **RED** : Écrire le test (qui échoue)
2. ✅ **GREEN** : Écrire le code minimum pour passer le test
3. ✅ **REFACTOR** : Améliorer le code (DRY) sans casser les tests

**Important sur les interfaces :**
- Les interfaces ne sont **jamais créées seules**
- Elles sont créées **avec leur première implémentation**
- Les tests de l'implémentation **définissent le contrat** de l'interface
- Exception : Interfaces sans implémentation bundle (UserProviderInterface) → implémentées par l'app

Validation continue après chaque phase : PHPStan 9 + CS Fixer + Tests 100%
