# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Estate Calculator API - A Symfony 7.3 backend for real estate insurance policy calculation and management. Provides RESTful API endpoints for insurance calculations, policy creation, and admin operations.

**Production API URL:** https://propcalc.zastrahovaite.com/

## Common Commands

```bash
# Install dependencies
composer install

# Run development server
symfony server:start

# Database operations
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate

# Seed database (order matters for foreign keys)
php bin/console app:seed-all

# Individual seed commands (in dependency order):
php bin/console app:seed-earthquake-zones
php bin/console app:seed-app-configs
php bin/console app:seed-estate-types
php bin/console app:seed-settlements
php bin/console app:seed-water-distances
php bin/console app:seed-insurance-clauses
php bin/console app:seed-tariff-presets
php bin/console app:seed-tariff-preset-clauses
php bin/console app:seed-person-roles
php bin/console app:seed-id-number-types
php bin/console app:seed-property-checklists
php bin/console app:seed-nationalities

# Create admin user
php bin/console app:create-user

# Maintenance
php bin/console app:purge-anonymous-users --dry-run   # anonymous users older than 7 days (--older-than N, --include-undated)
php bin/console app:remove-duplicate-settlements

# Tests (see tests/README.md)
composer test:setup       # once: throwaway JWT keys in var/jwt/ + propcalc_test DB via .env.test.local
composer test             # full suite; or vendor/bin/phpunit --testsuite Unit|Integration|Api

# Clear cache
php bin/console cache:clear

# Generate JWT keys
./generate_jwt_keys.sh

# Deploy to production (rsync/SSH; preview + "yes" prompt)
./deploy.sh -n            # dry run: show what would change
./deploy.sh               # deploy HEAD
./deploy.sh --ref <tag>   # deploy a specific ref; --no-migrate to skip migrations

# Replace the local DB with a copy of production (DROPs the local DB)
./sync-prod-db.sh
```

## Deployment

Production: `teodor81@91.215.216.12:22022`, `/home/teodor81/propcalc.zastrahovaite.com`, PHP `/usr/local/php8.3/bin/php`, MariaDB 10.6. The website runs **PHP 8.3** (cPanel MultiPHP), so all CLI steps must use 8.3 too: a cache compiled on 8.4 breaks the site with a ParseError.

