# Changelog

Toutes les modifications notables de l'application ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money.

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnement : dates ISO (l'application n'a pas encore de release taguée).

---

## [Non publié]

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

### Ajouté

- **CI GitHub Actions** — un workflow `.github/workflows/ci.yml` exécute Pest
  (backend) et Vitest + `type-check` (frontend) sur chaque PR et push sur
  `main`. Objectif : empêcher qu'un merge silencieux ne supprime à nouveau des
  fonctions référencées par les tests (mécanisme qui a laissé passer l'incident
  du 30/07).

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
