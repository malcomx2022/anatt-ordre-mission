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
- **Stockage** : clé `partAvance` dans `storage/app/settings.json` (fichier, **pas** la
  base de données).
- **Validation** : entier de 0 à 100, contrôlé par `SettingsController::update` (422 sinon :
  hors bornes ou non entier).

## API

| Route | Accès | Rôle |
|-------|-------|------|
| `GET /api/admin/parametres/part-avance` | **tout profil authentifié** | Lecture (pilote l'affichage) |
| `GET/PUT /api/admin/settings` | `parametres.gerer` (SUPER_ADMIN) | Lecture/écriture du réglage (liste blanche de clés) |

> **Point clé** : la lecture passe par la route publique dédiée `parametres/part-avance`
> — et non par `/settings` (réservée au SUPER_ADMIN). C'était la cause du bug initial
> (403 → repli silencieux à 70 % pour tous les autres profils).

## Comportement

- **Calcul** : `avance = round(montant_total × partAvance / 100)` ;
  `reliquat = montant_total − avance`. Source unique :
  `CalculIndemniteService::repartir()` (lit `App\Support\Parametres::partAvance()`).
  `montant_total` comprend le per diem **et** le transport ; une mission prise en charge
  par un tiers (exonération) donne 0 partout.
- **Cache serveur** : `Parametres::partAvance()` est mis en cache **60 s**
  (`parametres.part_avance`), invalidé immédiatement par une écriture via `/settings`. Une
  modification **manuelle** de `settings.json` prend donc effet au plus tard une minute après.
- **Application** : le split est **calculé et figé à la retranscription** de l'OM. Un
  changement de réglage ne recalcule pas les OM existants — il s'applique aux OM créés
  ensuite.
- **Amendement (avenant)** : tant qu'aucune avance n'est engagée, l'amendement recalcule la
  répartition avec la part **en vigueur au moment de l'amendement**. Une fois l'avance
  engagée (`avance_engagee`, posée à la validation de l'état des avances, ou par
  l'amendement si l'avance est déjà payée sur une mission sans état), la part est
  **neutralisée** : l'avance ne bouge plus et seul le reliquat absorbe l'écart
  (cf. `docs/amendement-om.md`).
- **Valeurs extrêmes** : à 100 %, le reliquat vaut 0 et n'entre dans aucun fichier
  (`Versement::scopeEligiblesLot` exclut les montants ≤ 0) ; à 0 %, tout passe en reliquat.
- **Frontend** : la valeur est chargée à la connexion et au rechargement (garantie avant
  l'affichage des écrans), purgée au logout, et rafraîchie immédiatement après un
  changement de réglage (sans rechargement de page). *Durci par la revue d'octobre 2026* :
  - un échec de lecture (401, 423 « mot de passe à changer », réseau) affiche 70 % **sans
    le mettre en cache** : la valeur est relue au prochain appel au lieu de rester figée
    toute la session ;
  - pendant le changement de mot de passe imposé, la part n'est pas chargée (l'API répond
    423) ; elle est relue dès la sortie de ce verrou ;
  - les vues lisent la valeur **réactive** (`usePartAvance().partAvance`), pas une copie
    figée au montage.
- **Libellés** : tous dynamiques (`{{ avancePct }} %` / `{{ 100 − avancePct }} %`), y
  compris le **PDF officiel de l'ordre de mission**.

## Fichiers principaux

### Backend

- `app/Support/Parametres.php` — lecture/cache de `partAvance` (défaut 70).
- `app/Services/CalculIndemniteService.php` — répartition paramétrable.
- `routes/api.php` — route publique `parametres/part-avance`.
- `app/Http/Controllers/Api/SettingsController.php` — validation de `PUT /settings`
  (entier 0–100), invalidation du cache, lecture publique (`partAvance()`).
- `resources/views/pdf/ordre-mission.blade.php` + `app/Services/PdfGenerator.php` —
  libellés PDF dynamiques (`avance_pct` / `reliquat_pct`).
- `app/Http/Controllers/Api/MissionController.php` — extraction de l'état reliquat par
  tranche (`firstWhere`).
- Seeders / factory — `Parametres::partAvance()` au lieu de `0.70`.

### Frontend

- `composables/usePartAvance.ts` — chargement (route publique), cache (jamais sur échec),
  ref réactif, `reinitialiser()`.
- `stores/auth.ts`, `router/index.ts` — amorçage login/boot, relecture à la sortie du
  changement de mot de passe imposé, reset logout.
- `views/admin/SettingsView.vue` — édition du réglage + rafraîchissement (et chargement
  correct d'une part à 0 %).
- Lecteurs de la valeur : `stores/ordres-mission.ts` (aperçu du per diem à la
  retranscription), `views/missions/MissionDetailView.vue`, `views/missions/MissionPdfView.vue`,
  `views/etats/EtatDetailView.vue`, `components/paiements/PaiementGeneration.vue`.

## Tests

- `tests/Feature/PartAvanceTest.php` — route publique lisible par tout profil ; renvoie
  la part configurée.
- `tests/Feature/SettingsControllerTest.php` — bornes 0–100, refus d'une valeur non
  entière, invalidation du cache après écriture.
- `tests/Unit/CalculIndemniteServiceTest.php` — split correct avec une part ≠ 70 %.
- `frontend/src/__tests__/part-avance.spec.ts` — pas de mise en cache du défaut sur
  erreur ; pas de chargement pendant le changement de mot de passe imposé, relecture après.
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
| #125 | Revue d'octobre 2026 : pas de cache sur échec, relecture après le changement de mot de passe imposé, lectures réactives |

## Exploitation

- `settings.json` vit dans `storage/app/` : il **n'est pas** dans les sauvegardes de la
  base (`db:backup`) et une restauration (`db:restore`) ne le modifie pas. Le conserver
  avec le reste de `storage/` lors d'une mise à jour (ne jamais écraser `storage/`, cf.
  `docs/runbook-mise-a-jour-cron.md`) et le sauvegarder à part si le réglage diffère de 70.
- Changer la part en cours d'exercice n'a d'effet que sur les OM retranscrits ensuite :
  les états de paiement déjà établis gardent les montants de leurs OM.
