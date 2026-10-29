# ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money

Application de suivi des **ordres de mission émis par SEMO** et de **génération des fichiers de paiement** Mobile Money (MTN / Moov) pour l'ANaTT (Agence Nationale des Transports Terrestres, Bénin).

> **Règle d'or** — l'application **ne crée pas** les OM (SEMO les émet) et **ne réalise pas** les paiements (faits sur les plateformes MTN/Moov). Elle **retranscrit** les OM et **génère** les fichiers de paiement à déposer manuellement. Aucune API SEMO, aucune API opérateur, aucun réimport de fichier résultat.

---

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel 12 · **PHP 8.4.x (64 bits)** · Sanctum · MySQL 8 · (Redis facultatif) · heure du Bénin (`Africa/Porto-Novo`) |
| Frontend | Vue **3.5.44 stable** (Composition API, TypeScript), épinglée · Vite 8 · Tailwind (thème **vert ANaTT**) · Pinia |
| Tests | Pest / PHPUnit (691 tests, 76 fichiers) · Vitest (215 tests) · Playwright (parcours critique e2e) |

---

## Démarrage rapide (Windows)

Prérequis : **WAMP** (MySQL démarré), **PHP 8.4.x 64 bits**, **Node 20.19+ ou 22.12+** (Vite 8), **Composer**.

> PHP 8.4 est un **minimum** : `composer.lock` épingle Symfony 8 et `spatie/laravel-activitylog`,
> qui l'exigent. Sous 8.3, Composer refuse de charger l'autoloader. **PHP 8.5 n'est pas encore pris
> en charge** : `phpoffice/phpspreadsheet` (fichiers Moov, exports Excel) déclare `<8.5.0`, alors que le
> contrôle de plateforme de Composer ne vérifie que le minimum et le 64 bits — l'application démarrerait
> sans avertissement. Même contrainte en production (cf. [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) §4).

```powershell
cd C:\wamp64\www\anatt-ordre-mission

# Première fois (installe vendor + node_modules, crée le .env/clé, seed la démo)
.\start-dev.ps1 -Install -Fresh

# Les fois suivantes
.\start-dev.ps1

# Pour tout arrêter
.\stop-dev.ps1
```

Le script lance **3 fenêtres** : API (`http://localhost:8000`), worker de file, frontend (`http://localhost:5173`)
— 2 seulement avec `-NoWorker` (les traitements en file attendent alors un `queue:work` lancé à la main).
Si l'exécution est bloquée : `powershell -ExecutionPolicy Bypass -File .\start-dev.ps1`.

Application : **http://localhost:5173**

### Comptes de démonstration (mot de passe : `password`)

> Créés par le seed **uniquement** si `APP_ENV=local` (ou `testing`), avec les agents, missions et paiements
> fictifs. En production, le seed ne crée que le SUPER_ADMIN initial (`SUPERADMIN_EMAIL` /
> `SUPERADMIN_PASSWORD`), les zones et le barème.

| Email | Profil | Peut |
|---|---|---|
| `superadmin@anatt.bj` | SUPER_ADMIN | tout (dont super admins & paramètres) |
| `admin@anatt.bj` | ADMIN | utilisateurs, référentiels, barème, audit |
| `gestion@anatt.bj` | GESTIONNAIRE_OM | agents, retranscription des missions/OM |
| `finance@anatt.bj` | AGENT_FINANCIER | génération & confirmation des paiements |
| `controle@anatt.bj` | CONTROLEUR | étape 1 du circuit : contrôle de conformité de l'état |
| `daf@anatt.bj` | DAF | étape 2 du circuit : visa « Vu » sur l'état de paiement |
| `dg@anatt.bj` | DG | étape 3 du circuit : validation finale de l'état (ouvre le paiement) |
| `lecteur@anatt.bj` | LECTEUR | lecture seule |

---

## Démarrage manuel (sans script)

```powershell
# 1) Backend
cd backend
copy .env.example .env          # si absent
php artisan key:generate        # si APP_KEY vide
php artisan migrate --seed      # ou migrate:fresh --seed (DESTRUCTIF)

php artisan serve               # http://localhost:8000  (terminal A)
php artisan queue:work --queue=paiements,etats,default --tries=3   # (terminal B)
# .env.example règle QUEUE_CONNECTION=database : sans worker, les gros lots et exports
# restent en attente. QUEUE_CONNECTION=sync traite tout immédiatement (réglage de production).

# 2) Frontend
cd ..\frontend
npm ci                          # première fois — installe exactement les versions du lock
npm run dev                     # http://localhost:5173  (terminal C)
```

---

## Concepts métier clés

- **Catégorie** d'agent = `INDICE_SUP_800 / INDICE_400_799 / INDICE_INF_400`, déduite de l'indice (ou du salaire brut), reprise de SEMO :
  la fiche d'un agent exige l'un des deux, la catégorie ne se choisit pas.
