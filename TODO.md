# TODO - Invoice Bundle Implementation (TDD & DRY)

## Principes
- ✅ **TDD** : Test-Driven Development - Tests AVANT le code
- ✅ **DRY** : Don't Repeat Yourself - Éviter toute duplication
- ✅ **Qualité dès le début** : PHPStan 9, CS Fixer, couverture 100%

---

## Progression globale
- [x] Phase 0 : Setup Qualité & Tests (5 tâches) - Tâches 1-5
- [ ] Phase 1 : Enums - TDD (8 tâches) - Tâches 6-13
- [ ] Phase 2 : DTOs - TDD (4 tâches) - Tâches 14-17
- [ ] Phase 3 : Entités - TDD (22 tâches) - Tâches 18-39
- [ ] Phase 4 : Repositories - TDD (4 tâches) - Tâches 40-43
- [ ] Phase 5 : Providers & Interfaces - TDD (5 tâches) - Tâches 44-48
- [ ] Phase 6 : Events & Subscribers - TDD (3 tâches) - Tâches 49-51
- [ ] Phase 7 : Services Métier - TDD (16 tâches) - Tâches 52-67
- [ ] Phase 8 : Features Avancées - TDD (8 tâches) - Tâches 68-75
- [ ] Phase 9 : Configuration & Intégration - TDD (5 tâches) - Tâches 76-80
- [ ] Phase 10 : Documentation & Validation finale (4 tâches) - Tâches 81-84

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

- [ ] 6. TEST : Écrire les tests pour InvoiceStatus
  - `tests/Unit/Enum/InvoiceStatusTest.php`
  - Tous les cas disponibles
  - Values correctes

- [ ] 7. CODE : Implémenter InvoiceStatus
  - `src/Enum/InvoiceStatus.php`
  - Les tests doivent passer

### InvoiceType

- [ ] 8. TEST : Écrire les tests pour InvoiceType
  - `tests/Unit/Enum/InvoiceTypeTest.php`

- [ ] 9. CODE : Implémenter InvoiceType
  - `src/Enum/InvoiceType.php`

### PaymentMethod

- [ ] 10. TEST : Écrire les tests pour PaymentMethod
  - `tests/Unit/Enum/PaymentMethodTest.php`

- [ ] 11. CODE : Implémenter PaymentMethod
  - `src/Enum/PaymentMethod.php`

### InvoiceHistoryAction

- [ ] 12. TEST : Écrire les tests pour InvoiceHistoryAction
  - `tests/Unit/Enum/InvoiceHistoryActionTest.php`

- [ ] 13. CODE : Implémenter InvoiceHistoryAction
  - `src/Enum/InvoiceHistoryAction.php`

**✓ Validation Phase 1** : PHPStan + CS Fixer + Tests 100%

---

## 📦 Phase 2 : DTOs - TDD

### CompanyData

- [ ] 14. TEST : Écrire les tests pour CompanyData
  - `tests/Unit/DTO/CompanyDataTest.php`
  - Construction
  - Tous les champs

- [ ] 15. CODE : Implémenter CompanyData
  - `src/DTO/CompanyData.php`
  - Readonly properties

### CustomerData

- [ ] 16. TEST : Écrire les tests pour CustomerData
  - `tests/Unit/DTO/CustomerDataTest.php`

- [ ] 17. CODE : Implémenter CustomerData
  - `src/DTO/CustomerData.php`

**✓ Validation Phase 2** : PHPStan + CS Fixer + Tests 100%

---

## 🗄️ Phase 3 : Entités - TDD

### InvoiceSequence

- [ ] 18. TEST : Écrire les tests pour InvoiceSequence
  - `tests/Unit/Entity/InvoiceSequenceTest.php`
  - Contrainte unique
  - Incrémentation

- [ ] 19. CODE : Implémenter InvoiceSequence
  - `src/Entity/InvoiceSequence.php`

### InvoiceLine (calculs simples d'abord)

- [ ] 20. TEST : Tests pour InvoiceLine (sans remises)
  - `tests/Unit/Entity/InvoiceLineTest.php`
  - Création
  - Total HT simple (quantité × prix)

- [ ] 21. CODE : Implémenter InvoiceLine (structure de base)
  - `src/Entity/InvoiceLine.php`
  - Champs de base, pas encore de calculs complexes

- [ ] 22. TEST : Tests pour remises sur InvoiceLine
  - Remise en %
  - Remise en montant fixe
  - Prix après remise
  - Total HT après remise

- [ ] 23. CODE : Ajouter les méthodes de calcul des remises
  - `getUnitPriceAfterDiscount()`
  - `getTotalBeforeVat()`

