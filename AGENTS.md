# AGENTS.md

High-signal notes for OpenCode sessions in this repo.

## First reads
- Read `CLAUDE.md` first for business constraints and design-system rules; then verify against executable sources when in doubt.
- Treat code/config as source of truth over prose. Example: the avance/reliquat split is NOT a hard-coded 70/30 — it reads `Parametres::partAvance()` (default 70) in `backend/app/Services/CalculIndemniteService.php`.
- `docs/revue-projet-2026-10.md` lists what the October 2026 review fixed and why; read it before touching payments, états or amendments.
- Repo language is French: user-facing text, code comments, commit messages, CHANGELOG entries and docs are written in French.

## Repo shape (easy to guess wrong)
- Main app UI is `frontend/` (Vue 3 + TS + Vite 8). API is `backend/` (Laravel 12, Sanctum).
- `backend/package.json`/`backend/vite.config.js` are Laravel-side assets; most product UI work belongs in `frontend/`.
- No Docker in the repo. Dev = WAMP + `start-dev.ps1`; production = cPanel shared hosting (`DEPLOY-CPANEL.md`).
- RBAC has 3 linked layers that must stay aligned:
  - backend permissions on routes: `backend/routes/api.php`
  - route meta permissions: `frontend/src/router/index.ts`
  - sidebar/menu permissions: `frontend/src/components/shared/navigation.ts`

## Environment requirements
- PHP **8.4+** is mandatory (not a preference): `composer.lock` pins Symfony 8 / spatie packages; under 8.3 Composer's platform check fails and every API call returns 500.
- Backend tests run without a `.env`: `phpunit.xml` provides a test `APP_KEY` (phpdotenv then emits harmless warnings, exit code stays 0).
- Playwright with an already-installed Chromium of another version: `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/path/to/chromium npm run test:e2e`.

## Local dev commands (Windows-first)
- Preferred boot: `./start-dev.ps1` (or first run `./start-dev.ps1 -Install -Fresh`), stop with `./stop-dev.ps1`.
- Script launches 3 processes: API `:8000`, queue worker (`paiements,etats,default`), frontend `:5173`.
- Manual boot if needed:
  - backend: `php artisan migrate --force`, `php artisan serve`, `php artisan queue:work --queue=paiements,etats,default --tries=3`
  - frontend: `npm run dev`

## Verification commands agents should run
- Backend full tests: `cd backend && php artisan test` (Pest, ~550 tests)
- Backend style: `cd backend && vendor/bin/pint --test` (CI runs it in check mode)
- Backend focused RBAC safety net: `cd backend && php artisan test tests/Feature/RoutesPermissionsTest.php`
- Frontend quality checks:
  - `cd frontend && npm run lint:check` (check only; `npm run lint` auto-fixes)
  - `cd frontend && npm run type-check`
  - `cd frontend && npx vitest run` (or `npm run test:unit -- src/__tests__/navigation.spec.ts` for the RBAC net)
  - `cd frontend && npm run build` (includes type-check via script)
  - `cd frontend && npm run test:e2e` (Playwright critical path, API mocked)
- CI (`.github/workflows/ci.yml`) runs Lint, Pest, Vitest + type-check, Playwright and the `frontend-dist` build on every PR.
- For a regression test, prove it fails without the fix (e.g. `git stash push -- app` then rerun) before relying on it.

## Critical implementation constraints
- Do not add API routes without `permission:` middleware unless intentionally public/self-service; `RoutesPermissionsTest` fails on accidental exposure.
- Do not add authenticated frontend routes without `meta.permission` (except explicit self-service routes like profile/password-change); `navigation.spec.ts` enforces this.
- Keep unique Vue route names (historical collision between `/ordres` and `/missions` caused wrong-screen navigation).
- Auth flow includes forced password change lock:
  - backend middleware returns HTTP 423: `backend/app/Http/Middleware/ForcePasswordChange.php`
  - frontend handles 423 and redirects in `frontend/src/composables/useApi.ts`; the router guard must also redirect after a page reload (`router/index.ts`).
- Unauthenticated `/api/*` requests always get a 401 JSON, even without an `Accept` header (`bootstrap/app.php` — there is no `login` route).
- API base URL precedence is runtime-first: `window.__ANATT_CONFIG__.apiUrl` from `frontend/public/runtime-config.js` overrides `VITE_API_URL`.
- The axios instance sends `Content-Type: application/json` by default: any `FormData` upload must pass `headers: { 'Content-Type': 'multipart/form-data' }` or axios serializes the file to `{}`.
- Pagination: use `$this->getPerPage($request[, $default])` (bounded 5–100), never a raw `per_page`.
- Dates in the SPA: build `YYYY-MM` with `moisLocal()` (`utils/benin.ts`), never `toISOString()` (UTC shifts the month in Benin, UTC+1).
- RBAC "ordonnateur/payeur" separation (`om.approuver` vs `paiements.generer|confirmer`) is enforced on profiles, on user overrides AND on every member's effective rights when a profile is edited or reset (`RoleController`).

