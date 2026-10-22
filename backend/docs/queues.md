# Files d'attente — ANaTT

Trois traitements passent par des **jobs Laravel**. Selon `QUEUE_CONNECTION`, un job
s'exécute **dans la requête** (`sync`) ou est confié à un **worker** (`database`, `redis`).
L'application fonctionne sans Redis ni worker : c'est la configuration de production
sur l'hébergement mutualisé (`backend/.env.production.example`).

| Mode | Où tourne le job | Reprise sur erreur | Usage |
|------|------------------|--------------------|-------|
| `sync` | dans la requête HTTP | **aucune** (1 seule exécution, `$tries` ignoré) | production cPanel (défaut du gabarit), tests |
| `database` | worker (`queue:work`, table `jobs`) | 3 tentatives, backoff `[10, 30, 60]` s | mutualisé à gros volumes (Cron chaque minute) |
| `redis` | worker (`queue:work redis`) | idem | serveur dédié avec Redis |

> Les échanges avec l'API ne changent pas d'un mode à l'autre : un gros lot répond
> toujours `202 + tracking_id` et le SPA sonde le suivi. En `sync`, le job est déjà
> terminé quand le 202 part : le premier sondage renvoie `TERMINE` (ou `ERREUR`).

## Jobs

| Job | File | Rôle |
|-----|------|------|
| `App\Jobs\GenererLotPaiementJob` | `paiements` | Génère un **gros lot** de paiement (.txt MTN / .xlsx Moov) |
| `App\Jobs\GenererEtatJob` | `etats` | Produit un **export de rapport** (xlsx/pdf) — sans lien avec le circuit des états de paiement, malgré son nom |
| `App\Jobs\SendNotification` | `default` | Envoie l'e-mail d'une notification (la notification en base est créée avant, hors job) |

`GenererLotPaiementJob` et `GenererEtatJob` : `$tries = 3`, `$backoff = [10, 30, 60]`,
suivi par `tracking_id` dans le **cache** (clés `lot_job:{id}` / `etat_job:{id}` ;
`EN_COURS` 1 h, résultat 24 h). `SendNotification` n'a pas de suivi : un échec est
journalisé (`storage/logs`).

### Génération d'un lot (`GenererLotPaiementJob`)

Le job appelle `LotPaiementService::generer`, donc **exactement** les mêmes règles que
la génération directe : garde « état de la tranche validé », périmètre
`Versement::scopeEligiblesLot` (idempotence, montants > 0), **lot complémentaire**
(`sequence` suivante) si le dernier lot est `CONFIRME`, tout dans une transaction.
Rien n'est écrit en base si la génération échoue.

**Échec définitif** (`failed()`, après les 3 tentatives — ou dès le premier échec en `sync`) :
- le suivi passe `ERREUR` avec le message ;
- le **dernier lot** de la clé (mission × tranche × opérateur), s'il est `GENERE` ou
  `ERREUR`, passe en `ERREUR` **par `LotPaiementService::marquerErreur`**, comme l'action
  manuelle « Marquer en erreur » : ses versements redeviennent `EN_ATTENTE` avec les
  coordonnées courantes de l'agent (OM de nouveau amendable), action auditée `ERROR_LOT` ;
- un lot `CONFIRME` n'est **jamais** touché (anti-double-paiement).

> Conséquence pratique : après un échec, un fichier précédemment généré pour ce lot est
> considéré comme **non déposé**. S'il a déjà été déposé sur la plateforme opérateur,
> ne régénérez pas : vérifiez le dépôt auprès de l'opérateur avant toute action.

## Bascule automatique (gros lots)

`POST /api/paiements/lots/generer` :
- **petit lot** (≤ `paiement.async_seuil`, défaut 50 bénéficiaires) → génération dans la
  requête + téléchargement direct ;
- **gros lot** (> seuil) → `202 Accepted` avec `{ async, tracking_id, nb_lignes, message }`.

Suivi : `GET /api/paiements/lots/job/{trackingId}` →
`{ statut: EN_COURS | TERMINE | ERREUR | INTROUVABLE, lot_id?, fichier?, message? }`.
Une fois `TERMINE`, télécharger via `GET /api/paiements/lots/{lot}/download`.

Côté SPA (`components/paiements/PaiementGeneration.vue`) : sondage toutes les **2 s**,
téléchargement automatique à `TERMINE`. `INTROUVABLE` est toléré **30 fois** (≈ 1 min,
le temps qu'un worker prenne la tâche) ; au-delà, l'écran affiche « la file de traitement
ne répond pas » — signe qu'aucun worker ne traite la file `paiements`.

Exports de rapports : `POST /api/rapports/export-async` (`dimension`, `format`,
`date_debut`, `date_fin`) → `202 + { tracking_id }` ; suivi
`GET /api/rapports/export/job/{trackingId}` ; téléchargement
`GET /api/rapports/export/download?fichier=...`.

## Configuration (.env)

```env
# Production mutualisée (gabarit .env.production.example) : aucun worker
QUEUE_CONNECTION=sync
CACHE_STORE=file

# Avec worker : database (sans Redis) ou redis
# QUEUE_CONNECTION=database
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379

PAIEMENT_ASYNC_SEUIL=50  # seuil de bascule en file (nb de bénéficiaires)
```

Le suivi par `tracking_id` exige un cache **partagé** entre l'API et le worker :
`file`, `database` ou `redis` conviennent ; `array` ne convient pas (perdu à chaque processus).

## Lancer le worker

Le worker doit écouter **les trois files** : sans `--queue`, il ne traite que `default`
et les gros lots restent `INTROUVABLE`.

```bash
# Développement (start-dev.ps1 le lance) — priorité aux paiements
php artisan queue:work --queue=paiements,etats,default --tries=3

# Mutualisé, QUEUE_CONNECTION=database : tâche Cron chaque minute (DEPLOY-CPANEL.md §9)
php artisan queue:work --queue=paiements,etats,default --tries=3 --stop-when-empty

# Serveur dédié avec Redis : superviser (systemd / supervisor)
php artisan queue:work redis --queue=paiements,etats,default --tries=3
```

Après un déploiement de code, redémarrez un worker permanent (`php artisan queue:restart`) :
il garde sinon l'ancien code en mémoire. La tâche Cron `--stop-when-empty` n'est pas concernée
(un nouveau processus démarre chaque minute).

Jobs échoués (modes `database` / `redis`, table `failed_jobs`) :

```bash
php artisan queue:failed       # liste
php artisan queue:retry all    # rejoue
```

> Rejouer un `GenererLotPaiementJob` échoué est sûr : il repasse par les mêmes gardes
> (un versement confirmé n'est jamais réinclus). Le rejeu reprend toutefois l'ancien
> `tracking_id`, que l'écran ne suit plus : relancez plutôt la génération depuis l'écran.

## Tests

`tests/Feature/Paiement/AsyncGenerationTest.php` : bascule 202 au-delà du seuil,
téléchargement direct en deçà, exécution du job et suivi, échec (suivi `ERREUR`,
versements remis `EN_ATTENTE`), lot confirmé jamais modifié, endpoint de suivi.
