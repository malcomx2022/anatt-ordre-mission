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
- PHP **8.4.x, 64-bit** is mandatory, neither 8.3 nor 8.5:
  - `composer.lock` pins Symfony 8 / spatie (≥ 8.4), so under 8.3 Composer's platform check fails and every API call returns 500;
  - `phpoffice/phpspreadsheet` 1.30.2 declares `php <8.5.0`, so `composer install` refuses the lock under 8.5;
  - `platform_check.php` only checks the minimum and 64-bit, so a server on 8.5 would boot outside the supported range.
- Node **20.19+ or 22.12+** (Vite 8 `engines`; CI uses Node 22).
- Backend tests run without a `.env`: `phpunit.xml` provides a test `APP_KEY` (phpdotenv then emits harmless warnings, exit code stays 0).
- Playwright with an already-installed Chromium of another version: `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/path/to/chromium npm run test:e2e`. In a container without a display, point it to the **headless shell** (e.g. `/opt/pw-browsers/chromium_headless_shell-*/chrome-linux/headless_shell`) and add `--project=chromium` like CI does; the full `chrome` binary fails with "Missing X server".

## Local dev commands (Windows-first)
- Preferred boot: `./start-dev.ps1` (or first run `./start-dev.ps1 -Install -Fresh`), stop with `./stop-dev.ps1`.
- Script launches 3 processes: API `:8000`, queue worker (`paiements,etats,default`), frontend `:5173`.
- Manual boot if needed:
  - backend: `php artisan migrate --force`, `php artisan serve`, `php artisan queue:work --queue=paiements,etats,default --tries=3`
  - frontend: `npm run dev`

## Verification commands agents should run
- Backend full tests: `cd backend && php artisan test` (Pest, ~586 tests)
- Backend style: `cd backend && vendor/bin/pint --test` (CI runs it in check mode)
- Backend focused RBAC safety net: `cd backend && php artisan test tests/Feature/RoutesPermissionsTest.php`
- Frontend quality checks:
  - `cd frontend && npm run lint:check` (check only; `npm run lint` auto-fixes)
  - `cd frontend && npm run type-check`
  - `cd frontend && npx vitest run` (or `npx vitest run src/__tests__/navigation.spec.ts` for the RBAC net). Plain `npm run test:unit` starts **watch mode** and never returns: add `-- --run`.
  - `cd frontend && npm run build` (includes type-check via script)
  - `cd frontend && npm run test:e2e` (Playwright critical path, API mocked)
- CI (`.github/workflows/ci.yml`) runs Lint, Pest, Vitest + type-check, Playwright and the `frontend-dist` build on every PR.
- For a regression test, prove it fails without the fix (e.g. `git stash push -- app` then rerun) before relying on it.

## Critical implementation constraints
- Do not add API routes without `permission:` middleware unless intentionally public/self-service; `RoutesPermissionsTest` fails on accidental exposure.
- Every API route must be described in `backend/docs/openapi.yaml` with its permission as `x-permission` (omit it for self-service/public routes); `OpenApiTest` fails on any missing, extra or mismatched operation. Quote YAML scalars that contain `: `, `#`, commas or `?` inside `{ }`.
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
- The permission matrix is cached with `rememberForever` (`rbac.matrice`): any code path that writes `role_permissions` (or replaces the DB, like `db:restore`) must call `PermissionRepository::vider()`.
- Permission changes (matrix or user overrides) take effect server-side on the **next API request**: Gates resolve rights per request, and `User::permissions()` is memoized per request only. Only the SPA menu/buttons wait for login or `/auth/me/permissions`. In tests, use `actingAs($user->fresh())` after a change, because the memo lives on the model instance.
- RBAC "ordonnateur/payeur" separation (`om.approuver` vs `paiements.generer|confirmer`) is enforced on profiles, on user overrides AND on every member's effective rights when a profile is edited or reset (`RoleController`).