## Payments/async gotchas
- Async generation threshold is `config('paiement.async_seuil')` (default 50) in `backend/config/paiement.php`; above it `POST /paiements/lots/generer` returns 202 + `tracking_id` and the SPA polls `/paiements/lots/job/:id`.
- Async jobs only progress if worker is running; local default queue connection is `database` (`backend/config/queue.php`, `backend/.env`).
- Payment file formats are strict (`.txt` MTN, `.xlsx` Moov); avoid "cleanup" edits to generators without running payment tests.
- Files live on the `local` disk = `storage/app/private/`, one folder per mission: `paiements/{mission_id}/{slug}_{TRANCHE}[_COMPL{n}].{txt|xlsx}`. `lots_paiement.nom_fichier` stores that path (old lots keep their old path).
- A lot is keyed by mission × tranche × operator × **`sequence`**. A CONFIRME lot is never rewritten: a versement that becomes payable later goes into the next sequence (complementary lot). `LotPaiementResource` must expose `sequence` (the UI badge depends on it).
- Eligibility gate is `Versement::scopeEligiblesLot`: excludes CONFIRME, ANNULE, A_RECOUVRER, RECOUVRE, amounts ≤ 0, versements carried by a CONFIRME lot, and requires the OM VALIDE + the état OF ITS TRANCHE VALIDE. `FileGenerator::exigerEtatValide` is a second barrier — keep both.
- Marking a lot ERREUR puts its versements back to EN_ATTENTE with the agent's CURRENT phone/operator.
- A mission has TWO payment states (`etats_paiement`, one per tranche AVANCE/RELIQUAT), each with its own full validation circuit; reliquat état is refused until the avance état is VALIDÉ. A versement is eligible only when the état OF ITS TRANCHE is valid (missions without any état = legacy OM-level fallback, `/ordres/:id/valider|rejeter`).
- The reliquat état is signed at the PAYABLE amount (sum of positive reliquats); trop-perçus (negative reliquats) are shown apart on the PDF, never subtracted from the signed total.
- Validating the avance état propagates VALIDE to the mission's OM except SOLDE/ANNULE/REJETE; rejecting an OM sets its EN_ATTENTE versements to ANNULE.
- The closing "état général" (avances + reliquats) is NOT an entity: `EtatPaiementPdf::telechargerFinal()` synthesizes it from the two sealed snapshots once BOTH tranches are VALIDE (422 otherwise) — no third circuit, no migration.
- The avance/reliquat split is paramétrable via `GET/PUT /api/admin/settings` (SUPER_ADMIN only, key `partAvance`, int 0–100, default 70). Read publicly at `GET /api/admin/parametres/part-avance`.

## Amendments (avenants)
- `MissionService::amender`: refused if the OM has a GENERE versement (file awaiting deposit), if an état is CONTROLE/VISA_DAF, if the reliquat état is VALIDE, or if a trop-perçu is RECOUVRE. États are read with `lockForUpdate`.
- Once `avance_engagee` is set, only the reliquat moves (`reliquat = total − avance_engagee`, may go negative → versement A_RECOUVRER). On the per-OM circuit, a CONFIRME avance is engaged at amendment time.
- Per diem with zero billed days keeps the transport; only `prise_en_charge_tiers` (exonération) zeroes everything.

## Backups
- `artisan db:backup --compress` (scheduled 02:00 in `routes/console.php`) and `artisan db:restore <file> --force` (takes a safety backup first; aborts if it fails). Binaries: `DB_DUMP_BINARY` / `DB_RESTORE_BINARY`.
- Tests mock `Artisan::call` for restore — the real `mysql` client is never exercised in CI.

## Seed/data expectations
- `backend/database/seeders/DatabaseSeeder.php` seeds essentials always; demo data only in `local/testing`.
- Production super admin requires `SUPERADMIN_PASSWORD`; seeder throws if missing (`backend/database/seeders/UserSeeder.php`).
- Demo accounts (password `password`, local only): `superadmin@ admin@ gestion@ finance@ controle@ daf@ dg@ lecteur@anatt.bj`.

## Docs that must follow functional changes
- `CHANGELOG.md` (French, Keep a Changelog), `CLAUDE.md`, `README.md`.
- Generated Word docs: run `python docs/generer_guide_utilisateur.py` and `python docs/generer_documentation_technique.py` (python-docx) after editing the scripts — never edit the `.docx` by hand.
- `docs/SCENARIO-DEMO.md` claims its amounts/files are real: replay changed flows against the API before editing it.
- Deployment notes: `DEPLOY-CPANEL.md` (incl. per-version notes), `docs/checklist-post-deploiement.md`, `docs/runbook-mise-a-jour-cron.md`.