- **Per diem** = `taux_journalier(catégorie × zone × type) × nb_jours` selon le **barème officiel ANaTT**
  (4 zones : `INTERIEUR`, `EXT_ZONE_I`, `EXT_ZONE_II`, `EXT_ZONE_III` — II = III ; règle des 12 h).
  Répartition : **avance 70 %** / **reliquat 30 %** (part de l'avance **paramétrable** par le SUPER_ADMIN, 70 % par défaut ; le reliquat absorbe l'arrondi). Exonération si prise en charge par un tiers.
  La répartition est **figée à la retranscription** : changer la part ne vaut que pour les OM saisis ensuite, et
  les écrans d'une mission existante (fiche et PDF de l'OM, état de paiement, génération des fichiers) affichent
  la part **de ses OM** (`part_avance` de l'API), pas le réglage du jour.
- **Une mission** regroupe **N ordres de mission** (1 OM = 1 bénéficiaire). Le **CAV** (conducteur) est payé selon **sa** catégorie.
- **Validation** : chaque mission porte **deux états de paiement** — l'état des **avances**, puis celui des
  **reliquats** — qui passent chacun le circuit **contrôleur → visa DAF → validation DG**. Un versement n'est
  payable que si l'état **de sa tranche** est validé. L'état des reliquats est signé au montant **payable**
  (somme des reliquats positifs). Les trois signataires doivent être trois personnes distinctes. Le PDF de
  chaque OM reprend les signatures de l'état des avances qui le couvre (même code de vérification) ; le
  document de référence reste l'état. Les missions sans état (reprise de données) se valident OM par OM.
- **Paiement** = génération d'un fichier (**1 mission × 1 tranche × 1 opérateur**) : MTN → `.txt`, Moov → `.xlsx`.
  Cycle : `EN_ATTENTE → GENERE → CONFIRME` (confirmation **manuelle** après dépôt). Régénération tant que non confirmé ;
  un lot passé en **erreur** remet ses versements en attente avec le numéro **actuel** de l'agent.
  **Idempotence** : un versement confirmé n'est jamais réinclus ; un lot confirmé n'est jamais réécrit — un
  versement devenu payable après coup part dans un **lot complémentaire** (`…_COMPL2`). Un OM dont tous les
  versements sont réglés (confirmés, ou trop-perçu recouvré) passe à **SOLDE** (une tranche nulle, part d'avance à 0 ou 100 %, ne le retient
  pas) ; un OM **rejeté** voit ses versements **annulés**. Après confirmation, l'écran de génération propose
  directement le lot complémentaire quand il reste des bénéficiaires payables.
- **Amendement (avenant)** : après paiement de l'avance, les jours facturés d'un OM peuvent changer ; l'avance
  est figée et seul le **reliquat** absorbe l'écart. Un reliquat négatif est un **trop-perçu**, récupéré par
  retenue sur salaire (écran *Régularisations*, document de retenue PDF) — jamais par Mobile Money.
  L'amendement est refusé quand le reliquat est déjà payé, qu'un trop-perçu a été recouvré ou qu'un fichier
  de l'OM attend son dépôt : ces cas relèvent d'un avenant formel ou d'une action sur le lot d'abord.
  Un amendement qui ramène le reliquat à 0, avance payée, solde l'OM ; une rallonge sur un OM soldé
  (avance à 100 % payée) le rouvre. La fiche, l'aperçu et le PDF de l'OM affichent alors « Avance
  engagée » et « Reliquat (ajusté par amendement) » ou « Trop-perçu ».
- **Notifications** (cloche + e-mail) : à chaque étape du circuit, elles vont aux comptes actifs qui
  **détiennent la permission** de l'étape suivante — droits effectifs, rôles personnalisés et droits
  individuels compris ; le SUPER_ADMIN n'est pas notifié.
- **Réglages appliqués par le serveur** : tout ce qu'enregistrent les écrans *Paramètres* et *Messagerie*
  agit réellement (opérateurs ouverts, notifications par type, durée de session, tentatives de connexion,
  HTTPS forcé, journal des connexions échouées, mode maintenance, nom de l'application). Un serveur SMTP
  saisi dans *Messagerie* **remplace** celui du `.env` dès l'e-mail suivant ; laissé vide, le `.env`
  s'applique. Bouton « Envoyer un e-mail de test » : un échec affiche le message du serveur de messagerie.
  Une valeur refusée (part d'avance hors 0-100, port invalide…) est expliquée à l'écran et rien n'est
  enregistré ; « Enregistrer tout » applique **tous** les onglets de *Paramètres* à la fois.
- **Refus expliqués** : tout refus du serveur affiche son motif, une fois et en français — signature refusée
  par la séparation des tâches, numéro d'état manquant ou déjà attribué, amendement impossible, adresse
  e-mail déjà utilisée… (`resources/lang/fr/validation.php` pour les règles génériques). Textes de l'interface et
  messages de l'API sont accentués (« Accès interdit », « Agent créé avec succès. »), y compris sur les pages d'erreur.
- **E-mails** : un e-mail de notification qui ne part pas (serveur SMTP injoignable) est journalisé et
  **n'empêche jamais** l'action qui notifie — validation, signature, rejet, amendement, confirmation d'un lot ;
  la notification reste dans la cloche.
- **Heure** : les horodatages (paiements, signatures, journal) sont à l'heure du Bénin ; l'API les
  renvoie en UTC (`…Z`). Un paiement confirmé le 1er du mois juste après minuit compte dans ce mois.

---

## Sécurité & données sensibles

- Les **fichiers de paiement** générés (`.txt` MTN / `.xlsx` Moov) contiennent des données à
  caractère personnel (nom, téléphone, montant). Ils sont écrits sur le disque **`local`**
  (`storage/app/private/paiements/{mission_id}/`, un dossier par mission), **hors racine web**, et ne sont servis que via les endpoints
  de téléchargement **authentifiés** (`permission:paiements.*`). Ils ne sont **pas chiffrés** au repos :
  prévoyez un contrôle d'accès disque et une politique de purge/rétention côté exploitation.
- Accès par **permission** (Sanctum + middleware `permission:{clé}`), `Gate::before` pour le SUPER_ADMIN.
- **Anti-double-paiement** : un versement `CONFIRME` — ou porté par un lot confirmé — n'est jamais
  réinclus (idempotence), et l'intégrité des états signés est scellée par **HMAC** (montants + tranche).
- **Session** : seul un 401 (jeton expiré ou révoqué) déconnecte. Une erreur passagère au rechargement
  (429, 5xx, réseau) garde la session : l'écran d'erreur s'ouvre et « Réessayer » ramène à la page demandée.
- **Secret SMTP** : chiffré au repos avec l'`APP_KEY`, jamais renvoyé par l'API (masque `********`),
  déchiffré seulement au moment d'envoyer ; à ressaisir si l'`APP_KEY` change.
- **Séparation ordonnateur / payeur** : un profil ou un utilisateur qui valide l'état (`om.approuver`) ne
  peut pas générer ni confirmer les paiements ; la règle est aussi vérifiée pour chaque membre d'un profil
  modifié dans la matrice des permissions.

---

## Exploitation

- **Tâches planifiées** : une tâche Cron (recommandée, [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) §9) appelle
  `php artisan schedule:run` chaque minute. Elle lance
  `db:backup --compress` chaque jour à 02:00 (rétention 30 jours, dans `storage/app/backups/`) et purge les
  jetons de connexion expirés (`sanctum:prune-expired`).
- **Files d'attente** : en mutualisé, `QUEUE_CONNECTION=sync` (aucun worker ; cf.
  `backend/.env.production.example`) ; avec un worker, voir [`backend/docs/queues.md`](backend/docs/queues.md).
  En `sync`, l'e-mail part pendant la requête : un serveur SMTP lent la ralentit, sans la faire échouer.
  Après une modification de *Messagerie*, envoyer un e-mail de test (le super administrateur n'est jamais
  notifié, il ne verrait pas une panne autrement).
- **Restauration** : écran *Paramètres › Sauvegardes* (SUPER_ADMIN, confirmation par recopie du nom du
  fichier) ou `php artisan db:restore <fichier> --force`. Une **sauvegarde de sécurité** de l'état courant
  est prise avant d'écraser la base. Requiert `mysqldump` et `mysql` (`DB_DUMP_BINARY` /
  `DB_RESTORE_BINARY` dans le `.env`). À tester sur une base de recette avant d'en avoir besoin.
  La matrice des permissions de la sauvegarde s'applique dès la requête suivante (cache purgé).
- **Hors sauvegarde de la base** : `storage/app/settings.json` (part d'avance, exercice, SMTP…), les paraphes
  des signataires et les fichiers produits (PDF, fichiers de paiement) vivent sur le disque. Les conserver
  en sauvegardant `storage/` ; ne jamais écraser ce dossier lors d'une mise à jour.
- **Mise à jour d'une instance** : voir [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) (§ *Mise à jour*, *Notes de
  version 1.3* et *1.4*) et le runbook [`docs/runbook-mise-a-jour-cron.md`](docs/runbook-mise-a-jour-cron.md).
  **Version 1.4** : la migration du fuseau décale d'une heure les horodatages existants — application en
  **maintenance** pendant l'envoi du code et la migration, caches purgés (`optimize:clear`) **avant**
  `migrate`, puis contrôles de [`docs/checklist-post-deploiement.md`](docs/checklist-post-deploiement.md) §06.

---

## Structure

```
anatt-ordre-mission/
├── CLAUDE.md            # contexte/spec détaillée du projet
├── AGENTS.md            # consignes pour les contributeurs (humains et assistants)
├── DEPLOY-CPANEL.md     # déploiement et mise à jour en production
├── .github/workflows/   # CI (Lint, Pest, Vitest, Playwright, build)
├── start-dev.ps1        # démarre l'env de dev (Windows)
├── stop-dev.ps1         # arrête l'env de dev
├── backend/             # API Laravel
│   ├── app/{Http,Models,Services,Jobs}
│   ├── database/{migrations,seeders,factories}
│   ├── tests/{Feature,Unit}
│   ├── docs/            # queues.md (files), openapi.yaml (référence API)
│   └── README.md        # prise en main de l'API
├── frontend/            # SPA Vue 3
│   ├── src/{views,components,stores,services,types,utils}
│   ├── e2e/             # parcours critique Playwright
│   └── README.md        # prise en main du SPA
└── docs/                # guides, procédures, revue, galerie des écrans
```

---

## Tests

```powershell
cd backend
.\vendor\bin\pest                 # 691 tests (76 fichiers)
.\vendor\bin\pint --test          # style de code (vérification ; sans --test : corrige)

cd ..\frontend
npm run type-check                # vérification TypeScript
npm run test:unit -- --run        # Vitest
npm run lint:check                # ESLint + oxlint (vérification ; `npm run lint` corrige)
npm run build                     # build de production
npm run test:e2e                  # Playwright — parcours critique (API mockée, aucun backend requis)
```

> Le CI exécute ces mêmes vérifications à chaque PR : un job **Lint** (Pint + ESLint/oxlint, en mode
> vérification), les suites Pest et Vitest, le build et le parcours Playwright.
>
> La suite Pest s'exécute **sans `.env`** (`phpunit.xml` fournit une `APP_KEY` de test). Pour Playwright
> avec un Chromium déjà installé d'une autre version : `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/chemin/chromium`.
> Sur une machine sans écran (serveur, conteneur), pointer cette variable vers le binaire
> `headless_shell` et ajouter `--project=chromium`.

---

## Documentation

| Document | Contenu |
|---|---|
| `CLAUDE.md` | Modèles de données, endpoints API, règles métier, design system |
| `AGENTS.md` | Consignes de contribution : environnement, vérifications, règles à ne pas casser |
| `CHANGELOG.md` | Historique des modifications |
| `docs/Guide-Utilisateur-ANaTT.docx` | Guide utilisateur (v1.4) — généré par `docs/generer_guide_utilisateur.py` |
| `docs/ANaTT_Documentation_Technique_Fonctionnelle.docx` | Documentation technique et fonctionnelle (v1.4) — générée par `docs/generer_documentation_technique.py` |
| `docs/SCENARIO-DEMO.md` | Scénario de démonstration (fichiers réels MTN / Moov ; variantes : part d'avance §8.5 avec refus expliqué, configuration e-mail §8.6 avec validation malgré un SMTP en panne, maintenance §8.7), rejoué le 27/10/2026, complété sur l'API réelle le 28/10, rejoué le 29/10 |
| `DEPLOY-CPANEL.md`, `UPLOAD-VENDOR.md` | Déploiement sur cPanel, mise à jour, restauration |
| `docs/checklist-post-deploiement.md`, `docs/runbook-mise-a-jour-cron.md` | Vérifications et déroulé du jour J |
| `docs/circuits-validation.md`, `docs/amendement-om.md` | Circuits de validation, amendement et trop-perçus |
| `docs/rbac-flexible.md`, `docs/workflows-par-profil.md` | Droits d'accès et parcours par profil |
| `docs/parametrage-part-avance.md`, `docs/annee-fiscale.md`, `docs/categories-indice.md` | Paramétrages métier |
| `docs/revue-projet-2026-10.md` | Revue complète d'octobre 2026 (couverture par module, correctifs, suites à donner) |
| `docs/deploiement-circuit-validation.md`, `docs/plan-validation-daf-dg.md` | Documents **historiques** (circuit par OM retiré), conservés pour mémoire |
| `docs/00_galerie_index.html` … | Galerie des écrans réels (46 écrans, recapturée le 29/10/2026) : captures par `docs/capturer_ecrans.cjs` (base de démonstration neuve, build de production, navigateur en `LANG=fr_FR.UTF-8`), pages par `docs/generer_maquettes.py` |
| `backend/README.md`, `frontend/README.md` | Prise en main de l'API et du SPA (commandes, repères dans le code) |
| `backend/docs/queues.md`, `backend/docs/openapi.yaml` | Files et worker ; description OpenAPI 3.0 de toutes les routes (verrouillée par `OpenApiTest` ; réponses réelles vérifiées par `OpenApiReponsesTest`) |
