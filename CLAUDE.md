# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Symfony Bundle** for invoice and credit note management compliant with French legal regulations. It's designed to be:
- Reusable across multiple client projects (80% use case)
- Decoupled from application entities (no dependencies on Company, Customer, User)
- Published "as-is" on GitHub without maintenance guarantees

**Key principle:** Invoice entities contain **snapshots** of data at creation time - NO Doctrine relations to external entities.

## Development Commands

### Quality & Testing (Run from bundle/ directory)

```bash
# Unit tests (isolated, no Symfony/Docker needed)
composer test
# or directly: vendor/bin/phpunit

# Static analysis (PHPStan level 9 - STRICT)
composer phpstan
# or: vendor/bin/phpstan analyse

# Code style check
composer cs-check

# Code style fix
composer cs-fix

# Test coverage (HTML report in coverage/)
composer test-coverage

# Quality check ALL (PHPStan + CS Fixer + Tests)
cd .. && make qa
```

### Integration Testing (Docker required, run from root)

```bash
# Integration tests (with full Symfony + Doctrine + Docker)
make test-integration

# All tests (unit + integration)
make test

# Start dev environment (API Platform)
make install
make start
```

### Single Test Execution

```bash
# Run specific test file
vendor/bin/phpunit tests/Unit/Entity/InvoiceTest.php

# Run specific test method
vendor/bin/phpunit --filter testCalculateTotalIncludingVat

# Functional test (requires TestKernel)
vendor/bin/phpunit tests/Functional/Service/InvoiceManagerTest.php
```

## Architecture Principles

### 1. Decoupling Strategy

**NO Doctrine relations** from bundle entities to app entities:
- `Invoice` stores **snapshots** (company name, customer address, etc.)
- App can create **reverse relations** if needed (e.g., Customer → OneToMany → Invoice)
- Bundle provides **Provider interfaces** for flexible data sourcing

### 2. Test-Driven Development (TDD) - STRICT

**Mandatory workflow for ALL code:**
1. **RED**: Write failing test first
2. **GREEN**: Write minimum code to pass
3. **REFACTOR**: Improve without breaking tests

**Critical rules:**
- Never write code without a failing test first
- Interfaces are created WITH their first implementation (tests define the contract)
- Exception: Interface-only contracts for app implementation (e.g., `UserProviderInterface`)
- Validation after each phase: PHPStan 9 + CS Fixer + Tests 100%

### 3. Entity Design

**Entities managed by bundle:**
- `Invoice` - Single entity for both invoices (INVOICE) and credit notes (CREDIT_NOTE)
- `InvoiceLine` - Invoice lines with VAT, discounts, calculations
- `Payment` - Extensible via Doctrine Single Table Inheritance
- `InvoiceSequence` - Thread-safe sequential numbering per fiscal year
- `InvoiceHistory` - Audit trail for all invoice actions

**Entities NOT managed:**
- Company, Customer, User, Product - App responsibility

### 4. French Legal Compliance

**Fiscal year numbering:**
- Sequences reset per **fiscal year** (NOT calendar year)
- Fiscal year configurable per company (e.g., Nov-Oct instead of Jan-Dec)
- Format: `FA-{YEAR}-{SEQUENCE}` for invoices, `AV-{YEAR}-{SEQUENCE}` for credit notes

**Legal features:**
- VAT rates (French defaults: 20%, 10%, 5.5%, 2.1%)
- Mandatory legal mentions (penalties, forfait recovery)
- FEC export (18 columns, pipe-separated)
- Factur-X support (PDF + EN 16931 XML)

### 5. Provider Pattern

Bundle uses providers for flexible data sourcing:

```php
// CompanyProvider: Company data (YAML config or custom implementation)
interface CompanyProviderInterface {
    public function getCompanyData(?int $companyId = null): CompanyData;
}

// UserProvider: Current user for audit trail (app implements)
interface UserProviderInterface {
    public function getCurrentUser(): ?UserData;
}
```

**DTOs used:**
- `CompanyData` - Issuer data (name, SIRET, VAT, address, banking)
- `CustomerData` - Customer snapshot (name, address, VAT)
- `UserData` - User info for history

### 6. Event-Driven Architecture

All state changes dispatch events:
- `InvoiceCreatedEvent`, `InvoiceFinalizedEvent`, `InvoicePaidEvent`
- `InvoiceHistorySubscriber` automatically logs all changes

Apps can subscribe to these events for custom logic.

### 7. PDF Generation & Storage

**Generation on finalization:**
- Atomic transaction: Number assignment + PDF generation + Storage
- Rollback on failure (e.g., storage permission error)
- Default template overridable via Twig inheritance

**Storage:**
- `PdfStorageInterface` - Filesystem by default, S3/custom via implementation
- Organization: `var/invoices/{YEAR}/{MONTH}/{invoice_number}.pdf`

## TestKernel

Bundle uses a **minimal Symfony kernel** for isolated testing (inspired by API Platform):
- Location: `tests/Fixtures/TestKernel.php`
- SQLite in-memory database
- Temporary cache directory
- Only essential bundles (FrameworkBundle, DoctrineBundle, InvoiceBundle)

No need for full Symfony project to run unit/functional tests.

## Code Quality Standards

**Enforced via tools:**
- PHP 8.3+ with strict types (`declare(strict_types=1)`)
- PHPStan level 9 (strictest)
- PHP CS Fixer with Symfony rules + trailing commas
- Test coverage target: >90%

**DRY principle:**
- Extract common logic to services
- Avoid code duplication in tests (use data providers, fixtures)
- Reusable calculation methods on entities

## Project Structure

```
bundle/
├── src/
│   ├── Entity/           # Invoice, InvoiceLine, Payment, InvoiceSequence, InvoiceHistory
│   ├── Enum/             # InvoiceStatus, InvoiceType, PaymentMethod, InvoiceHistoryAction
│   ├── DTO/              # CompanyData, CustomerData, UserData
│   ├── Repository/       # Custom queries, thread-safe locking
│   ├── Provider/         # CompanyProvider, UserProvider interfaces
│   ├── Service/          # Business logic (InvoiceManager, InvoiceFinalizer, PaymentManager)
│   │   ├── NumberGenerator/  # Fiscal year numbering
│   │   ├── Pdf/              # TwigPdfGenerator, PdfStorage
│   │   ├── FacturX/          # Factur-X generation
│   │   └── Fec/              # FEC export
│   ├── Event/            # Domain events
│   ├── EventSubscriber/  # InvoiceHistorySubscriber
│   └── DependencyInjection/  # Bundle configuration
├── tests/
│   ├── Unit/             # Pure logic tests (no Symfony)
│   ├── Functional/       # Tests with TestKernel + Doctrine
│   └── Fixtures/         # TestKernel, test data
├── config/               # services.yaml
├── templates/            # invoice/pdf.html.twig
├── ARCHITECTURE.md       # Complete architectural decisions (50+ KB)
└── TODO.md               # 84 TDD tasks in 10 phases
```

## Important Files to Reference

- **ARCHITECTURE.md** - Comprehensive design decisions, use cases, configuration examples
- **TODO.md** - Implementation roadmap with TDD workflow (84 tasks, phases 0-10)
- **README.md** - Installation, usage, testing instructions

