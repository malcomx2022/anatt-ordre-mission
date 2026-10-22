# Changelog

Toutes les modifications notables de l'application ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money.

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnement : dates ISO (l'application n'a pas encore de release taguée).

---

## [Non publié]

### Modifié

- **Revue d'octobre 2026, seconde passe — points restants traités** (détail :
  `docs/revue-projet-2026-10.md` §4).
  - **État des reliquats** : il est signé au montant **payable**, c'est-à-dire la somme des
    reliquats positifs. Les trop-perçus sont mentionnés à part sur le PDF.
  - **Lots complémentaires** : nouvelle colonne `lots_paiement.sequence`, avec une migration.
    Un lot confirmé n'est jamais réécrit. Un versement devenu payable ensuite part dans un lot
    de séquence suivante.
  - **Lot en `ERREUR`** : ses versements reviennent à `EN_ATTENTE`, avec les coordonnées
    actuelles de l'agent.
  - **Amendement** : il est refusé tant qu'un fichier contenant l'OM attend son dépôt, et
    les états sont verrouillés pendant l'opération.
  - **OM rejeté** : ses versements passent à `ANNULE`.
  - **Mission** : un même agent ne peut y figurer qu'une fois.
  - **Séparation ordonnateur/payeur** : elle est désormais contrôlée sur l'effectif de
    chaque membre d'un profil.
  - **Sauvegardes** : ajout du téléchargement et de la restauration, avec une sauvegarde
    de sécurité préalable (commande `db:restore`).
  - **Frontend** :
    - suivi des gros lots générés en asynchrone ;
    - part d'avance qui ne reste plus figée à 70 % ;
    - boutons Valider et Rejeter pour le circuit par OM ;
    - messages d'erreur sur la fiche OM et l'aperçu PDF au lieu d'une page blanche ;
    - compte à rebours sur la page de maintenance ;
    - corrections du design system (`flag-bar` sur la connexion, icônes SVG).
  - **Tests** : la suite Pest s'exécute sans `.env` (`APP_KEY` de test). Le binaire
    Chromium de Playwright peut être fourni par variable d'environnement.

### Corrigé