- [ ] 24. TEST : Tests pour TVA sur InvoiceLine
  - Calcul montant TVA
  - Total TTC

- [ ] 25. CODE : Ajouter les méthodes de calcul TVA
  - `getVatAmount()`
  - `getTotalIncludingVat()`

### Payment

- [ ] 26. TEST : Tests pour Payment
  - `tests/Unit/Entity/PaymentTest.php`
  - Création
  - Relation Invoice

- [ ] 27. CODE : Implémenter Payment
  - `src/Entity/Payment.php`
  - Extensible (SINGLE_TABLE inheritance)

### Invoice (structure puis calculs)

- [ ] 28. TEST : Tests pour Invoice (structure de base)
  - `tests/Unit/Entity/InvoiceTest.php`
  - Création
  - Ajout de lignes
  - Ajout de paiements

- [ ] 29. CODE : Implémenter Invoice (structure de base)
  - `src/Entity/Invoice.php`
  - Champs, relations, pas encore de calculs

- [ ] 30. TEST : Tests pour calculs simples Invoice
  - Sous-total (somme lignes HT)
  - Total TVA
  - Total TTC

- [ ] 31. CODE : Implémenter calculs simples
  - `getSubtotalBeforeDiscount()`
  - `getTotalVat()`
  - `getTotalIncludingVat()`

- [ ] 32. TEST : Tests pour remise globale Invoice
  - Remise globale %
  - Remise globale montant
  - Total après remise globale

- [ ] 33. CODE : Implémenter remise globale
  - `getGlobalDiscountValue()`
  - `getTotalExcludingVat()`

- [ ] 34. TEST : Tests pour paiements Invoice
  - Total payé
  - Reste à payer
  - isFullyPaid()
  - isPartiallyPaid()

- [ ] 35. CODE : Implémenter méthodes paiements
  - `getTotalPaid()`
  - `getRemainingAmount()`
  - `isFullyPaid()`
  - `isPartiallyPaid()`

- [ ] 36. TEST : Tests pour échéances Invoice
  - isOverdue()
  - getDaysOverdue()

- [ ] 37. CODE : Implémenter méthodes échéances
  - `isOverdue()`
  - `getDaysOverdue()`

### InvoiceHistory

- [ ] 38. TEST : Tests pour InvoiceHistory
  - `tests/Unit/Entity/InvoiceHistoryTest.php`
  - Création
  - Données JSON

- [ ] 39. CODE : Implémenter InvoiceHistory
  - `src/Entity/InvoiceHistory.php`

**✓ Validation Phase 3** : PHPStan + CS Fixer + Tests 100%

---

## 📂 Phase 4 : Repositories - TDD

- [ ] 40. TEST : Tests pour InvoiceRepository
  - `tests/Functional/Repository/InvoiceRepositoryTest.php`
  - Méthodes custom de recherche

- [ ] 41. CODE : Implémenter InvoiceRepository
  - `src/Repository/InvoiceRepository.php`
  - Requêtes optimisées

- [ ] 42. TEST : Tests pour InvoiceSequenceRepository
  - Lock pour numérotation
  - findForUpdate()

- [ ] 43. CODE : Implémenter InvoiceSequenceRepository
  - `src/Repository/InvoiceSequenceRepository.php`

**Note** : PaymentRepository et InvoiceHistoryRepository basiques (pas de tests si pas de logique custom)

**✓ Validation Phase 4** : PHPStan + CS Fixer + Tests 100%

---

## 🔌 Phase 5 : Providers & Interfaces - TDD

### CompanyProvider

- [ ] 44. TEST : Tests pour ConfigCompanyProvider
  - `tests/Unit/Provider/ConfigCompanyProviderTest.php`
  - Mock de configuration
  - Les tests définissent le contrat de l'interface

- [ ] 45. CODE : Créer interface CompanyProviderInterface + implémentation
  - `src/Provider/CompanyProviderInterface.php`
  - `src/Provider/ConfigCompanyProvider.php`
  - Les tests doivent passer

### UserProvider

