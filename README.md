# Invoice Bundle

Bundle Symfony pour la gestion de factures et avoirs conformes à la réglementation française.

## Installation

```bash
composer require corentinboutillier/invoice-bundle
```

## Tests

Le bundle dispose d'une suite de tests complète inspirée d'API Platform Core.

### Structure des tests

```
tests/
├── bootstrap.php              # Bootstrap PHPUnit
├── Fixtures/
│   └── TestKernel.php        # Kernel Symfony minimal pour les tests
├── Unit/                     # Tests unitaires (logique métier pure)
└── Functional/               # Tests d'intégration (avec Symfony + Doctrine)
```

### Lancer les tests

```bash
# Depuis la racine du projet
make test-unit              # Tests unitaires uniquement
make test-integration       # Tests d'intégration (nécessite Docker)
make test                   # Tous les tests

# Ou directement dans le bundle
cd bundle
composer install
vendor/bin/phpunit
```

### Tests avec couverture de code

```bash
make test-coverage
# Rapport dans bundle/coverage/index.html
```

## Qualité de code

### PHPStan (niveau 9)

```bash
make phpstan
# ou
cd bundle && vendor/bin/phpstan analyse
```

### PHP CS Fixer

```bash
make cs-fix      # Corriger le code
make cs-check    # Vérifier sans corriger
```

### Tout en une commande

```bash
make qa          # PHPStan + CS Fixer + Tests unitaires
```

## Configuration

Le bundle utilise une configuration minimale par défaut. Pour personnaliser :

```yaml
# config/packages/invoice.yaml
invoice:
    # Configuration à venir
```

## Développement

Pour contribuer au bundle, utilisez l'environnement de développement fourni :

```bash
# Depuis la racine du projet
make install     # Installer et démarrer l'environnement
make test        # Lancer tous les tests
make qa          # Vérifier la qualité du code
```

## License

MIT
