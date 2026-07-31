# AGENTS.md

High-signal notes for OpenCode sessions in this repo.

## First reads
- Read `CLAUDE.md` first for business constraints and design-system rules; then verify against executable sources when in doubt.
- Treat code/config as source of truth over prose (some docs are stale: e.g. per diem split in code is 70/30 via `backend/app/Services/CalculIndemniteService.php`).

## Repo shape (easy to guess wrong)
- Main app UI is `frontend/` (Vue 3 + TS + Vite). API is `backend/` (Laravel 12, Sanctum).
- `backend/package.json`/`backend/vite.config.js` are Laravel-side assets; most product UI work belongs in `frontend/`.
- RBAC has 3 linked layers that must stay aligned:
  - backend permissions on routes: `backend/routes/api.php`
  - route meta permissions: `frontend/src/router/index.ts`
  - sidebar/menu permissions: `frontend/src/components/shared/navigation.ts`

## Local dev commands (Windows-first)
- Preferred boot: `./start-dev.ps1` (or first run `./start-dev.ps1 -Install -Fresh`), stop with `./stop-dev.ps1`.
- Script launches 3 processes: API `:8000`, queue worker (`paiements,etats,default`), frontend `:5173`.
- Manual boot if needed:
  - backend: `php artisan migrate --force`, `php artisan serve`, `php artisan queue:work --queue=paiements,etats,default --tries=3`
  - frontend: `npm run dev`

## Verification commands agents should run
- Backend full tests: `cd backend && php artisan test`
- Backend focused RBAC safety net: `cd backend && php artisan test tests/Feature/RoutesPermissionsTest.php`
- Frontend quality checks:
  - `cd frontend && npm run lint`
  - `cd frontend && npm run type-check`
  - `cd frontend && npm run test:unit -- src/__tests__/navigation.spec.ts`
  - `cd frontend && npm run build` (includes type-check via script)

## Critical implementation constraints
- Do not add API routes without `permission:` middleware unless intentionally public/self-service; `RoutesPermissionsTest` fails on accidental exposure.
- Do not add authenticated frontend routes without `meta.permission` (except explicit self-service routes like profile/password-change); `navigation.spec.ts` enforces this.
- Keep unique Vue route names (historical collision between `/ordres` and `/missions` caused wrong-screen navigation).
- Auth flow includes forced password change lock:
  - backend middleware returns HTTP 423: `backend/app/Http/Middleware/ForcePasswordChange.php`
  - frontend handles 423 and redirects in `frontend/src/composables/useApi.ts`
- API base URL precedence is runtime-first: `window.__ANATT_CONFIG__.apiUrl` from `frontend/public/runtime-config.js` overrides `VITE_API_URL`.

## Payments/async gotchas
- Async generation threshold is `config('paiement.async_seuil')` (default 50) in `backend/config/paiement.php`.
- Async jobs only progress if worker is running; local default queue connection is `database` (`backend/config/queue.php`, `backend/.env`).
- Payment file formats are strict (`.txt` MTN, `.xlsx` Moov); avoid "cleanup" edits to generators without running payment tests.
- A mission has TWO payment states (`etats_paiement`, one per tranche AVANCE/RELIQUAT), each with its own full validation circuit; reliquat état is refused until the avance état is VALIDÉ. A versement is eligible only when the état OF ITS TRANCHE is valid (missions without any état = legacy OM-level fallback).
- The closing "état général" (avances + reliquats) is NOT an entity: `EtatPaiementPdf::telechargerFinal()` synthesizes it from the two sealed snapshots once BOTH tranches are VALIDE (422 otherwise) — no third circuit, no migration.

## Seed/data expectations
- `backend/database/seeders/DatabaseSeeder.php` seeds essentials always; demo data only in `local/testing`.
- Production super admin requires `SUPERADMIN_PASSWORD`; seeder throws if missing (`backend/database/seeders/UserSeeder.php`).