## Payments/async gotchas
- Async generation threshold is `config('paiement.async_seuil')` (default 50) in `backend/config/paiement.php`; above it `POST /paiements/lots/generer` returns 202 + `tracking_id` and the SPA polls `/paiements/lots/job/:id`.
- Async jobs only progress if worker is running; local default queue connection is `database` (`backend/config/queue.php`, `backend/.env`). Production on cPanel runs `QUEUE_CONNECTION=sync` (`backend/.env.production.example`), so jobs run inside the request.
- Payment file formats are strict (`.txt` MTN, `.xlsx` Moov); avoid "cleanup" edits to generators without running payment tests.
- Files live on the `local` disk = `storage/app/private/`, one folder per mission: `paiements/{mission_id}/{slug}_{TRANCHE}[_COMPL{n}].{txt|xlsx}`. `lots_paiement.nom_fichier` stores that path (old lots keep their old path).
- A lot is keyed by mission × tranche × operator × **`sequence`**. A CONFIRME lot is never rewritten: a versement that becomes payable later goes into the next sequence (complementary lot). `LotPaiementResource` must expose `sequence` (the UI badge depends on it).
- `PaiementGeneration.vue` must **not** block generation just because the latest lot is CONFIRME. It blocks only when the preview has no payable line; otherwise it offers "Générer le lot complémentaire" (defect n° 21: the server supported complementary lots, the screen hid them). `GET /paiements/lots` orders by `created_at` then `id`.
- An OM goes SOLDE when all its versements are settled — CONFIRME, or RECOUVRE for an overpayment — checked on lot confirmation, on `marquerRecouvre` and after an amendment that brings the reliquat to 0 (`OrdreMission::solderSiRegle`, n° 23 and 26). Amending a SOLDE OM is allowed unless its reliquat is CONFIRME or its trop-perçu RECOUVRE (own guards); `amender` puts it back to VALIDE when a payable amount reappears (only VALIDE OMs enter files) and leaves it SOLDE otherwise (n° 25 revised by n° 28). The SPA shows « Amender » on such a SOLDE OM. An exonerated OM (zero amount) is SOLDE as soon as it is validated, in both circuits (n° 24). Zero-amount versements (empty tranche, `partAvance` 0 or 100) are ignored there, since they never enter a file (n° 19). An `A_RECOUVRER` trop-perçu still holds the OM.
- Eligibility gate is `Versement::scopeEligiblesLot`: excludes CONFIRME, ANNULE, A_RECOUVRER, RECOUVRE, amounts ≤ 0, versements carried by a CONFIRME lot, and requires the OM VALIDE + the état OF ITS TRANCHE VALIDE. `FileGenerator::exigerEtatValide` is a second barrier — keep both.
- Marking a lot ERREUR puts its versements back to EN_ATTENTE with the agent's CURRENT phone/operator.
- A mission has TWO payment states (`etats_paiement`, one per tranche AVANCE/RELIQUAT), each with its own full validation circuit; reliquat état is refused until the avance état is VALIDÉ. A versement is eligible only when the état OF ITS TRANCHE is valid (missions without any état = legacy OM-level fallback, `/ordres/:id/valider|rejeter`).
- The OM PDF takes its signature blocks from the OM itself first, else from the AVANCE état covering it (`PdfGenerator::etatSignataire`: OM listed in the état `lignes`, OM not REJETE/ANNULE, état not REJETE; never on a draft). The cache key includes the état status and the amendment count (`OM-{ref}-{statut}[-ETAT-{statut_etat}][-AMD{n}].pdf`) — `/pdf/download` serves the cached file as is, so any new input to the PDF must enter the key, or stale PDFs get served.
- The reliquat état is signed at the PAYABLE amount (sum of positive reliquats); trop-perçus (negative reliquats) are shown apart on the PDF, never subtracted from the signed total.
- Validating the avance état propagates VALIDE to the mission's OM except SOLDE/ANNULE/REJETE; rejecting an OM sets its EN_ATTENTE versements to ANNULE.
- The closing "état général" (avances + reliquats) is NOT an entity: `EtatPaiementPdf::telechargerFinal()` synthesizes it from the two sealed snapshots once BOTH tranches are VALIDE (422 otherwise) — no third circuit, no migration.
- Fiscal year: the SPA default (`SettingsView`) is the *determined* current exercise (`utils/exercice.ts` → `exerciceDe`, mirror of `Parametres::anneeFiscalePour`), not the calendar year (n° 20). No screen or report consumes the exercise yet.
- The avance/reliquat split is paramétrable via `GET/PUT /api/admin/settings` (SUPER_ADMIN only, key `partAvance`, int 0–100, default 70). Read publicly at `GET /api/admin/parametres/part-avance`.

