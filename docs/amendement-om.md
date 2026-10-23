# Amendement d'un ordre de mission après paiement de l'avance

> **Statut : fonctionnalité complète — Phases 1 à 6 livrées, document de retenue RH inclus,
> durcie par la revue d'octobre 2026 (version 1.3).**
> Ce document cadre le
> besoin, la règle de gestion, l'implémentation et le planning. Décisions métier
> tranchées (§7) : trop-perçu = **retenue sur salaire** (constat + trace, recouvrement
> manuel) ; périmètre v1 = amendement **avant** validation de l'état des reliquats ;
> amendement par le **gestionnaire** (motif + audit) ; portée = **jours facturés** de l'OM.
>
> **Livré (Phase 1)** : colonne `avance_engagee` figée à la validation des avances
> (`EtatPaiementService::propager`) et règle de répartition conditionnelle
> (`CalculIndemniteService::repartir($total, $avanceEngagee)`), avec tests.
>
> **Livré (Phase 2)** : endpoint **`POST /api/ordres/:id/amender`** (permission
> `om.retranscrire`, motif obligatoire) + `MissionService::amender` (recalcul au
> barème, répartition contre l'avance engagée, réajustement des versements non
> confirmés), table **`om_amendements`** (journal), gardes de périmètre v1 (refus si
> `REJETE`/`ANNULE`, si un état de tranche est en cours de signature, ou si l'état des
> reliquats est établi/validé — garde assouplie en Phase 4 : un état des reliquats en
> `BROUILLON` / `REJETE` est resynchronisé, seul l'état validé bloque), audit `AMEND_OM`. Colonnes `reliquat` et
> `versements.montant` passées en **entiers signés** ; `scopeEligiblesLot` exclut les
> montants ≤ 0 (trop-perçu).
>
> **Livré (Phase 3)** : statuts de régularisation `A_RECOUVRER` / `RECOUVRE` (enum
> `versements.statut` étendu) ; un reliquat négatif après amendement bascule
> automatiquement en `A_RECOUVRER` et sort du périmètre payable
> (`scopeEligiblesLot` exclut aussi `A_RECOUVRER`/`RECOUVRE`) ; endpoints
> **`GET /api/paiements/regularisations`** (liste des trop-perçus, montant à
> recouvrer en valeur absolue) et **`POST /api/paiements/regularisations/:versement/recouvrer`**
> (constat manuel de la retenue RH, `paiements.confirmer`), audit
> `RECOUVRER_VERSEMENT`.
>
> **Livré (Phase 5, frontend)** : fiche détail OM enrichie — bouton **« Amender »**
> (modale jours + motif, gated `om.retranscrire`), **journal des amendements**,
> badge **« Trop-perçu »** quand le reliquat est négatif ; exposition
> `avance_engagee` + `amendements` sur `GET /api/ordres/:id` (`OrdreMissionResource`) ;
> nouvel écran **Régularisations** (`/paiements/regularisations`, menu Paiements) qui
> liste les trop-perçus et permet de les marquer recouvrés ; badges de statut
> `A_RECOUVRER`/`RECOUVRE` ; tests Vitest (écran + badge) et e2e Playwright
> (parcours régularisation).
>
> **Livré (Phase 4)** : intégrité de l'amendement avec l'état des reliquats.
> Colonnes `etats_paiement.montant_total` / `montant_reliquat` passées en **entiers
> signés**. *Révisé en octobre 2026* : l'état des reliquats porte désormais le
> montant **payable** (Σ des reliquats positifs) — un trop-perçu n'abaisse plus le
> total signé ; le PDF le mentionne à part, hors total (cf. `docs/revue-projet-2026-10.md`).
> L'observer resynchronise automatiquement l'état BROUILLON/REJETE des reliquats
> sur les montants amendés ; l'état des AVANCES validé reste **scellé** (HMAC) et
> intact (avance figée). Garde d'amendement affinée : un état des reliquats en
> cours de signature (`CONTROLE`/`VISA_DAF`) guide vers le **rejet** (« rejetez-le
> avant d'amender, puis rétablissez-le »), un état **validé** relève d'un avenant
> formel (hors périmètre). Tests d'intégrité (`AmendementIntegriteEtatTest`).
>
> **Livré (Phase 6, docs & démo)** : `docs/circuits-validation.md` §6 — section
> « avenant » avec **diagramme Mermaid** du cas d'amendement, table des états où
> l'amendement est permis, garanties d'intégrité et références de tests ;
> `docs/SCENARIO-DEMO.md` §7 — variante avenant (rallonge → reliquat 171 000 ;
> réduction forte → trop-perçu −54 000 → régularisation) avec le parcours UI.
>
> **Livré (document de retenue)** : PDF officiel à remettre à la RH, généré par
> `PdfGenerator::genererDocumentRetenue` (gabarit `pdf.document-retenue`, en-tête
> ANaTT) — bénéficiaire, mission, décomposition du trop-perçu (avance engagée −
> montant dû), **montant à recouvrer en toutes lettres** (`MontantEnLettres::mention`,
> ex. « Cinquante-quatre mille (54 000) FRANCS CFA »), motif de l'amendement, blocs
> de signature DAF/RH ; endpoint
> **`GET /api/paiements/regularisations/:versement/document`** (`paiements.consulter`),
> bouton **« Document »** sur l'écran Régularisations. Le document **n'ordonne aucun
> décaissement** : il formalise la retenue, appliquée hors application par la RH.
>
> **Révisé (revue d'octobre 2026, version 1.3)** — cf. `docs/revue-projet-2026-10.md` :
> - **circuit par OM** (mission sans état) : une avance déjà payée (`CONFIRME`) est
>   **engagée à l'amendement**. Auparavant l'amendement recalculait toute la répartition et
>   ignorait l'avance décaissée (sur-paiement sans trop-perçu détecté) ;
> - **garde « fichier en attente de dépôt »** : amendement refusé tant qu'un versement de l'OM
>   est `GENERE` (confirmer le lot, ou le passer en `ERREUR`) ;
> - **verrou** : l'OM puis les états de la mission sont lus avec `lockForUpdate`, pour qu'un
>   contrôle concurrent ne signe pas l'état entre la garde et la resynchronisation ;
> - **état des reliquats au montant payable** (Σ des reliquats positifs) : un trop-perçu
>   n'abaisse plus le total signé (cf. Phase 4 ci-dessus).
>
> **Révisé (octobre 2026, mises à jour de la documentation)** :
> - **PDF de l'OM amendé** (§4.6) : il était servi depuis le cache d'avant l'amendement
>   (l'écran télécharge `/pdf/download`, et la clé de cache ne dépendait que des statuts),
>   et présentait un reliquat ajusté, voire négatif, comme « Reliquat (30 %) ». La clé
>   compte désormais les amendements (`-AMD{n}`), les libellés suivent la répartition
>   réelle et le PDF mentionne le dernier amendement ;
> - **échec d'une génération en file** : il remet aussi les versements `GENERE` en
>   `EN_ATTENTE`, ce qui lève la garde « fichier en attente de dépôt » (`backend/docs/queues.md`) ;
> - **signatures** : le PDF de l'OM reprend celles de l'état des avances
>   (`docs/circuits-validation.md` §7) ; elles restent valables après un amendement,
>   puisqu'elles portent sur l'avance, qui est figée.

---

## 1. Le besoin

Une mission a été retranscrite, **l'état des avances a été validé et les avances
payées**. Ensuite, un ou plusieurs OM sont **amendés** : SEMO modifie le **nombre de
jours** de la mission (rallonge ou réduction). L'ajustement — en plus ou en moins —
doit se répercuter sur le **reliquat**, puisque l'avance est déjà décaissée.

*Constat de départ (avant la fonctionnalité)* — ce cas n'était pas géré :

- les montants (`montant_total`, `avance`, `reliquat`) étaient **figés à la
  retranscription** (`CalculIndemniteService`) ;
- les OM **ne sont pas modifiables** individuellement (retranscription seule, pas de
  `PUT /ordres/:id`) — c'est toujours vrai : seul l'**amendement** (§4.3) les ajuste ;
- la répartition était fixe : `avance = round(total × partAvance/100)` puis
  `reliquat = total − avance`.

---

## 2. La règle comptable

Une fois l'avance engagée (payée), elle **ne bouge plus**. Le reliquat devient la
**variable d'ajustement** :

```
reliquat = nouveau_montant_total − avance_déjà_engagée
```

### Trois issues

| Cas | Total | Reliquat ajusté | Conséquence |
|---|---|---|---|
| **Jours en hausse** (rallonge) | ↑ | **au-delà** de la part initiale du reliquat (30 % avec la part d'avance par défaut) | complément à verser au bénéficiaire |
| **Jours en baisse** (réduction modérée) | ↓ | **en deçà** de cette part, ≥ 0 | reliquat réduit |
| **Jours en forte baisse** | ↓↓ | **négatif** | **trop-perçu** : l'avance dépasse le nouveau total → à **récupérer**, non payable en Mobile Money |

Le troisième cas est le point dur : un fichier MTN (`.txt`) ou Moov (`.xlsx`) ne peut
porter qu'un montant **strictement positif**.

---

## 3. Le moment de l'amendement change le traitement

| Amendement survenant… | Traitement |
|---|---|
| **Avant** le récapitulatif des avances (OM `IMPORTE`) | Recalcul complet avec la part d'avance (`Parametres::partAvance()`, 70 % par défaut), trivial. **Aucun cas spécial** — équivaut à corriger la retranscription. |
| État des avances en `BROUILLON` / `REJETE` | Recalcul complet (rien n'est figé) ; le récapitulatif des avances se **resynchronise**. |
| État des avances en `CONTROLE` / `VISA_DAF` | Refusé : rejeter l'état des avances, amender, puis le rétablir. |
| **Après** validation des avances, **avant** l'état des reliquats | ⭐ **Cas central** : avance figée, `reliquat = new_total − avance`. |
| Mission **sans état**, avance déjà payée (`CONFIRME`) | Même règle : l'amendement **engage** l'avance payée, puis seul le reliquat bouge. |
| État des reliquats en `BROUILLON` / `REJETE` | Autorisé : le récapitulatif se **resynchronise** sur les montants amendés. |
| État des reliquats en `CONTROLE` / `VISA_DAF` | Refusé avec **rejet guidé** : rejeter l'état, amender, puis le rétablir. |
| État des reliquats `VALIDE` (scellé), ou trop-perçu déjà `RECOUVRE` | **Avenant formel** — hors périmètre v1, refusé par l'application. |

C'est le franchissement « avance figée » qui introduit la nouvelle règle.

---

## 4. Implémentation

### 4.1 Donnée — figer l'avance engagée

- Colonne `ordre_missions.avance_engagee` (int, **nullable**). Elle est posée
  **au moment où l'état des avances passe `VALIDE`** (`EtatPaiementService::propager`)
  ou, sur une mission **sans état**, **par l'amendement** lui-même si l'avance est déjà
  `CONFIRME` (`MissionService::amender`).
  Tant qu'elle est `null`, la répartition par la part d'avance est **strictement inchangée**.
- Table `om_amendements` (traçabilité, migration `2026_08_18_130000`) :
  `ordre_mission_id, nb_jours_avant, nb_jours_apres, lignes_avant (json),
  montant_total_avant, montant_total_apres, delta_montant (signé), motif, amende_par,
  amende_par_nom (instantané), amende_le`.
- Migrations **additives** (aucun `drop`, non destructif).

### 4.2 Calcul — `CalculIndemniteService`

```text
si avance_engagee est null :          # avances pas encore validées
    avance   = round(total × partAvance / 100)
    reliquat = total − avance
sinon :                                # avance figée (déjà engagée / payée)
    avance   = avance_engagee          # inchangée
    reliquat = total − avance_engagee  # peut être négatif
```

Le paramètre `partAvance` ne s'applique qu'au **premier** calcul ; une fois l'avance
engagée, il est neutralisé (l'avance ne se recalcule plus).

### 4.3 Endpoint d'amendement (le maillon manquant)

```
POST /api/ordres/:id/amender          (permission : om.retranscrire)
Body: { nb_jours? , lignes? , motif }
```

- Recalcule `montant_total` au barème, applique la règle §4.2, met à jour le
  **versement RELIQUAT** (si non `CONFIRME`), journalise `AMEND_OM`, écrit dans
  `om_amendements`. Le tout dans une transaction qui verrouille l'OM puis les états de la
  mission (`lockForUpdate`).
- **Garde-fous** :
  - refus si l'OM est `REJETE` ou `ANNULE` ;
  - refus si un état de tranche est **en cours de signature** (`CONTROLE` / `VISA_DAF`,
    document scellé) → il faut d'abord le **rejeter** ;
  - refus si un trop-perçu de l'OM est déjà **`RECOUVRE`** (retenue RH soldée) : ré-amender
    rouvrirait un versement clos en ignorant le montant déjà retenu → avenant formel, hors
    périmètre v1. Un trop-perçu encore `A_RECOUVRER` (rien retenu) reste amendable ;
  - refus tant qu'un versement de l'OM est **`GENERE`** (fichier en attente de dépôt) :
    confirmer le lot, ou le passer en `ERREUR` — ses versements redeviennent `EN_ATTENTE`.
    L'échec définitif d'une génération en file a le même effet (`GenererLotPaiementJob::failed`) ;
  - circuit par OM (mission sans état) : une avance déjà **`CONFIRME`** est engagée à
    l'amendement (`avance_engagee`), seul le reliquat absorbe l'écart ;
  - autorisé si l'état est en `BROUILLON` (le récapitulatif se rafraîchit) ou si les
    avances sont `VALIDE` (l'avance se fige, seul le reliquat bouge).

### 4.4 Reliquat négatif — trop-perçu

- `ordre_missions.reliquat` et `versements.montant` sont des entiers **signés**
  (migration de la Phase 2) : ils portent le trop-perçu.
- Le modèle **autorise** un `reliquat` négatif (vérité comptable), mais :
  - `Versement::scopeEligiblesLot` filtre `montant > 0` → **aucun montant ≤ 0 n'entre
    dans un fichier**, et exclut les statuts `A_RECOUVRER` / `RECOUVRE` ;
  - statut de versement `A_RECOUVRER` + écran **« Régularisations / trop-perçus »** qui
    les liste. La récupération (retenue sur salaire, ordre de reversement) est un
    **processus manuel hors Mobile Money**.
  - **Notification** : dès qu'un amendement produit un trop-perçu, l'agent financier est
    averti dans l'application (cloche) et par e-mail (`NotificationService::tropPercuCree`,
    type `trop_percu_cree`) —
    la régularisation ne dépend pas d'une consultation manuelle de l'écran.

### 4.5 État des reliquats

- Le récapitulatif du reliquat étant **recalculé depuis les OM**, il reflète
  automatiquement les amendements tant qu'il est en `BROUILLON` (ou `REJETE`) :
  `OrdreMissionObserver` → `EtatPaiementService::synchroniser`.
- Son total est le montant **payable** : Σ des reliquats **positifs**
  (`EtatPaiementService::montantsTranche`). Un trop-perçu n'abaisse pas le total signé ;
  le PDF le mentionne à part (« trop-perçus hors total, à recouvrer par retenue sur
  salaire »).
- En cours de signature (`CONTROLE` / `VISA_DAF`) : amendement refusé avec un message qui
  guide vers le **rejet** de l'état, puis son rétablissement.
- `VALIDE` : le **scellé HMAC** protège le document ; l'amendement est **refusé** (avenant
  formel, hors périmètre v1 — décision §7.2).

### 4.6 PDF de l'OM amendé

L'amendement change les montants de l'OM sans changer son statut ni celui de l'état.
Le PDF de l'OM (`PdfGenerator`) en tient compte :

- **cache** : la clé `ordres/OM-{ref}-{statut}[-ETAT-{statut_etat}][-AMD{n}].pdf` compte les
  amendements ; après un amendement, `GET /api/ordres/:id/pdf/download` régénère le PDF au
  lieu de servir celui d'avant ;
- **répartition** (`libellesRepartition`) :

  | Situation de l'OM | Libellés |
  |---|---|
  | Avance non engagée, ou engagée sans amendement | `Avance (70 %)` / `Reliquat (30 %)` — pourcentage tiré des montants de l'OM, pas du paramètre courant (la part d'avance a pu changer depuis la retranscription) |
  | Avance engagée et OM amendé, reliquat ≥ 0 | `Avance engagée` / `Reliquat (ajusté par amendement)` |
  | Reliquat négatif | `Avance engagée` / `Trop-perçu (à recouvrer par retenue sur salaire)` ; l'encadré du total devient « Montant total dû » |

- **mention** : un encadré rappelle le dernier amendement (date, jours avant → après,
  motif, nombre d'amendements s'il y en a plusieurs) ;
- **taux journalier** affiché sous le total : per diem seul, **hors transport**.

Les signatures reprises de l'état des avances restent affichées : elles portent sur
l'avance, que l'amendement ne modifie pas. Tests : `tests/Feature/OmPdfAmendementTest.php`.

---

## 5. Conséquences sur l'existant

> Tableau d'analyse d'impact établi au cadrage. Ce qui a été réalisé est décrit au §4 et
> dans l'en-tête ; les écarts sont signalés dans la colonne « Impact ».

| # | Composant | Impact |
|---|---|---|
| 1 | `CalculIndemniteService` | Répartition par la part d'avance **conditionnelle** (`repartir($total, $avanceEngagee)`) ; `partAvance` neutralisé après engagement. Tests de calcul étendus. |
| 2 | `MissionService` | Nouvelle méthode `amender()` ; `exigerAbsenceDEtat` **ne peut plus** bloquer (l'amendement doit agir sur une mission avec état) → garde plus fine par statut d'état. |
| 3 | `EtatPaiementService::propager` | Fige `avance_engagee` à la validation des avances. |
| 4 | `Versement` | `montant` peut être **négatif** ; `scopeEligiblesLot` exclut `≤ 0` ; éventuel statut `A_RECOUVRER`. |
| 5 | Générateurs MTN / Moov | Montants > 0 garantis par `scopeEligiblesLot` (seule source des lignes des fichiers) ; pas de contrôle supplémentaire dans les générateurs. |
| 6 | Migrations | `avance_engagee`, table `om_amendements`, éventuel statut — **additif**. |
| 7 | Frontend | Détail OM : bouton **Amender** (jours/lignes + motif), **historique**, badges « reliquat ajusté » / « trop-perçu » ; écran **Régularisations**. |
| 8 | PDF | Réalisé sous trois formes : trop-perçus mentionnés hors total sur l'état des reliquats ; document de retenue (avant / après amendement, motif) ; PDF de l'OM régénéré après amendement, avec libellés de répartition réels et mention de l'amendement (§4.6). |
| 9 | Audit | Action `AMEND_OM`. |
| 10 | Rapports | Réalisé : rapport des trop-perçus (`GET /api/rapports/trop-percus`, à recouvrer vs recouvrés). La distinction per diem initial / ajusté n'est pas faite : les rapports portent les montants courants. |
| 11 | Idempotence | Garde-fou renforcé : une avance `CONFIRME` n'est **jamais** re-touchée ; aucun amendement tant qu'un versement est `GENERE`. |
| 12 | Docs | `circuits-validation.md`, guide utilisateur, `SCENARIO-DEMO.md`. |

---

## 6. Planning d'intégration (≈ 5–6 PR)

| Phase | Contenu | Livrable |
|---|---|---|
| **0 · Cadrage** | Trancher les décisions métier (§7). Aucun code. | note de décision |
| **1 · Socle données + calcul** ✅ | Migration `avance_engagee` ; figer l'avance dans `propager` ; règle conditionnelle `CalculIndemniteService::repartir($total, $avanceEngagee)` ; **tests** unitaires (rallonge / réduction / trop-perçu) + feature (gel). *Rien d'exposé.* | **livré** |
| **2 · Endpoint d'amendement** ✅ | Migration `om_amendements` + colonnes signées (`reliquat`, `versements.montant`) ; `POST /ordres/:id/amender` + FormRequest + `MissionService::amender` + gardes par statut + audit ; MAJ des versements ; `scopeEligiblesLot > 0` ; tests feature. | **livré** |
| **3 · Trop-perçu / régularisations** ✅ | Enum `versements.statut` étendu (`A_RECOUVRER`/`RECOUVRE`) ; reliquat négatif basculé en `A_RECOUVRER` (`reventilerVersements`) ; `scopeEligiblesLot` exclut `A_RECOUVRER`/`RECOUVRE` ; endpoints `GET /paiements/regularisations` + `POST …/:versement/recouvrer` ; audit `RECOUVRER_VERSEMENT` ; tests feature. *(Document de retenue reporté en Phase 5.)* | **livré** |
| **4 · Intégration état reliquats** ✅ | Colonnes `etats_paiement.montant_total`/`montant_reliquat` signées ; l'observer recale l'état BROUILLON/REJETE des reliquats sur les montants amendés ; l'état des avances validé reste scellé (HMAC) et intact ; garde affinée (reliquats `CONTROLE`/`VISA_DAF` → rejet guidé ; `VALIDE` → avenant formel) ; tests d'intégrité. | **livré** |
| **5 · Frontend** ✅ | Fiche OM (bouton Amender + modale, journal des amendements, badge trop-perçu) ; `avance_engagee` + `amendements` exposés sur `GET /ordres/:id` ; écran Régularisations (`/paiements/regularisations` + menu) avec « marquer recouvré » ; badges `A_RECOUVRER`/`RECOUVRE` ; Vitest + e2e Playwright. | **livré** |
| **6 · Docs & démo** ✅ | `circuits-validation.md` §6 (section avenant + diagramme Mermaid + table des permissions + garanties d'intégrité + refs tests) ; `SCENARIO-DEMO.md` §7 (variante avenant : rallonge, trop-perçu, régularisation). | **livré** |

Ordre pensé pour que **chaque PR reste verte et non destructive** : le socle (1) ne
change aucun comportement tant que l'endpoint (2) n'existe pas ; le trop-perçu (3)
durcit le périmètre payable ; le front (5) arrive en dernier.

---

## 7. Décisions métier (Phase 0 — tranchées)

Questions de gestion posées au cadrage, et la réponse retenue :

1. **Trop-perçu** — comment le récupère-t-on ? → **Retenue sur salaire**, hors
   application. L'application constate (`A_RECOUVRER`), produit le **document de retenue**
   pour la RH, puis enregistre le recouvrement (`RECOUVRE`).
2. **Amendement après l'état des reliquats validé / payé** → **hors périmètre v1** :
   l'amendement est permis jusqu'à la validation de l'état des reliquats ; au-delà,
   avenant formel (refusé par l'application).
3. **Qui amende ?** → le **gestionnaire** seul (`om.retranscrire`), sans circuit de
   validation propre, mais avec **motif obligatoire** (≥ 10 caractères), journal
   `om_amendements` et audit `AMEND_OM`. Le contrôle reste celui de l'état des reliquats.
4. **Portée** → les **jours facturés** de l'OM (`nb_jours` ou `lignes`) ; les dates de la
   mission ne changent pas.

---

## 8. Références

| Sujet | Emplacement |
|---|---|
| Répartition avance/reliquat | `app/Services/CalculIndemniteService.php` |
| Circuit & propagation VALIDE | `app/Services/EtatPaiementService.php`, `docs/circuits-validation.md` |
| Périmètre payable | `Versement::scopeEligiblesLot()` |
| Part de l'avance paramétrable | `docs/parametrage-part-avance.md` |
| Générateurs de fichiers | `app/Services/Paiement/{MtnFileGenerator,MoovFileGenerator}.php` |
| Amendement et gardes | `MissionService::amender()`, `MissionService::exigerAmendementPossible()` |
| Régularisations et document de retenue | `LotPaiementService::regularisations()`, `PdfGenerator::genererDocumentRetenue()` |
| Notification du trop-perçu | `NotificationService::tropPercuCree()` |
| PDF de l'OM amendé | `PdfGenerator::donnees()`, `PdfGenerator::libellesRepartition()`, gabarit `pdf.ordre-mission` |
| Endpoints et permissions | `backend/docs/openapi.yaml` (`POST /ordres/{ordre}/amender`, tag *Paiements* pour les régularisations) |
| Tests | `AmendementOmTest`, `AmendementIntegriteEtatTest`, `AvanceEngageeTest`, `RegularisationTest`, `RapportTropPercusTest`, `OmPdfAmendementTest` |
| Revue d'octobre 2026 | `docs/revue-projet-2026-10.md` (§3 n° 3, §4 *Intégrité des paiements*) |
