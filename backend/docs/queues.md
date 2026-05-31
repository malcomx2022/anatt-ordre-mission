# Files d'attente (Redis) — ANaTT

Les traitements lourds sont déportés sur des **jobs Laravel** exécutés via une file
**Redis**, avec reprise sur erreur (3 tentatives, backoff progressif `[10, 30, 60]` s)
et suivi de progression par `tracking_id` (stocké dans le cache).

## Jobs

| Job | File | Rôle |
|-----|------|------|
| `App\Jobs\GenererLotPaiementJob` | `paiements` | Génère un gros lot de paiement (.txt MTN / .xlsx Moov) |
| `App\Jobs\GenererEtatJob` | `etats` | Produit un état/rapport (export xlsx/pdf) |

Chaque job : `$tries = 3`, `$backoff = [10, 30, 60]`. En cas d'échec définitif,
`failed()` enregistre l'erreur dans le cache et (pour les lots) bascule le lot en
statut `ERREUR` pour autoriser une régénération.

## Bascule automatique (gros lots)

`POST /api/paiements/lots/generer` :
- **petit lot** (≤ `paiement.async_seuil`, défaut 50) → génération synchrone + téléchargement direct.
- **gros lot** (> seuil) → `202 Accepted` avec `{ tracking_id }` ; la génération part en file.

Suivi : `GET /api/paiements/lots/job/{trackingId}` →
`{ statut: EN_COURS | TERMINE | ERREUR | INTROUVABLE, lot_id?, fichier?, message? }`.
Une fois `TERMINE`, télécharger via `GET /api/paiements/lots/{lot}/download`.

États : `POST /api/rapports/export-async` → `{ tracking_id }` ;
suivi `GET /api/rapports/export/job/{trackingId}` ; téléchargement via
`GET /api/rapports/export/download?fichier=...`.

## Configuration (.env)

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis        # pour le suivi de progression (tracking_id)
PAIEMENT_ASYNC_SEUIL=50  # seuil de bascule en file
```

## Lancer le worker

```bash
# Traite les deux files, 3 tentatives, priorité aux paiements
php artisan queue:work redis --queue=paiements,etats --tries=3 --backoff=10,30,60

# En production : superviser avec systemd ou supervisor, et/ou
php artisan queue:listen   # (dev, recharge le code à chaque job)
```

Relancer les jobs échoués :

```bash
php artisan queue:failed       # liste
php artisan queue:retry all    # rejoue
```
