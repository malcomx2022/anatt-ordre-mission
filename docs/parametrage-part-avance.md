# Pourcentage avance/reliquat paramétrable

## Résumé

La répartition **avance / reliquat** d'un per diem, jusque-là figée à **70 % / 30 %**,
est désormais **entièrement paramétrable** par un SUPER_ADMIN. La valeur
(`partAvance`, entier 0–100) est stockée dans `settings.json` ; le reliquat s'en déduit
(`100 − partAvance`) et absorbe l'arrondi.

> Défaut : **70 %**. Aucun changement de comportement pour une installation existante.
>
> *Relu le 24/10/2026 contre le code* : règle du solde déplacée sur le modèle
> (`OrdreMission::solderSiRegle`), conséquences d'une part à 0 % ou 100 % sur l'état des avances
> et sur l'amendement (défaut n° 28 : un OM soldé par une avance à 100 % redevient amendable).
>
> *Relu le 27/10/2026 (version 1.4)* : la fiche et l'aperçu de l'OM ne lisent plus le paramètre courant
> pour leurs libellés, mais les montants de l'OM, comme le PDF (`utils/repartition.ts`) ; chemin de
> l'écran corrigé (menu *Système*, onglet *Général*). Le même jour, l'écran de l'état et le sélecteur de
> tranche de la génération passent à la part **de la mission** (`part_avance`), et la chaîne des montants
> est vérifiée de bout en bout par un test à 55 %.
>
> *Complété le 28/10/2026* : les autres réglages de l'écran *Paramètres* sont désormais appliqués par le
> serveur — « Enregistrer tout » les enregistre **avec** la part d'avance (à relire avant) ; un refus du
> serveur affiche son motif (« Le pourcentage d'avance doit être un entier entre 0 et 100. ») au lieu du
> message générique « Donnees invalides. ». Puis (#188) : le motif s'affiche **une seule fois** —
> l'avertissement global d'un refus, qui doublait le message de l'écran par « Donnees invalides. »,
> donne lui aussi le motif — et les règles génériques du serveur sont rédigées en français.

## Où se règle-t-il

- **Écran** : menu *Système* → **Paramètres** (`/admin/parametres`), onglet **Général** →
  *Part de l'avance (%)* (le champ *Part du reliquat* est calculé automatiquement).
- **Permission** : `parametres.gerer` (SUPER_ADMIN) pour l'écriture.
- **Stockage** : clé `partAvance` dans `storage/app/settings.json` (fichier, **pas** la
  base de données).
- **Validation** : entier de 0 à 100, contrôlé par `SettingsController::update` (422 sinon :
  hors bornes, non entière ou champ vidé). L'écran affiche le motif du refus — « Le pourcentage
  d'avance doit être un entier entre 0 et 100. » (champ `errors` de la réponse, repris par
  `getErrorMessage`) — **une seule fois**, et n'enregistre **rien** : une valeur refusée bloque aussi
  les autres réglages envoyés avec elle. La saisie reste à l'écran : la corriger, puis « Enregistrer
  tout » à nouveau (ou recharger la page pour retrouver la part enregistrée).
- **Enregistrer tout** : le bouton envoie **tous** les onglets de l'écran à la fois (part d'avance,
  exercice, opérateurs, notifications, sécurité, maintenance). Depuis le 28/10/2026, chacun est
  appliqué par le serveur : relire les autres onglets avant de changer la part (« Forcer HTTPS »
  seulement si le site répond déjà en `https://`).

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
- **Valeurs extrêmes** : à 100 %, le reliquat vaut 0 ; à 0 %, c'est l'avance qui vaut 0 et
  tout passe en reliquat. La tranche nulle garde son versement, créé à la retranscription :
  un amendement ultérieur pourra le réécrire. Ce versement n'entre dans aucun fichier
  (`Versement::scopeEligiblesLot` exclut les montants ≤ 0) et **ne retient pas l'OM** : la
  règle du solde (`OrdreMission::solderSiRegle`, appelée à la confirmation d'un lot) ignore les
  versements nuls et passe l'OM à `SOLDE`. *Corrigé le 23/10/2026* : avant, un OM entièrement
  payé avec une part à 100 % (ou à 0 %) restait `VALIDE` indéfiniment (défaut n° 19).
  - **À 0 %** : l'état des avances est signé à 0 (aucun fichier d'avance) ; sa validation fige
    une avance engagée de 0 et ouvre l'état des reliquats, qui porte tout le montant.
  - **À 100 %** : l'OM est soldé dès la confirmation de son avance. Une rallonge reste possible
    ensuite : l'amendement recrée un reliquat positif et **rouvre l'OM** (`SOLDE` → `VALIDE`),
    payable après validation de l'état des reliquats ; un amendement qui ne laisse rien à régler
    le laisse soldé. *Corrigé le 24/10/2026* (défaut n° 28) : l'amendement d'un OM soldé était
    refusé, ce qui rendait l'avenant impossible après paiement avec une part à 100 %.
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
- **Libellés** — deux familles :
  - **ce qui reste à créer** suit le paramètre courant (`usePartAvance().partAvance`) : aperçu
    du per diem à la retranscription (`stores/ordres-mission.ts`, même arrondi que
    `CalculIndemniteService::repartir`), mention « avance et reliquat sont recalculés
    (70 / 30 %) » de la modale d'amendement tant que l'avance n'est pas engagée ;
  - **ce qui décrit un OM existant** tire son pourcentage des **montants de l'OM**
    (`round(avance × 100 / total)`) : un OM retranscrit à 70 % garde « Avance (70 %) » même si la
    part a changé depuis. C'est le cas du **PDF officiel** (`PdfGenerator::libellesRepartition`)
    et, depuis la 1.4, de la **fiche** et de l'**aperçu** de l'OM (`utils/repartition.ts`, son
    miroir) — auparavant, ces deux écrans affichaient la part courante et contredisaient le PDF.
    Le paramètre n'y sert plus que de repli pour un OM à montant nul (exonéré). Après un
    amendement, les trois affichent « Avance engagée » et « Reliquat (ajusté par amendement) » ou
    « Trop-perçu » (`docs/amendement-om.md` §4.6).
  - **à l'échelle d'une mission** (corrigé le 27/10/2026) : l'écran d'un état de paiement
    (`EtatDetailView`, onglets « Avances (60 %) » / « Reliquats (40 %) » et montant de la tranche)
    et le sélecteur de tranche de l'écran de génération (`PaiementGeneration`) affichent la part
    **de la mission**, `part_avance`, calculée par le serveur (`Mission::partAvanceReelle`) :
    Σ avances / Σ montants de ses OM, hors OM rejetés ou annulés et hors OM amendés dont l'avance
    est engagée (même règle que `libellesRepartition`). L'API la renvoie sur `GET /missions/:id/etat`,
    le récapitulatif et `GET /paiements/lots/previsualiser` ; repli sur le réglage courant pour une
    mission exonérée ou avant le choix d'une mission. Auparavant, ces deux écrans titraient avec le
    réglage courant : montants justes, pourcentage faux si la part avait changé depuis la
    retranscription. Le PDF de l'état n'imprime pas ce pourcentage.

