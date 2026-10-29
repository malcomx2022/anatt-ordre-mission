# Recette sur l'API réelle

Deux scripts Node qui jouent l'application **contre l'API Laravel réellement démarrée**
(`php artisan serve`), sans navigateur et sans dépendance (Node 22, `fetch` natif) :

| Script | Rôle | Contrôles |
|--------|------|-----------|
| `rejeu-scenario.cjs` | Rejoue `docs/SCENARIO-DEMO.md` : 4 agents (indice 850), mission BOHICON du 18 au 23/05/2026 (1 j un repas + 6 j journée complète, transport 10 000 pour ATCHA), état des avances (contrôle → visa DAF → numéro + validation DG), aperçus et fichiers MTN / Moov (contenu exact), confirmation, état des reliquats et fichiers reliquat, état général, puis l'avenant §7 (rallonge à 8 j, réduction à 3 j → trop-perçu −54 000, document de retenue, recouvrement). | 119 |
| `recette-api.cjs` | Contrôles transverses : erreurs JSON sans en-tête `Accept` (401, 404, 405, 403, 422), en-têtes de sécurité, 8 comptes et refus RBAC 403, séparation ordonnateur/payeur, retranscription et refus de saisie, ordre du circuit, rejet et numéro de registre, fichier MTN téléchargé (en-têtes HTTP, CRLF, ASCII), lot en erreur puis régénération avec le numéro courant, lot complémentaire, idempotence, verrou par tranche, mission sans état (validation et rejet par OM), trop-perçu → `RECOUVRE`, export Excel, audit, pagination, notifications, déconnexion. | 154 |

Soit **273 contrôles** en tout. Les motifs de refus sont comparés au texte exact renvoyé par l'API (en
français) : c'est ainsi que la recette a relevé les défauts n° 54 (agent sans indice ni salaire brut) et
n° 55 (message de la date de retour), désormais contrôlés à chaque exécution.

Chaque attendu est une assertion : le script s'arrête en **code 1 au premier écart** (motif obtenu
affiché) et termine sinon par « N contrôles conformes ». `lib.cjs` regroupe le client HTTP, les
assertions et un lecteur `.xlsx` minimal (zip + XML) pour contrôler le fichier Moov.

Ils complètent Pest (qui joue l'API en mémoire) sur ce qui n'est vrai que sur l'API servie :
pile HTTP complète, limites de débit, fichiers réellement téléchargés, `.env` d'exécution.
Ils tournent en CI dans le job **« Recette · API réelle »** (`.github/workflows/ci.yml`).

## Prérequis

- PHP 8.4, dépendances installées (`composer install`), Node ≥ 22.
- Une base **neuve** seedée avec les comptes de démonstration (mot de passe `password`), donc
  `APP_ENV=local` : `rejeu-scenario.cjs` utilise les références SEMO réelles du scénario, uniques.
  `recette-api.cjs` crée ses propres agents et références (suffixe par exécution) : il est rejouable
  sur la même base.
- Jobs exécutés dans la requête : `QUEUE_CONNECTION=sync` (comme en production cPanel).
- API servie en **HTTP** (`php artisan serve`, copie locale ou instance de recette sans TLS) :
  `recette-api.cjs` vérifie qu'aucun en-tête HSTS n'est envoyé en HTTP et s'arrête donc derrière HTTPS,
  où `SecurityHeaders` l'envoie à bon droit. **Jamais contre la production** : les deux scripts créent des
  données (agents, missions, lots, paiements confirmés).

> `php artisan serve` ne transmet pas les variables d'environnement au serveur quand un `.env`
> existe : écrire `DB_*`, `QUEUE_CONNECTION=sync`… **dans le `.env`** (ce que fait le job CI).

## Commandes

```bash
cd backend
# .env : DB_CONNECTION=sqlite, DB_DATABASE=/chemin/absolu/recette.sqlite, QUEUE_CONNECTION=sync
touch /chemin/absolu/recette.sqlite
php artisan migrate:fresh --seed --force
QUEUE_CONNECTION=sync php artisan serve --host=127.0.0.1 --port=8000 &

cd ..
API_URL=http://127.0.0.1:8000 node scripts/recette/rejeu-scenario.cjs
API_URL=http://127.0.0.1:8000 node scripts/recette/recette-api.cjs
```

Ne jamais viser la base de travail ni la production : le scénario crée des agents, des missions
et des lots, et `recette-api.cjs` change des numéros d'agents qu'il a lui-même créés.

## Variables

| Variable | Défaut | Rôle |
|----------|--------|------|
| `API_URL` | `http://127.0.0.1:8000` | Racine du serveur Laravel (sans `/api`) |
| `RECETTE_PASSWORD` | `password` | Mot de passe des comptes de démonstration |

## Limites de débit

L'API limite les connexions (5 par minute et par IP, réglable dans Paramètres › Sécurité), les
appels (60 par minute et par compte), les exports de rapports (10 par minute) et les opérations de
paiement (5 par minute et par compte) — `AppServiceProvider`, limiteurs `login`, `api`, `export`, `paiement`.
Sur un **429**, les scripts attendent la fenêtre suivante (`Retry-After`) puis rejouent la requête :
une exécution complète dure environ 4 minutes par script, dont
l'essentiel en attentes.

## Sortie attendue

```
Rejeu du scénario de démonstration sur http://127.0.0.1:8000

== Connexions (comptes de démonstration)
  [OK] connexion gestion@anatt.bj (GESTIONNAIRE_OM)
  …
== Étape D — fichier MTN (finance@)
  [OK] aperçu MTN AVANCE : 2 bénéficiaires, 406 000
  [OK] contenu exact du fichier MTN (CRLF, saut de ligne final)
  …
Rejeu du scénario de démonstration : 119 contrôles conformes.
```

En cas d'écart :

```
  [ECART] validation finale sans numéro refusée (HTTP 422 « Renseignez le numéro … »)
          HTTP 200, motif « … »

Recette interrompue au premier écart (section « Étape C — … »), après 31 contrôle(s) conforme(s).
```