- **`deploy.sh`** ships a clean `git archive` of the ref (only tracked files), minus `deploy-excludes.txt`. Then, on the server, it runs `composer.phar install --no-dev` and `doctrine:migrations:migrate`. `vendor/`, `var/`, `.env*`, `config/jwt/` and `public/.htaccess` are server-owned and never uploaded.
- **rsync never deletes.** A file removed from the repo stays on the server. The preflight refuses to deploy if the server has migration files the ref doesn't. If that happens, move the stale file out of `migrations/` on the server.
- **`sync-prod-db.sh`** streams `mysqldump` from prod over SSH (prod credentials are resolved on the server and never leave it). It imports into the local Homebrew MySQL 8.4 from `.env`'s `DATABASE_URL`, rewriting MariaDB-only bits in the stream. It refuses non-local hosts and `*_test` databases.
- Both scripts need SSH key access to the prod host. Neither stores credentials.
- The deploy ends with a smoke test (`POST /api/v1/auth/anonymous` through the live site must return 200).
- Cron on prod (user crontab, not in the repo): `app:purge-anonymous-users` daily at 03:30 with PHP 8.3, logging to `var/log/purge-anonymous-users.log`. Edit with `crontab -e` over SSH; the same crontab also runs the v3.zastrahovaite.com Laravel scheduler and a Softaculous backup — leave those alone.
- Email goes through Brevo (`MAILER_DSN` in the server's `.env`). The Brevo account restricts API calls to authorized IPs; prod sends from `79.124.30.10`.

## Testing

- PHPUnit suites: `tests/Unit` (no kernel), `tests/Integration` (kernel + seeded DB), `tests/Api` (full HTTP). Shared helpers in `tests/Support/`.
- `tests/bootstrap.php` rebuilds and seeds the test schema (`app:seed-all`) only when fixtures could have changed; `TEST_DB_REBUILD=1` forces it. It refuses any DB not ending in `_test`. Each test runs in a rolled-back transaction (`dama/doctrine-test-bundle`).
- Safety rails, enforced by `EnvironmentGuardTest`: `MAILER_DSN=null://null`, JWT keys from `var/jwt/` (never `config/jwt/`), outbound HTTP blocked by `NoNetworkStreamWrapper`.
- Tests suffixed `*_KNOWN_GAP` pin current, known-wrong behaviour. When fixing one, flip it to assert the correct behaviour and drop the suffix — never delete it.
- CI: `.github/workflows/tests.yml` runs the suite on PHP 8.2 and 8.4 against MySQL 8.4.

## Architecture

### API Structure
- **Public API** (`/api/v1/`): Client-facing endpoints for settlements, insurance policies, form data, promotional codes
- **Admin API**: spread over three prefixes — `/api/v1/admin/`, `/api/v1/insurance-policies/admin/`, `/api/v1/app-configs/admin/` — for managing policies, clauses, tariffs, users, promo codes and app config

### Authentication
- JWT-based authentication using `lexik/jwt-authentication-bundle`
- Anonymous tokens available at `/api/v1/auth/anonymous`
- Admin login at `/api/v1/admin/auth/login`
- Public (no token): `/api/v1/auth/anonymous`, `/api/v1/admin/auth/login`, `POST /api/v1/tariff/pdf` (exact path; `/tariff/pdf/email` needs a token)
- All three admin prefixes require `ROLE_ADMIN` in `access_control`, and each admin controller repeats it with `#[IsGranted]`. Anonymous tokens carry `ROLE_ANONYMOUS` + `ROLE_USER`.
- Everything else under `/api` needs any valid token. One deliberate exception: `GET /api/v1/insurance-policies/admin/tariff-presets` is open to anonymous tokens for the public calculator; `GET /api/v1/form-data/tariff-presets` serves the same payload and is the replacement.
- `access_control` paths are regexes — anchor with `$` when an exact match is meant; an unanchored prefix covers every path below it.

### Core Domain Entities
- **InsurancePolicy**: Main policy entity with insurer details, property info, financial calculations
- **InsuranceClause**: Insurance coverage types (fire, earthquake, flood, etc.)
- **TariffPreset**: Predefined tariff packages containing multiple clauses
- **TariffPresetClause**: Junction table linking presets to clauses with amounts
- **Settlement**: Geographic locations with earthquake zone associations
- **User**: admins and anonymous users (one row per anonymous token; `created_at` lets `app:purge-anonymous-users` age them out)
- 20 entities, 20 repositories

### Key Services
- **TariffPresetService** (`src/Service/TariffPresetService.php`): Calculates insurance premiums with earthquake zone adjustments, flood zone filtering, discounts, and taxes
- **StatisticsService** (`src/Service/StatisticsService.php`): Computes policy statistics (premium amounts, discounts, tax, totals)
- **EmailService** (`src/Service/EmailService.php`): Sends order confirmation emails and tariff offer PDFs
- **PdfService** (`src/Service/PdfService.php`): Generates policy and tariff offer PDF documents

### Other Key Files
- `src/Controller/Trait/ValidatesEntities.php` — shared validation error formatting trait (`validationErrors()` → 400 flat list; `fieldValidationErrors()` → 422 field map)
- `src/EventListener/CorsListener.php` — CORS handling for all API responses
- `src/Constants/AppConstants.php` — company name, admin email constants

### Business Logic Notes
- Earthquake tariff numbers are determined by settlement's earthquake zone
- Flood clauses are filtered based on distance to water (< 500m vs > 500m)
- Policy codes are auto-generated with format: P + zeros + ID + date + daily count
- App configs store system-wide values (TAX_PERCENTS, DISCOUNT_PERCENTS, EARTHQUAKE_ID, FLOOD_*_ID, CURRENCY)

### Related Frontends
- **Admin panel**: `/Applications/MAMP/htdocs/reactjs/procalc-admin/src` (React)
- **Public calculator**: `/Applications/MAMP/htdocs/reactjs/propcalc-client` (React), live at https://propcalc-dy7.pages.dev/

## Coding Conventions

### API Response Key Casing

Mixed convention exists — **match the existing casing in the controller you're editing, do not convert.**

| Controllers | Casing | Examples |
|-------------|--------|----------|
| `AppConfigController`, `AdminAuthController`, `UserManagementController`, `UserProfileController`, `PromotionalCodeController` (public + admin) | **camelCase** | `nameBg`, `isEditable`, `fullName`, `discountPercentage` |
| `InsuranceClauseController`, `TariffPresetController`, `TariffPresetService` | **snake_case** | `tariff_number`, `has_tariff_number`, `tariff_amount`, `insurance_clause` |

Admin panel sends **camelCase** field names for User/PromotionalCode endpoints, **snake_case** for InsuranceClause/TariffPreset endpoints.

### Error Response Formats

Three formats coexist — **match the existing format in the controller you're editing.** The `errors` envelope has two shapes: a flat list (400, entity constraint violations) or a field => messages map (422, payload checks before an entity is populated, e.g. tariff preset create).

| Format | Used by | Example |
|--------|---------|---------|
| `{'errors': ['msg1', 'msg2']}` (array) | `ValidatesEntities` trait (used by `AppConfigController`, `InsuranceClauseController`, `TariffPresetController`, `Admin PromotionalCodeController`) and `InsurancePolicyController` | Validation errors |
| `{'message': 'string'}` | `AdminAuthController`, `UserManagementController`, `UserProfileController`, `PromotionalCodeController` (public) | Auth/user operations |
| `{'error': 'string'}` | CRUD not-found/bad-request in most admin controllers, `FormDataController`, `TariffPdfController` | Resource lookup failures |

Note: Some controllers use multiple formats (e.g., `Admin PromotionalCodeController` uses both `errors` array from trait and `error` string for not-found).

### Entity Serialization

Two patterns coexist:
- `User`, `PromotionalCode` → have `toArray()` methods (camelCase keys). **If a `toArray()` exists, use it.**
- All other entities → serialized manually in controllers. Build arrays manually matching the controller's existing key casing pattern.

### Request Handling
- JSON body parsing: `json_decode($request->getContent(), true)`
- Query params: `$request->query->getInt()` / `$request->query->get()` with type cast
- `PromotionalCodeController::updateEntityFromData()` accepts both snake_case and camelCase input (backwards compatibility)

## Known Inconsistencies

These are intentional or legacy — do not "fix" them:
- `EarthquakeZone.tariff_number` property uses snake_case (all other entities use camelCase properties)
- Mixed casing across API endpoints (documented above)
- `PromotionalCodeController::updateEntityFromData()` accepts both snake_case and camelCase input for backwards compatibility