## Fichiers principaux

### Backend

- `app/Support/Parametres.php` — lecture/cache de `partAvance` (défaut 70).
- `app/Services/CalculIndemniteService.php` — répartition paramétrable.
- `routes/api.php` — route publique `parametres/part-avance`.
- `app/Http/Controllers/Api/SettingsController.php` — validation de `PUT /settings`
  (entier 0–100), invalidation du cache, lecture publique (`partAvance()`).
- `resources/views/pdf/ordre-mission.blade.php` + `app/Services/PdfGenerator.php` —
  libellés de répartition du PDF (`libellesRepartition()` → `avance_libelle` / `reliquat_libelle`).
- `app/Models/Mission.php` — `partAvanceReelle()` : part d'avance de la mission tirée de ses OM,
  exposée en `part_avance` par `EtatPaiementController` (sauf la file `GET /etats`) et
  `LotPaiementService::previsualiser`.
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
- `views/etats/EtatDetailView.vue`, `components/paiements/PaiementGeneration.vue` — part de la
  mission (`part_avance` de l'API), repli sur le réglage courant.
- `utils/repartition.ts` — libellés d'un OM existant (part tirée de ses montants, miroir de
  `PdfGenerator::libellesRepartition`), utilisés par la fiche et l'aperçu de l'OM.
