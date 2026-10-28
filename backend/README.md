# ANaTT — API Laravel (`backend/`)

API de l'application de suivi des ordres de mission SEMO et de génération des fichiers de paiement
Mobile Money (MTN `.txt`, Moov `.xlsx`). Le SPA qui la consomme est dans `../frontend/`.

> Vue d'ensemble, règles métier et installation complète : [`../README.md`](../README.md) et
> [`../CLAUDE.md`](../CLAUDE.md). Notes pour les agents de code : [`../AGENTS.md`](../AGENTS.md).
> Ce fichier ne reprend que ce qui est propre à l'API.

## Prérequis

- **PHP 8.4.x, 64 bits** — ni 8.3 (le `composer.lock` exige Symfony 8, l'API répond 500 partout), ni
  8.5 (`phpoffice/phpspreadsheet` déclare `<8.5.0`).
- Composer 2 ; MySQL 8 en production, SQLite pour les tests.
- Redis facultatif : en hébergement mutualisé, `CACHE_STORE=file` et `QUEUE_CONNECTION=sync`.

## Démarrer en local

```bash
composer install
cp .env.example .env          # puis renseigner DB_*, SUPERADMIN_* si besoin
php artisan key:generate
php artisan migrate --seed    # socle (zones, barème, super admin) + données de démo en local
php artisan serve             # http://localhost:8000
php artisan queue:work --queue=paiements,etats,default --tries=3   # si QUEUE_CONNECTION ≠ sync
```

Sous Windows, `..\start-dev.ps1` lance l'API, le worker et le SPA ensemble.
Comptes de démonstration (local et tests uniquement, mot de passe `password`) : `superadmin@`, `admin@`,
`gestion@`, `finance@`, `controle@`, `daf@`, `dg@`, `lecteur@anatt.bj`.

## Vérifier

```bash
./vendor/bin/pest                                   # 671 tests, 75 fichiers — sans .env (APP_KEY de test dans phpunit.xml)
./vendor/bin/pint --test                            # style (la CI l'exécute en vérification)
./vendor/bin/pest tests/Feature/RoutesPermissionsTest.php tests/Feature/OpenApiTest.php   # filets RBAC et OpenAPI
```

Un test de régression doit échouer sans le correctif avant d'être retenu.

## Repères dans le code

| Dossier | Contenu |
|---------|---------|
| `app/Http/Controllers/Api/` | Contrôleurs (missions et OM, états de paiement, lots, barème, rapports, administration) |
| `app/Services/` | Logique métier : `MissionService` (retranscription, amendement), `EtatPaiementService` (circuit), `LotPaiementService`, `CalculIndemniteService`, `Paiement/` (fichiers MTN / Moov) |
| `app/Support/` | `Permissions`, `PermissionRepository` (matrice RBAC en cache), `CategorieIndice`, `Parametres`, `Workflow` |
| `app/Jobs/` | `GenererLotPaiementJob` (file `paiements`), `GenererEtatJob` (file `etats`), `SendNotification` |
| `app/Console/Commands/` | `db:backup`, `db:restore` |
| `routes/api.php` | Routes, chacune protégée par `permission:{clé}` (verrouillé par `RoutesPermissionsTest`) |
| `routes/console.php` | Planification : `db:backup` à 02:00, purge des jetons Sanctum |
| `database/seeders/` | Socle toujours seedé ; démo en `local`/`testing` ; `ComptesCircuitSeeder` pour les signataires en production |
| `tests/Feature`, `tests/Unit` | Pest |

## Documentation de l'API

- [`docs/openapi.yaml`](docs/openapi.yaml) — description OpenAPI 3.0.3 des routes réelles, avec la
  permission de chacune (`x-permission`). Toute nouvelle route doit y figurer : `OpenApiTest` échoue sinon.
- [`docs/queues.md`](docs/queues.md) — files d'attente, modes `sync` / `database` / `redis`, comportement
  à l'échec.
- Déploiement sur cPanel (sans SSH) : [`../DEPLOY-CPANEL.md`](../DEPLOY-CPANEL.md),
  [`../UPLOAD-VENDOR.md`](../UPLOAD-VENDOR.md), [`../docs/runbook-mise-a-jour-cron.md`](../docs/runbook-mise-a-jour-cron.md).

## Points d'attention

- L'API ne crée pas les OM (elle les **retranscrit** depuis SEMO) et ne paie rien : elle **génère** des
  fichiers, déposés à la main sur les plateformes MTN / Moov, puis confirmés manuellement.
- Toute erreur d'une route `/api/*` répond en JSON, même sans en-tête `Accept`. Un 422 porte le message
  générique « Donnees invalides. » et le motif par champ dans `errors` : c'est ce dernier qu'un écran affiche.
- Les réglages de `settings.json` sont relus par `App\Support\Parametres` (cache 60 s, purgé à l'écriture) ;
  la messagerie est appliquée à chaque envoi par `App\Support\Messagerie::appliquer()` (serveur SMTP de
  l'écran *Messagerie*, sinon le `.env`). Un réglage ajouté doit agir et avoir son test (`AGENTS.md`).
- `storage/app/settings.json` (part d'avance, exercice, SMTP, signataires), les paraphes et les fichiers
  produits vivent sur le disque : `db:backup` ne les inclut pas.
- `package.json` et `vite.config.js` de ce dossier sont ceux du gabarit Laravel ; l'interface de
  l'application est le SPA de `../frontend/`.
