# ANaTT — SPA Vue (`frontend/`)

Interface de l'application de suivi des ordres de mission SEMO et de génération des fichiers de
paiement Mobile Money. Elle consomme l'API Laravel de `../backend/`.

> Vue d'ensemble, règles métier et installation complète : [`../README.md`](../README.md) et
> [`../CLAUDE.md`](../CLAUDE.md) (charte graphique au §4, registre des écrans au §5). Notes pour les
> agents de code : [`../AGENTS.md`](../AGENTS.md). Ce fichier ne reprend que ce qui est propre au SPA.

## Prérequis

- **Node 20.19+ ou 22.12+** (exigé par Vite 8 ; la CI utilise Node 22).
- Vue **3.6 bêta**, figée par `package-lock.json` : installez avec `npm ci`, pas `npm install`, pour ne
  pas changer de bêta.

## Démarrer en local

```bash
npm ci
npm run dev        # http://localhost:5173 — l'API est attendue sur http://localhost:8000
```

L'URL de l'API se règle **après build**, sans recompiler, dans `public/runtime-config.js`
(`window.__ANATT_CONFIG__.apiUrl`). Vide en développement : `VITE_API_URL` s'applique, ou
`http://localhost:8000` par défaut. En production, ne pas écraser le `runtime-config.js` déployé
(`../DEPLOY-CPANEL.md` §6).

## Vérifier

```bash
npm run type-check        # vue-tsc
npm run lint:check        # oxlint + ESLint, en vérification (npm run lint corrige)
npx vitest run            # 210 tests ; « npm run test:unit » seul reste en mode surveillance
npm run build             # type-check + build de production dans dist/
npm run test:e2e          # Playwright, parcours critique (e2e/parcours-critique.spec.ts), API simulée
```

Playwright avec un Chromium déjà installé : `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/chemin/chromium npm run test:e2e`.
Sans écran, pointez sur le binaire *headless shell* et ajoutez `--project=chromium`, comme la CI.

## Repères dans le code

| Dossier | Contenu |
|---------|---------|
| `src/views/` | Écrans (registre de `../CLAUDE.md` §5, plus les écrans ajoutés : états de paiement, régularisations, signatures, permissions par utilisateur…) |
| `src/components/shared/` | Composants communs (`AppLayout`, `AppButton`, `AppTable`…) et `navigation.ts`, le menu latéral |
| `src/components/paiements/PaiementGeneration.vue` | Génération MTN / Moov, lots complémentaires, suivi des gros lots |
| `src/router/index.ts` | Routes et `meta.permission` |
| `src/services/` | Un client par domaine ; `http.ts` partage l'instance axios et normalise la pagination |
| `src/stores/`, `src/composables/` | Pinia (`auth`, `notifications`, `ordres-mission`) ; `useApi` (instance axios : URL de l'API, jeton Bearer, verrou 423), `useAuth`, `usePartAvance`, `useSearch`, `useToast` |
| `src/utils/` | `benin.ts` (téléphone, catégorie, `moisLocal`), `permissions.ts`, `roles.ts`, `etats.ts`, `exercice.ts` |
| `src/__tests__/`, `e2e/` | Vitest ; Playwright |

## Points d'attention

- **Trois niveaux alignés** : une entrée de menu (`navigation.ts`) porte la même clé de permission que sa
  route (`router/index.ts`), qui porte celle exigée par l'API. `src/__tests__/navigation.spec.ts` le
  vérifie : aucun lien mort, aucun écran hors menu, aucun 403 après un clic.
- **Dates** : construisez un mois `YYYY-MM` avec `moisLocal()`, jamais `toISOString()` (décalage UTC au Bénin).
- **Téléversement** : un envoi `FormData` doit passer `headers: { 'Content-Type': 'multipart/form-data' }`,
  sinon axios sérialise le fichier en `{}`.
- **Charte** : Sora pour les titres et libellés, DM Sans pour le texte, icônes SVG en ligne
  uniquement, `flag-bar` avant chaque pied de page.
- **Session** : `stores/auth.ts` → `fetchUser` ne met fin à la session que sur un 401 ; une erreur passagère
  est relancée sans toucher au jeton, et le garde du routeur ouvre l'écran 500 (« Réessayer » ramène à la page
  demandée) ou, sur un 503, l'écran Maintenance.
- **Refus 422** : l'API renvoie « Donnees invalides. » et le motif dans `errors` ; afficher le motif
  (`getValidationErrors`), pas le message générique.
- Les pages d'écran de `../docs/` (`00_galerie_index.html` …) sont tirées de captures de l'application réelle
  (`docs/capturer_ecrans.cjs`) ; à refaire quand un écran change.