- Lecteurs de la valeur : `stores/ordres-mission.ts` (aperçu du per diem à la
  retranscription), `views/missions/MissionDetailView.vue`, `views/missions/MissionPdfView.vue`,
  `views/etats/EtatDetailView.vue`, `components/paiements/PaiementGeneration.vue`.

## Tests

- `tests/Feature/PartAvanceTest.php` — route publique lisible par tout profil ; renvoie
  la part configurée.
- `tests/Feature/SettingsControllerTest.php` — bornes 0–100, refus d'une valeur non
  entière, invalidation du cache après écriture.
- `tests/Unit/CalculIndemniteServiceTest.php` — split correct avec une part ≠ 70 % ; part
  ignorée une fois l'avance engagée (amendement).
- `tests/Feature/SoldeTest.php` — un OM dont la tranche restante est nulle (part à 100 %) passe
  à `SOLDE` à la confirmation de son avance.
- `tests/Feature/AmendementOmTest.php` — un OM soldé par une avance à 100 % est rouvert par une
  rallonge (reliquat payable) et reste soldé si l'amendement ne laisse rien à régler.
- `frontend/src/__tests__/mission-detail.spec.ts` — « Amender » proposé sur un OM soldé dont le
  reliquat n'est ni payé ni recouvré, masqué sinon.
- `tests/Feature/OmPdfAmendementTest.php` — le PDF de l'OM tire son pourcentage des montants
  de l'OM, et non du paramètre courant.
- `tests/Feature/PartAvanceReelleTest.php` — part de la mission tirée de ses OM (rejetés, annulés,
  amendés à avance engagée écartés ; null si exonérée), exposée sur l'état et l'aperçu d'un lot.
- `tests/Feature/ChaineMontantsPartAvanceTest.php` — chaîne complète à 55 % : OM et versements,
  état des avances (Σ avances), aperçus MTN/Moov, état des reliquats (Σ reliquats), puis réglage
  passé à 70 % sans effet sur la mission ni sur la part affichée.
- `frontend/src/__tests__/part-avance-reelle.spec.ts` — écran de l'état et sélecteur de tranche à la
  part de la mission (60 %) malgré un réglage à 70 %, repli sur le réglage sans part renvoyée.
- `frontend/src/__tests__/repartition.spec.ts` — part réelle de l'OM et non le paramètre courant ;
  repli sur le paramètre pour un OM à montant nul ; libellés après amendement et trop-perçu.
- `frontend/src/__tests__/settings-refus.spec.ts` — une part d'avance refusée (422) affiche le motif
  du serveur, pas le message générique ; aucun succès annoncé.
- `frontend/src/__tests__/refus-motif.spec.ts` — un refus 422 donne son motif (et non « Donnees
  invalides. »), une seule fois, que l'avertissement global et l'écran le reprennent tous deux.
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
| #167 | Version 1.4 : fiche et aperçu de l'OM aux libellés du PDF (part tirée des montants de l'OM, `utils/repartition.ts`) |
| #174 | Part de la mission (`Mission::partAvanceReelle`, `part_avance`) sur l'écran de l'état et le sélecteur de tranche ; chaîne des montants vérifiée à 55 % |
| #175 | Autres réglages de *Paramètres* appliqués par le serveur, enregistrés avec la part d'avance (« Enregistrer tout ») |
| #185 | Motif d'un refus affiché à l'écran *Paramètres* (au lieu de « Donnees invalides. ») |
| #188 | Motif d'un refus affiché une seule fois, sur tous les écrans ; messages de validation du serveur en français |

## Exploitation

- `settings.json` vit dans `storage/app/` : il **n'est pas** dans les sauvegardes de la
  base (`db:backup`) et une restauration (`db:restore`) ne le modifie pas. Le conserver
  avec le reste de `storage/` lors d'une mise à jour (ne jamais écraser `storage/`, cf.
  `docs/runbook-mise-a-jour-cron.md`) et le sauvegarder à part si le réglage diffère de 70.
  Il porte aussi les autres réglages de *Paramètres* et de *Messagerie*, appliqués depuis le 28/10 :
  à relire avant une mise à jour (runbook, étape 1).
- Changer la part en cours d'exercice n'a d'effet que sur les OM retranscrits ensuite :
  les états de paiement déjà établis gardent les montants de leurs OM.
