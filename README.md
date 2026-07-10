# api.selloff

Laravel 13 JSON API for the Selloff marketplace rewrite.

## Stack

- PHP 8.3+ with `bcmath` extension, Laravel 13 (lock pinned to Symfony 7 for Homestead PHP 8.3)
- PostgreSQL (canonical)
- Sanctum + Spatie Permission + Spatie Media Library
- Modular packages under `packages/selloff/{Module}/`

## Setup

```bash
createdb selloff   # PostgreSQL

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_CONNECTION=pgsql, DB_DATABASE=selloff, FRONTEND_URL, AWS_* for S3

RUN_DEMO_SEEDER=true php artisan selloff:migrate --fresh --seed
php artisan serve
```

### SPA + API (local demo)

Run the API on port 8000 and the SPA with `npm run dev`. In `app.selloff/.env` use:

```
VITE_API_BASE_URL=/api/v1
```

Vite proxies `/api` → `http://localhost:8000`, so the browser stays on `http://localhost:5173` and **CORS is not involved**.

If you call the API by full URL (e.g. `https://api.selloff.local/api/v1`), set `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS` in this `.env` and ensure nginx points at `public/` with Laravel routing — see [`../docs/HOMESTEAD_NGINX.md`](../docs/HOMESTEAD_NGINX.md) (`Primary script unknown` = wrong `root` or missing `try_files`).

Demo accounts (password: `password`):

| Email | Role |
|-------|------|
| `superadmin@selloff.test` | Admin |
| `vendor@selloff.test` | Vendor (Demo Electronics) |
| `vendor2@selloff.test` | Vendor (Demo Fashion Hub) |
| `buyer@selloff.test` | Buyer (₦500k wallet, 2 sample orders) |

SPA smoke tests (from `../app.selloff`):

```bash
npm run test:smoke
```

Parity matrix reconcile (from repo root):

```bash
node scripts/reconcile-spa-parity-matrix.mjs
```

Mobile API tests:

```bash
php artisan test --filter=Pass9MobileApiTest
# or: ./vendor/bin/pest --filter=Pass9MobileApiTest
```

Legacy import (Pass 10):

```bash
php artisan selloff:import-legacy-data --source=tests/fixtures/legacy-subset.sql --dry-run
php artisan test --filter=Pass10
# or: ./vendor/bin/pest --filter=Pass10
```

## Key commands

| Command | Purpose |
|---------|---------|
| `./vendor/bin/pest` or `composer test` | Pest test suite (SQLite in-memory in CI) |
| `php artisan selloff:migrate` | Platform + package migrations in FK order |
| `php artisan selloff:migrate --fresh --seed` | Full reset + seeders |

## Docs

- `docs/API_CONTRACT.md` — JSON envelope and route conventions
- `../docs/SELLOFF_PLATFORM_REFERENCE.md` — architecture hub