## Amendments (avenants)
- `MissionService::amender`: refused if the OM has a GENERE versement (file awaiting deposit), if an état is CONTROLE/VISA_DAF, if the reliquat état is VALIDE, if the RELIQUAT versement is already CONFIRME, or if a trop-perçu is RECOUVRE. États are read with `lockForUpdate`.
  - The CONFIRME-reliquat case only arises on missions without état (n° 22). `reventilerVersements` never rewrites a CONFIRME versement, so amending would desync the OM from what was paid.
  - The SPA hides "Amender" for a SOLDE OM, but the server guard is what counts.
- "Last amendment" = order by `amende_le` then `id` (two amendments can share a second). The same order applies to the OM PDF box, the retenue document motif and the fiche journal (n° 17).
- Once `avance_engagee` is set, only the reliquat moves (`reliquat = total − avance_engagee`, may go negative → versement A_RECOUVRER). On the per-OM circuit, a CONFIRME avance is engaged at amendment time.
- Per diem with zero billed days keeps the transport; only `prise_en_charge_tiers` (exonération) zeroes everything.

## Backups
- `artisan db:backup --compress` (scheduled 02:00 in `routes/console.php`, together with `sanctum:prune-expired`; on cPanel the recommended Cron line is `schedule:run` every minute) and `artisan db:restore <file> --force` (takes a safety backup first; aborts if it fails). Binaries: `DB_DUMP_BINARY` / `DB_RESTORE_BINARY`.
- Tests mock `Artisan::call` for the HTTP restore; `BackupControllerTest` also runs `db:restore` end-to-end with a fake POSIX `mysql` script (skipped on Windows). A real MySQL restore is never exercised in CI.
- `db:restore` purges the cached permission matrix after success. The dump covers the DB only: `storage/app/settings.json` (partAvance, fiscal year, SMTP, signatories), paraphes (`storage/app/private/signatures/`) and generated files live on disk and are neither backed up nor restored.

## Seed/data expectations
- `backend/database/seeders/DatabaseSeeder.php` seeds essentials always; demo data only in `local/testing`.
- Production super admin requires `SUPERADMIN_PASSWORD`; seeder throws if missing (`backend/database/seeders/UserSeeder.php`).
- Demo accounts (password `password`, local only): `superadmin@ admin@ gestion@ finance@ controle@ daf@ dg@ lecteur@anatt.bj`.

## Docs that must follow functional changes
- `CHANGELOG.md` (French, Keep a Changelog), `CLAUDE.md`, `README.md`.
- Generated Word docs: run `python docs/generer_guide_utilisateur.py` and `python docs/generer_documentation_technique.py` (python-docx) after editing the scripts — never edit the `.docx` by hand.
- `docs/SCENARIO-DEMO.md` claims its amounts/files are real: replay changed flows against the API before editing it.
- Deployment notes: `DEPLOY-CPANEL.md` (incl. per-version notes), `docs/checklist-post-deploiement.md`, `docs/runbook-mise-a-jour-cron.md`.
