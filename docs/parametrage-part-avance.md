# Pourcentage avance/reliquat paramétrable

## Résumé

La répartition **avance / reliquat** d'un per diem, jusque-là figée à **70 % / 30 %**,
est désormais **entièrement paramétrable** par un SUPER_ADMIN. La valeur
(`partAvance`, entier 0–100) est stockée dans `settings.json` ; le reliquat s'en déduit
(`100 − partAvance`) et absorbe l'arrondi.

> Défaut : **70 %**. Aucun changement de comportement pour une installation existante.

## Où se règle-t-il

- **Écran** : Administration → Paramètres → *Part de l'avance (%)* (le champ *Part du
  reliquat* est calculé automatiquement).
- **Permission** : `parametres.gerer` (SUPER_ADMIN) pour l'écriture.
- **Stockage** : clé `partAvance` dans `storage/app/settings.json`.

## API

| Route | Accès | Rôle |
|-------|-------|------|
| `GET /api/admin/parametres/part-avance` | **tout profil authentifié** | Lecture (pilote l'affichage) |
| `GET/PUT /api/admin/settings` | `parametres.gerer` (SUPER_ADMIN) | Lecture/écriture du réglage |

> **Point clé** : la lecture passe par la route publique dédiée `parametres/part-avance`
> — et non par `/settings` (réservée au SUPER_ADMIN). C'était la cause du bug initial
> (403 → repli silencieux à 70 % pour tous les autres profils).

## Comportement

- **Calcul** : `avance = round(montant_total × partAvance / 100)` ;
  `reliquat = montant_total − avance`. Source unique :
  `CalculIndemniteService::repartir()` (lit `App\Support\Parametres::partAvance()`).
- **Application** : le split est **calculé et figé à la retranscription** de l'OM. Un
  changement de réglage ne recalcule pas les OM existants — il s'applique aux OM créés
  ensuite.
- **Frontend** : la valeur est chargée à la connexion et au rechargement (garantie avant
  l'affichage des écrans), purgée au logout, et rafraîchie immédiatement après un
  changement de réglage (sans rechargement de page).
- **Libellés** : tous dynamiques (`{{ avancePct }} %` / `{{ 100 − avancePct }} %`), y
  compris le **PDF officiel de l'ordre de mission**.

## Fichiers principaux

### Backend

- `app/Support/Parametres.php` — lecture/cache de `partAvance` (défaut 70).
- `app/Services/CalculIndemniteService.php` — répartition paramétrable.
- `routes/api.php` — route publique `parametres/part-avance` + validation
  `PUT /settings` (entier 0–100).
- `resources/views/pdf/ordre-mission.blade.php` + `app/Services/PdfGenerator.php` —
  libellés PDF dynamiques (`avance_pct` / `reliquat_pct`).
- `app/Http/Controllers/Api/MissionController.php` — extraction de l'état reliquat par
  tranche (`firstWhere`).
- Seeders / factory — `Parametres::partAvance()` au lieu de `0.70`.

### Frontend

- `composables/usePartAvance.ts` — chargement (route publique), cache, ref réactif,
  `reinitialiser()`.
- `stores/auth.ts`, `router/index.ts` — amorçage login/boot + reset logout.
- `views/admin/SettingsView.vue` — édition du réglage + rafraîchissement (et chargement
  correct d'une part à 0 %).

## Tests

- `tests/Feature/PartAvanceTest.php` — route publique lisible par tout profil ; renvoie
  la part configurée.
- `tests/Unit/CalculIndemniteServiceTest.php` — split correct avec une part ≠ 70 %.
- `tests/Feature/MissionsIndexTest.php` — distinction état des avances / état des
  reliquats.

## Historique (PR)

| PR | Objet |
|----|-------|
| #44 | `usePartAvance` : lecture via la route publique + ref réactif |
| #45 | Amorçage du chargement au login/boot + reset au logout |
| #46 | Réglage reflété sans rechargement |
| #47 | Doc/seeds backend alignés sur le paramétrable |
| #48 | `MissionController::missions()` fiabilisé (état reliquat par tranche) |
| #49 | Tests + build + assets |
| #50 | Libellé avance/reliquat du PDF de l'OM dynamique |
| #51 | Chargement correct d'une part enregistrée à 0 % |
