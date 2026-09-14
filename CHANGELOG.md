# Changelog

Toutes les modifications notables de l'application ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money.

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnement : dates ISO (l'application n'a pas encore de release taguée).

---

## [Non publié]

### Ajouté

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