- [ ] 46. CODE : Créer interface UserProviderInterface (simple contrat, pas d'implémentation)
  - `src/Provider/UserProviderInterface.php`
  - Sera implémenté par l'app cliente

### DueDateCalculator

- [ ] 47. TEST : Tests pour DueDateCalculator
  - `tests/Unit/Service/DueDateCalculatorTest.php`
  - 30j net
  - 45j fin de mois
  - Comptant
  - Les tests définissent le contrat de l'interface

- [ ] 48. CODE : Créer interface + implémentation DueDateCalculator
  - `src/Service/DueDateCalculatorInterface.php`
  - `src/Service/DueDateCalculator.php`
  - Les tests doivent passer

**✓ Validation Phase 5** : PHPStan + CS Fixer + Tests 100%

---

## 🔔 Phase 6 : Events & Subscribers - TDD

### InvoiceHistorySubscriber (TDD)

- [ ] 49. TEST : Tests pour InvoiceHistorySubscriber
  - `tests/Unit/EventSubscriber/InvoiceHistorySubscriberTest.php`
  - Mock des Events (définir leur structure dans les tests)
  - Mock EventDispatcher
  - Vérifier enregistrement history pour chaque type d'event

- [ ] 50. CODE : Créer les Events nécessaires
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

- [ ] 51. CODE : Implémenter InvoiceHistorySubscriber
  - `src/EventSubscriber/InvoiceHistorySubscriber.php`
  - Les tests doivent passer

**✓ Validation Phase 6** : PHPStan + CS Fixer + Tests 100%

---

## ⚙️ Phase 7 : Services Métier - TDD

### InvoiceNumberGenerator

- [ ] 52. TEST : Tests pour InvoiceNumberGenerator
  - `tests/Functional/Service/NumberGenerator/InvoiceNumberGeneratorTest.php`
  - Format par défaut
  - Exercice comptable
  - Séquence par société
  - Thread-safe (concurrence)
  - Les tests définissent le contrat de l'interface

- [ ] 53. CODE : Créer interface + implémentation InvoiceNumberGenerator
  - `src/Service/NumberGenerator/InvoiceNumberGeneratorInterface.php`
  - `src/Service/NumberGenerator/InvoiceNumberGenerator.php`
  - Lock Doctrine
  - Calcul exercice comptable
  - Les tests doivent passer

### PaymentManager

- [ ] 54. TEST : Tests pour PaymentManager
  - `tests/Functional/Service/PaymentManagerTest.php`
  - Enregistrement paiement
  - Mise à jour statut
  - Events dispatché
  - Les tests définissent le contrat

- [ ] 55. CODE : Implémenter PaymentManager
  - `src/Service/PaymentManager.php`
  - Les tests doivent passer

### PdfGenerator

- [ ] 56. TEST : Tests pour TwigPdfGenerator
  - `tests/Functional/Service/Pdf/TwigPdfGeneratorTest.php`
  - Génération PDF
  - Contenu présent (données facture)
  - Format correct
  - Les tests définissent le contrat de l'interface

- [ ] 57. CODE : Créer template Twig + interface + implémentation
  - `templates/invoice/pdf.html.twig` (blocs overridables)
  - `src/Service/Pdf/PdfGeneratorInterface.php`
  - `src/Service/Pdf/TwigPdfGenerator.php`
  - Integration DomPDF
  - Les tests doivent passer

### PdfStorage

- [ ] 58. TEST : Tests pour FilesystemPdfStorage
  - `tests/Functional/Service/Pdf/Storage/FilesystemPdfStorageTest.php`
  - Store
  - Retrieve
  - Organisation par date
  - Les tests définissent le contrat de l'interface

- [ ] 59. CODE : Créer interface + implémentation FilesystemPdfStorage
  - `src/Service/Pdf/Storage/PdfStorageInterface.php`
  - `src/Service/Pdf/Storage/FilesystemPdfStorage.php`
  - Les tests doivent passer

### InvoiceManager

- [ ] 60. TEST : Tests pour InvoiceManager (création)
  - `tests/Functional/Service/InvoiceManagerTest.php`
  - Création facture
  - Création avoir
  - Ajout lignes
  - Calculs automatiques

- [ ] 61. CODE : Implémenter InvoiceManager (partie création)
  - `src/Service/InvoiceManager.php`
  - createInvoice()
  - createCreditNote()
  - addLine()
  - Les tests doivent passer

- [ ] 62. TEST : Tests pour InvoiceManager (mise à jour)
  - Modification brouillon
  - Interdiction modification finalisée

- [ ] 63. CODE : Implémenter InvoiceManager (mise à jour)
  - updateInvoice()
  - Validations
  - Les tests doivent passer

- [ ] 64. TEST : Tests pour InvoiceManager (annulation)
  - Annulation avec raison
  - Event dispatché

- [ ] 65. CODE : Implémenter InvoiceManager (annulation)
  - cancelInvoice()
  - Les tests doivent passer

### InvoiceFinalizer

- [ ] 66. TEST : Tests pour InvoiceFinalizer
  - `tests/Functional/Service/InvoiceFinalizerTest.php`
  - Finalisation complète
  - Transaction atomique
  - Rollback sur échec PDF
  - Rollback sur échec storage
  - Numéro attribué
  - PDF généré et stocké
  - Events

- [ ] 67. CODE : Implémenter InvoiceFinalizer
  - `src/Service/InvoiceFinalizer.php`
  - Transaction complète
  - Gestion erreurs
  - Les tests doivent passer

**✓ Validation Phase 7** : PHPStan + CS Fixer + Tests 100%

---

## 🚀 Phase 8 : Features Avancées - TDD

### Factur-X

- [ ] 68. TEST : Tests pour FacturXGenerator
  - `tests/Functional/Service/FacturX/FacturXGeneratorTest.php`
  - Génération XML
  - Embarquement dans PDF
  - Validation format EN 16931
  - Les tests définissent le contrat de l'interface

- [ ] 69. CODE : Créer interface + implémentation FacturXGenerator
  - `src/Service/FacturX/FacturXGeneratorInterface.php`
  - `src/Service/FacturX/FacturXGenerator.php`
  - XML EN 16931
  - Profiles (BASIC, etc.)
  - Les tests doivent passer

- [ ] 70. TEST : Tests pour intégration Factur-X dans InvoiceFinalizer
  - Factur-X activé → PDF avec XML
  - Factur-X désactivé → PDF standard

- [ ] 71. CODE : Intégrer Factur-X dans InvoiceFinalizer
  - Option config facturx.enabled
  - Utiliser FacturXGenerator si activé
  - Les tests doivent passer

### Export FEC

- [ ] 72. TEST : Tests pour FecExporter
  - `tests/Functional/Service/Fec/FecExporterTest.php`
  - Format CSV correct
  - 18 colonnes conformes
  - Séparateur |
  - Calculs corrects
  - Les tests définissent le contrat de l'interface

- [ ] 73. CODE : Créer interface + implémentation FecExporter
  - `src/Service/Fec/FecExporterInterface.php`
  - `src/Service/Fec/FecExporter.php`
  - Les tests doivent passer

- [ ] 74. TEST : Tests pour ExportFecCommand
  - `tests/Functional/Command/ExportFecCommandTest.php`
  - Arguments (exercice, société)
  - Output généré
  - Contenu valide

- [ ] 75. CODE : Implémenter ExportFecCommand
  - `src/Command/ExportFecCommand.php`
  - Les tests doivent passer

**✓ Validation Phase 8** : PHPStan + CS Fixer + Tests 100%

---

## 🔧 Phase 9 : Configuration & Intégration - TDD

- [ ] 76. TEST : Tests d'intégration pour configuration bundle
  - `tests/Functional/DependencyInjection/InvoiceBundleExtensionTest.php`
  - Chargement des paramètres YAML
  - Valeurs par défaut
  - Services autowirés
  - Aliases corrects

- [ ] 77. CODE : Compléter Configuration.php + services.yaml
  - `src/DependencyInjection/Configuration.php`
  - `config/services.yaml`
  - Tous les paramètres YAML
  - Autowiring complet
  - Tags et Aliases
  - Les tests doivent passer

- [ ] 78. TEST : Tests pour schéma Doctrine
  - `tests/Functional/Entity/SchemaValidationTest.php`
  - Validation du schéma
  - Contraintes uniques
  - Index

- [ ] 79. CODE : Créer les migrations Doctrine
  - Pour toutes les entités
  - Script propre
  - Les tests doivent passer

- [ ] 80. TEST : Test d'intégration complet end-to-end
  - `tests/Functional/Integration/CompleteInvoiceWorkflowTest.php`
  - Créer facture → Finaliser → Payer → Export FEC
  - Workflow complet avec tous les services

**✓ Validation Phase 9** : PHPStan + CS Fixer + Tests 100%

---

## 📚 Phase 10 : Documentation & Validation finale

- [ ] 81. Mettre à jour README.md
  - Installation
  - Configuration
  - Utilisation
  - Tests

- [ ] 82. Créer USAGE.md
  - Exemples concrets
  - Cas d'usage
  - Extension

- [ ] 83. VALIDATION FINALE : PHPStan niveau 9
  - 0 erreurs
  - 0 warnings

- [ ] 84. VALIDATION FINALE : Couverture de code > 90%
  - `make test-coverage`
  - Vérifier toutes les branches

---

## 📊 Statistiques

- **Total tâches** : 84
- **Tâches complétées** : 0
- **Progression** : 0%

---

## 🎯 Prochaine étape

👉 **Phase 0 - Tâche 1** : Vérifier et ajuster phpstan.neon (niveau 9)

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
