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

# Clear cache
php bin/console cache:clear

# Generate JWT keys
./generate_jwt_keys.sh
```

## Architecture

### API Structure
- **Public API** (`/api/v1/`): Client-facing endpoints for settlements, insurance policies, form data, promotional codes
- **Admin API** (`/api/v1/admin/`): Protected endpoints for managing policies, clauses, tariffs, users, and app config

### Authentication
- JWT-based authentication using `lexik/jwt-authentication-bundle`
- Anonymous tokens available at `/api/v1/auth/anonymous`
- Admin login at `/api/v1/admin/auth/login`
- All API routes require authentication except explicitly public endpoints (see `config/packages/security.yaml`)

### Core Domain Entities
- **InsurancePolicy**: Main policy entity with insurer details, property info, financial calculations
- **InsuranceClause**: Insurance coverage types (fire, earthquake, flood, etc.)
- **TariffPreset**: Predefined tariff packages containing multiple clauses
- **TariffPresetClause**: Junction table linking presets to clauses with amounts
- **Settlement**: Geographic locations with earthquake zone associations

### Key Services
- **TariffPresetService** (`src/Service/TariffPresetService.php`): Calculates insurance premiums with earthquake zone adjustments, flood zone filtering, discounts, and taxes
- **EmailService**: Sends order confirmation emails
- **PdfService**: Generates policy PDF documents

### Business Logic Notes
- Earthquake tariff numbers are determined by settlement's earthquake zone
- Flood clauses are filtered based on distance to water (< 500m vs > 500m)
- Policy codes are auto-generated with format: P + zeros + ID + date + daily count
- App configs store system-wide values (TAX_PERCENTS, DISCOUNT_PERCENTS, EARTHQUAKE_ID, FLOOD_*_ID, CURRENCY)

### CORS
Custom `CorsListener` handles CORS headers for all API responses.