- **Revue complète d'octobre 2026 — huit défauts corrigés** (détail et points
  restants : `docs/revue-projet-2026-10.md`). Bloquants reproduits sur l'API réelle :
  un OM **rejeté** avant le récapitulatif redevenait `VALIDE` et **payable** à la
  validation de l'état des avances (`EtatPaiementService::propager` exclut `REJETE`) ;
  deux missions de **même objet** partageaient le même fichier de paiement, si bien que
  le re-téléchargement d'un lot servait celui de l'autre (un dossier par mission,
  `paiements/{mission_id}/`) ; sur le circuit par OM, un amendement après paiement de
  l'avance recalculait 70/30 en ignorant l'avance décaissée (l'avance `GENERE`/`CONFIRME`
  est désormais engagée). Également : 401 JSON au lieu d'une page 500 sur `/api/*` sans
  en-tête `Accept` ; téléversement des paraphes (le FormData partait sérialisé en JSON) ;
  mois courant calculé en UTC (décalé d'un mois au Bénin dans les rapports) ; catégorie
  suggérée fausse quand l'indice est vidé ; historique OM-08 muet (champs et codes
  d'action obsolètes).

- **Déploiement : la version de PHP annoncée rendait l'API inutilisable**
  `DEPLOY-CPANEL.md` §4 demandait de sélectionner « PHP 8.2 ou 8.3 » dans cPanel.
  Or `composer.lock` épingle six paquets de production qui exigent **PHP ≥ 8.4** :
  `symfony/clock`, `symfony/css-selector`, `symfony/event-dispatcher`,
  `symfony/string`, `symfony/translation` (Symfony 8) et
  `spatie/laravel-activitylog`. Composer grave cette exigence dans
  `vendor/composer/platform_check.php`, que `autoload_real.php` charge à
  **chaque** requête : sous PHP 8.3 l'API répond **500 sur tous les appels**
  (« Your Composer dependencies require a PHP version ">= 8.4.0" »). Le guide
  aurait donc conduit tout droit à une instance morte.
  `composer.json` annonce encore `"php": "^8.2"` — laissé tel quel puisque c'est
  le **lock** qui détermine le `vendor/` uploadé, mais la contrainte réelle est
  désormais écrite noir sur blanc, avec la sortie de secours (rétrograder vers
  Symfony 7 en local et régénérer le lock) si l'hébergeur ne propose pas 8.4.
  Corrigé dans `DEPLOY-CPANEL.md` (§0, §4, toutes les commandes Cron passent à
  `ea-php84`), `docs/checklist-post-deploiement.md`, `README.md` et `CLAUDE.md`.

- **Deux tâches Cron se disputaient le même fichier de log**
  `migrate:status` écrivait dans `migration.log` avec `>` et `migrate --force`
  y ajoutait avec `>>`. Une tâche de statut restée active — cPanel n'ayant pas
  de planification « une seule fois », elle se rejoue toutes les cinq minutes —
  **tronquait le log de migration** en cours de lecture. Un fichier distinct par
  étape (`migration-status.log` / `migration-run.log`), et la règle « une seule
  tâche active à la fois » est explicite.

- **Faits obsolètes dans `README.md` et `CLAUDE.md`**
  Laravel 11 → **12** (`laravel/framework v12.56.0`), PHP 8.3+ → **8.4+**,
  et le compte de tests (495 / 59 fichiers → **531 / 62**), mesuré en exécutant
  la suite.

### Ajouté

- **`docs/runbook-mise-a-jour-cron.md` — déroulé du jour J**
  Les tâches Cron étant disponibles sur l'instance ANaTT, la procédure de
  référence de `DEPLOY-CPANEL.md` est désormais celle par Cron (Terminal cPanel
  et migration à blanc en local deviennent des **replis**, pour les hébergeurs
  qui ne l'offrent pas). Le runbook en tire une page opérationnelle : neuf
  étapes cochables, commandes prêtes à coller, de la sauvegarde au ménage des
  tâches temporaires, avec le retour arrière en pied de page.
  Trois pièges propres à Cron y sont traités, qu'aucune documentation du projet
  ne couvrait : cPanel n'a pas de planification « une seule fois » (régler sur
  *Once Per Five Minutes*, laisser passer, **supprimer**) ; un `%` dans la
  commande est interprété comme un saut de ligne et tronque la tâche
  silencieusement ; et l'adresse e-mail du Cron Job donne une seconde fenêtre
  sur la sortie quand le fichier de log n'a pas été écrit du tout — le symptôme
  d'un mauvais chemin PHP.

- **Mise à jour d'une instance en ligne sans accès SSH — procédure complète**
  La section « Mise à jour » de `DEPLOY-CPANEL.md` ne couvrait la migration de la
  base qu'en une ligne (« Terminal cPanel ou tâche Cron »), sans dire quoi faire
  quand l'hébergeur n'offre ni l'un ni l'autre. Elle décrit désormais, dans
  l'ordre d'exécution : sauvegarde préalable (base **avec `DROP TABLE`**, SPA,
  `.env`), **relevé des migrations manquantes** depuis phpMyAdmin
  (`SELECT migration FROM migrations ORDER BY id;` comparé au contenu de
  `database/migrations/`), upload du code **avant** la migration, puis
  **trois voies** : tâche Cron ponctuelle (la plus largement disponible),
  Terminal cPanel, et — si aucun PHP en ligne de commande n'est accessible —
  **migration à blanc d'une copie locale de la base de production, puis
  réimport**. Cette troisième voie laisse Laravel jouer lui-même les migrations
  et tenir la table `migrations` à jour : aucun SQL n'est rédigé à la main, donc
  aucune divergence possible avec ce qu'attend le code.
  Deux pièges d'exploitation sont documentés : le chemin PHP des tâches Cron
  (`/usr/local/bin/php` est un alias pointant souvent vers une **autre** version
  que celle de « Select PHP Version » — d'où `/opt/cpanel/ea-php83/root/usr/bin/php`),
  et la **redirection de sortie** vers `/home/anatt/` (hors racine web), seule
  fenêtre sur le résultat quand on n'a pas de terminal.
  S'y ajoutent la purge des caches **sans artisan** (les trois dossiers à vider,
  dont `storage/framework/cache/data` qui porte la matrice des permissions), une
  procédure de **retour arrière**, un récapitulatif des artefacts propre à la mise
  à jour (avec ce qu'il ne faut **pas** écraser : `.env`, `storage/`,
  `runtime-config.js`) et huit entrées de dépannage spécifiques.

- **`backend/.env.production.example` — le gabarit manquait au dépôt**
  `DEPLOY-CPANEL.md` §5.1 demandait de copier ce fichier en `anatt-api/.env`,
  mais il n'avait jamais été versionné : la règle `.gitignore` `.env.*` l'excluait
  et seul `!.env.example` était ré-autorisé. Un déploiement suivant le guide à la
  lettre butait donc sur un fichier absent. Le gabarit est ajouté (valeurs de
  production commentées, aucune valeur réelle) et l'exception `.gitignore`
  étendue. Les clés qu'il porte ont été vérifiées une à une dans `config/`
  (`CORS_ALLOWED_ORIGINS`, `WORKFLOW_SEPARATION_TACHES`, `PAIEMENT_*`,
  `MTN_COMPTE`, `DB_DUMP_BINARY`, `FRONTEND_URL`, `SUPERADMIN_*`).

- **Garde-fou sur les modèles d'import du personnel**
  Les fichiers distribués aux utilisateurs (`docs/modele-import-agents.csv`, à
  séparateur **point-virgule** comme le produit Excel en configuration française,
  et son jumeau `.xlsx`) sont désormais importés par la suite de tests elle-même.
  Leur lisibilité ne tenait à aucun réglage du projet : c'est le défaut
  `excel.imports.csv.delimiter = null` de maatwebsite/excel qui laisse
  PhpSpreadsheet **inférer** le séparateur. Publier `config/excel.php` et
  « harmoniser » ce délimiteur sur celui des exports (`,`) rendrait le modèle
  illisible — silencieusement : une seule colonne reconnue, un rapport d'erreurs
  incompréhensible pour l'utilisateur. Vérifié en provoquant la régression :
  le modèle n'importe alors plus aucun agent et les tests tombent.
  Test : `backend/tests/Feature/ImportModeleAgentsTest.php` (4 cas : modèle CSV
  avec BOM et colonnes alignées, couverture des 3 catégories et des 2 opérateurs,
  modèle XLSX avec le téléphone préservé en texte, et fichier à séparateur `,`).

### Corrigé (documentation)

- **`.gitignore` : commentaire contredisant le déploiement**
  Le bloc « Builds » affirmait que `frontend/dist/` était « volontairement
  VERSIONNÉ (déploiement FTP sans build serveur) », alors que `dist/` n'est
  versionné ni en fait (`git ls-files` le confirme) ni en intention —
  `DEPLOY-CPANEL.md` §2 et le job CI `dist` le produisent comme artefact
  `frontend-dist`. Le commentaire est corrigé et `frontend/dist/` explicitement
  ignoré, pour qu'un build local ne se retrouve pas commité par accident.

### Modifié

- **Catégories d'indice nominatives — `ORDRE_1/2/3` remplacé**
  La catégorie d'un agent ne s'appelle plus « ordre » mais porte l'indice
  qu'elle désigne : `INDICE_SUP_800` (« Indice ≥ 800 »), `INDICE_400_799`
  (« Indice 400-799 ») et `INDICE_INF_400` (« Indice < 400 »). Le vocabulaire
  de l'application rejoint ainsi celui déjà imprimé dans la colonne
  *Grade/Indice* des états de paiement signés.
  Les écrans et les PDF affichent désormais un **libellé** et non plus le code
  brut (personnel, retranscription d'OM, matrice du barème, fiches, impressions).
  Les seuils (800 / 400 / 372 000 / 186 000), jusqu'ici dupliqués en quatre
  endroits côté serveur, sont centralisés.
  **Transition** : les anciens codes `ORDRE_*` ne sont plus produits mais restent
  **acceptés en entrée** — filtres `?categorie=`, fichiers d'import déjà préparés,
  création d'agent, de barème et retranscription — et sont traduits silencieusement.
  Le journal d'audit n'est pas réécrit : ses entrées antérieures conservent
  l'ancien vocabulaire.
  Fichiers : `backend/app/Support/CategorieIndice.php` (nouveau, source de vérité),
  `backend/database/migrations/2026_09_14_090000_renommer_categories_ordre_en_indice.php`
  (bascule des trois colonnes `enum` en trois temps — élargir, traduire, refermer),
  `frontend/src/types/agent.ts` (`CATEGORIE_LABELS`, `categorieLabel()`),
  + requests, services, seeders, factories, 10 écrans et les PDF.
  Tests : `backend/tests/Feature/CategorieIndiceTransitionTest.php` (7 cas :
  vocabulaire produit, anciens codes acceptés à la création, dans les filtres
  agents et barème, à l'import CSV, code inconnu toujours rejeté) et
  `backend/tests/Unit/CategorieTest.php` (normalisation, libellés, plages).
  Documentation : `docs/categories-indice.md`.
  Non impactés (vérifié) : scellé HMAC des états, états déjà signés, formats de
  fichiers MTN/Moov, et tous les montants.

### Ajouté

- **Auto-synchronisation de l'état de paiement avec ses OM**
  Dès qu'un OM est créé, modifié (montant, statut) ou supprimé, l'état de la
  mission — s'il est en BROUILLON ou REJETE — est silencieusement recalé. Plus
  besoin de cliquer sur *« Rafraîchir »* : le brouillon reflète toujours les
  OM. Un état signé (CONTROLE / VISA_DAF / VALIDE) est **protégé** : le scellé
  financier apposé lors de la signature n'est jamais altéré par une mutation
  d'OM survenue après coup.
  Fichiers : `backend/app/Observers/OrdreMissionObserver.php`,
  `backend/app/Services/EtatPaiementService.php` (méthode `synchroniser`),
  `backend/app/Models/OrdreMission.php` (attribut `#[ObservedBy]`).
  Test : `backend/tests/Feature/EtatAutoSyncTest.php` (4 cas : rejet d'OM,
  état signé intact, ajout d'OM sur état REJETE, non-création d'état
  fantôme quand aucun état n'existe).

- **CI GitHub Actions** — un workflow `.github/workflows/ci.yml` exécute Pest
  (backend) et Vitest + `type-check` (frontend) sur chaque PR et push sur
  `main`. Objectif : empêcher qu'un merge silencieux ne supprime à nouveau des
  fonctions référencées par les tests (mécanisme qui a laissé passer l'incident
  du 30/07).

### Corrigé

- **API — clé `data` toujours exposée dans les réponses `success()`**
  `App\Http\Traits\ApiResponse::success()` retournait `{}` (objet vide) lorsque
  `$data === null`, au lieu de `{data: null}`. Côté frontend, `unwrap({})`
  retournait alors un objet vide truthy, ce qui poussait les écrans à afficher
  une ressource fantôme (le cas typique : une mission sans état de paiement
  ouvrait un écran BROUILLON à 0 FCFA). La clé `data` est désormais toujours
  présente, même à `null`.
  Fichier : `backend/app/Http/Traits/ApiResponse.php`.

- **Frontend — garde-fou anti-état-fantôme** *(restauration d'un fix perdu au
  merge du 30/07 — commit d'origine `2e8a186`)*
  `etatsService.parMission()` considère désormais qu'un objet sans `statut`
  n'est PAS un état. La vue « État de paiement » retombe alors sur l'écran
  « Établir le récapitulatif » au lieu d'afficher un faux BROUILLON à 0 FCFA.
  Ce garde-fou complète le fix backend ci-dessus : il protège les postes qui
  n'auraient pas encore le back à jour.
  Fichier : `frontend/src/services/etats.ts`.

- **Frontend — vraie cause d'un échec de téléchargement rendue visible**
  *(restauration d'un fix perdu au même merge — commit d'origine `13cc8f8`)*
  Sur une requête `responseType: 'blob'` qui échoue, Axios livre le corps
  d'erreur en Blob : `error.response.data.message` était alors `undefined` et
  l'utilisateur ne voyait qu'un « impossible de télécharger » générique.
  L'intercepteur `useApi` relit désormais les Blobs JSON d'erreur et les
  reconvertit en `{ message, errors }` : le vrai motif (barème introuvable,
  stockage non inscriptible, etc.) remonte jusqu'au toast.
  Fichier : `frontend/src/composables/useApi.ts`.
  Tests associés : `frontend/src/__tests__/useApi.spec.ts` (référençaient déjà
  `normaliserErreurBlob` mais échouaient silencieusement sans CI — voir plus
  bas).

### Notes de maintenance

- **Branches distantes obsolètes** (état au 31/07) — toutes à 0 commit d'avance
  sur `main`, aucun apport unique. Suppression recommandée après validation :

  ```bash
  git push origin \
    --delete arena/019fae24-anatt-ordre-mission \
             claude/list-root-directories-cUxVz \
             claude/workflow-adjustments-e1vmy9 \
             docs/maj-documentation-technique
  git fetch --all --prune
  ```

- **Recommandation GitHub** — activer *Branch protection* sur `main` avec
  « Require status checks to pass before merging » cochant les 2 jobs `Backend
  · Pest` et `Frontend · Vitest + type-check`. C'est ce qui rend la CI
  réellement bloquante pour les PR.
