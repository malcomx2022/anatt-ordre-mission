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

> *Relu le 25/10/2026 contre le code* : jobs, contrôleurs, `PaiementGeneration.vue` et
> `AsyncGenerationTest`. Le comportement en `sync` décrit ci-dessous est verrouillé par deux tests.
>
> **En `sync`**, le job tourne pendant le `POST` lui-même.
> - **S'il réussit**, le gros lot répond `202 + tracking_id` comme avec un worker. Le premier
>   sondage du SPA renvoie alors directement `TERMINE`.
> - **S'il échoue**, aucun `202` ne part : l'exception remonte dans la réponse du `POST`. Son code
>   est celui de l'erreur, souvent un 500 JSON « Erreur interne du serveur. ». `failed()` a déjà
>   tourné (suivi `ERREUR`, dernier lot non confirmé en `ERREUR`, cf. *Échec définitif*). L'écran
>   affiche l'erreur sans sonder. C'est le même comportement qu'un petit lot en échec. Si un lot `GENERE` existait déjà pour
>   cette clé, il vient de passer en `ERREUR` : l'écran recharge le lot après l'erreur
>   (`PaiementGeneration.vue`, depuis le 26/10/2026), « Confirmer » disparaît et « Régénérer »
>   reste proposé.

## Jobs

| Job | File | Rôle |
|-----|------|------|
| `App\Jobs\GenererLotPaiementJob` | `paiements` | Génère un **gros lot** de paiement (.txt MTN / .xlsx Moov) |
| `App\Jobs\GenererEtatJob` | `etats` | Produit un **export de rapport** (xlsx/pdf) — sans lien avec le circuit des états de paiement, malgré son nom |
| `App\Jobs\SendNotification` | `default` | Envoie l'e-mail d'une notification. La notification en base est créée avant, hors job. Aucun e-mail n'est envoyé à un compte désactivé entre-temps (correctif n° 27). |

`GenererLotPaiementJob` et `GenererEtatJob` : `$tries = 3`, `$backoff = [10, 30, 60]`,
suivi par `tracking_id` dans le **cache** (clés `lot_job:{id}` / `etat_job:{id}` ;
`EN_COURS` 1 h, résultat 24 h). `SendNotification` n'a pas de suivi. Une erreur d'envoi SMTP est
interceptée dans le job et journalisée (`storage/logs`) : le job ne la fait jamais échouer, et elle
n'est pas rejouée.

> En `sync`, l'e-mail part **pendant** la requête qui notifie, par exemple la validation d'un état
> par le DG. Un serveur SMTP lent ralentit donc cette action, sans jamais la faire échouer.

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

> **Le SPA n'emploie pas cette voie.** L'écran *Rapports › Export* appelle l'export synchrone
> `GET /api/rapports/export` (`services/rapports.ts`). `export-async` et `GenererEtatJob` ne servent
> qu'à un appel direct de l'API : aucun écran ne les déclenche ni ne sonde leur suivi.

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

`QUEUE_CONNECTION=database` s'appuie sur les tables `jobs` et `failed_jobs`. Elles sont créées par
la migration `0001_01_01_000002_create_jobs_table`, déjà jouée à l'installation : rien à ajouter
pour basculer.

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
versements remis `EN_ATTENTE`), lot confirmé jamais modifié, endpoint de suivi, et en `sync` :
réussite en 202 avec un suivi déjà `TERMINE`, échec remonté dans la réponse sans 202.
