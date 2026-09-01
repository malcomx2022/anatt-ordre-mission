# Amendement d'un ordre de mission après paiement de l'avance

> **Statut : Phase 1 (socle) livrée ; reste non implémenté.** Ce document cadre le
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
> reliquats est établi/validé), audit `AMEND_OM`. Colonnes `reliquat` et
> `versements.montant` passées en **entiers signés** ; `scopeEligiblesLot` exclut les
> montants ≤ 0 (trop-perçu). **Reste** : Phase 3 (statut `A_RECOUVRER`, écran
> Régularisations, document de retenue) puis Phase 5 (UI d'amendement).

---

## 1. Le besoin

Une mission a été retranscrite, **l'état des avances a été validé et les avances
payées**. Ensuite, un ou plusieurs OM sont **amendés** : SEMO modifie le **nombre de
jours** de la mission (rallonge ou réduction). L'ajustement — en plus ou en moins —
doit se répercuter sur le **reliquat**, puisque l'avance est déjà décaissée.

Aujourd'hui ce cas n'est pas géré :

- les montants (`montant_total`, `avance`, `reliquat`) sont **figés à la
  retranscription** (`CalculIndemniteService`) ;
- les OM **ne sont pas modifiables** individuellement (retranscription seule, pas de
  `PUT /ordres/:id`) ;
- la répartition est fixe : `avance = round(total × partAvance/100)` puis
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
| **Jours en hausse** (rallonge) | ↑ | **> 30 %** | complément à verser au bénéficiaire |
| **Jours en baisse** (réduction modérée) | ↓ | **< 30 %**, ≥ 0 | reliquat réduit |
| **Jours en forte baisse** | ↓↓ | **négatif** | **trop-perçu** : l'avance dépasse le nouveau total → à **récupérer**, non payable en Mobile Money |

Le troisième cas est le point dur : un fichier MTN (`.txt`) ou Moov (`.xlsx`) ne peut
porter qu'un montant **strictement positif**.

---

## 3. Le moment de l'amendement change le traitement

| Amendement survenant… | Traitement |
|---|---|
| **Avant** le récapitulatif des avances (OM `IMPORTE`) | Recalcul complet 70/30, trivial. **Aucun cas spécial** — équivaut à corriger la retranscription. |
| **Après** validation des avances, **avant** l'état des reliquats | ⭐ **Cas central** : avance figée, `reliquat = new_total − avance`. |
| **Après** l'état des reliquats établi / validé / payé | **Avenant** : re-signature de l'état (scellé HMAC invalidé) et/ou versement de complément / trop-perçu. Cas le plus lourd. |

C'est le franchissement « avance figée » qui introduit la nouvelle règle.

---

## 4. Implémentation proposée

### 4.1 Donnée — figer l'avance engagée

- Nouvelle colonne `ordre_missions.avance_engagee` (int, **nullable**). Elle est posée
  **au moment où l'état des avances passe `VALIDE`** (`EtatPaiementService::propager`).
  Tant qu'elle est `null`, le comportement actuel 70/30 est **strictement inchangé**.
- Nouvelle table `om_amendements` (traçabilité) :
  `ordre_mission_id, nb_jours_avant, nb_jours_apres, lignes_avant (json),
  delta_montant, motif, amende_par, amende_le`.
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
  `om_amendements`.
- **Garde-fous** :
  - refus si l'OM est `REJETE` ou `ANNULE` ;
  - refus si un état de tranche est **en cours de signature** (`CONTROLE` / `VISA_DAF`,
    document scellé) → il faut d'abord le **rejeter** ;
  - autorisé si l'état est en `BROUILLON` (le récapitulatif se rafraîchit) ou si les
    avances sont `VALIDE` (l'avance se fige, seul le reliquat bouge).

### 4.4 Reliquat négatif — trop-perçu

- ⚠️ **Migration requise (Phase 2/3)** : `ordre_missions.reliquat` et
  `versements.montant` sont actuellement `unsignedBigInteger` (ne stockent PAS de
  négatif). Il faudra les passer en entiers **signés** pour porter le trop-perçu.
  *(Hors Phase 1 : le socle livré ne produit encore aucun montant négatif.)*
- Le modèle **autorise** un `reliquat` négatif (vérité comptable), mais :
  - `Versement::scopeEligiblesLot` reçoit un `where('montant', '>', 0)` → **aucun
    montant ≤ 0 n'entre dans un fichier** (ce filtre n'existe pas aujourd'hui, à
    ajouter de toute façon comme défense en profondeur) ;
  - statut de versement `A_RECOUVRER` + écran **« Régularisations / trop-perçus »** qui
    les liste. La récupération (retenue sur salaire, ordre de reversement) est un
    **processus manuel hors Mobile Money**.

### 4.5 État des reliquats

- Le récapitulatif du reliquat étant **recalculé depuis les OM**, il reflète
  automatiquement les amendements tant qu'il est en `BROUILLON`.
- S'il est déjà `VALIDE`, le **scellé HMAC** protège le document : un amendement
  postérieur l'**invalide** → il faut le **rejeter et refaire** (ou gérer un avenant
  formel). C'est le cas « après l'état des reliquats » à cadrer (§7).

---

## 5. Conséquences sur l'existant

| # | Composant | Impact |
|---|---|---|
| 1 | `CalculIndemniteService` | Répartition 70/30 **conditionnelle** ; `partAvance` neutralisé après engagement. Tests de calcul à étendre. |
| 2 | `MissionService` | Nouvelle méthode `amender()` ; `exigerAbsenceDEtat` **ne peut plus** bloquer (l'amendement doit agir sur une mission avec état) → garde plus fine par statut d'état. |
| 3 | `EtatPaiementService::propager` | Fige `avance_engagee` à la validation des avances. |
| 4 | `Versement` | `montant` peut être **négatif** ; `scopeEligiblesLot` exclut `≤ 0` ; éventuel statut `A_RECOUVRER`. |
| 5 | Générateurs MTN / Moov | Verrou « montants > 0 uniquement » (défense en profondeur). |
| 6 | Migrations | `avance_engagee`, table `om_amendements`, éventuel statut — **additif**. |
| 7 | Frontend | Détail OM : bouton **Amender** (jours/lignes + motif), **historique**, badges « reliquat ajusté » / « trop-perçu » ; écran **Régularisations**. |
| 8 | PDF / état général | Tracer montant initial → avenant → reliquat ajusté. |
| 9 | Audit | Action `AMEND_OM`. |
| 10 | Rapports | Distinguer per-diem initial vs ajusté ; les totaux évoluent. |
| 11 | Idempotence | Garde-fou renforcé : une avance `CONFIRME` n'est **jamais** re-touchée. |
| 12 | Docs | `circuits-validation.md`, guide utilisateur, `SCENARIO-DEMO.md`. |

---

## 6. Planning d'intégration (≈ 5–6 PR)

| Phase | Contenu | Livrable |
|---|---|---|
| **0 · Cadrage** | Trancher les décisions métier (§7). Aucun code. | note de décision |
| **1 · Socle données + calcul** ✅ | Migration `avance_engagee` ; figer l'avance dans `propager` ; règle conditionnelle `CalculIndemniteService::repartir($total, $avanceEngagee)` ; **tests** unitaires (rallonge / réduction / trop-perçu) + feature (gel). *Rien d'exposé.* | **livré** |
| **2 · Endpoint d'amendement** ✅ | Migration `om_amendements` + colonnes signées (`reliquat`, `versements.montant`) ; `POST /ordres/:id/amender` + FormRequest + `MissionService::amender` + gardes par statut + audit ; MAJ des versements ; `scopeEligiblesLot > 0` ; tests feature. | **livré** |
| **3 · Trop-perçu / régularisations** | filtre `> 0` dans `scopeEligiblesLot` + verrou générateurs ; statut `A_RECOUVRER` ; écran/état de reversement ; tests. | PR 3 |
| **4 · Intégration état reliquats** | récap reflète les amendements ; re-scellé HMAC ; parcours « amendement après état validé » (rejet guidé) ; tests d'intégrité. | PR 4 |
| **5 · Frontend** | détail OM (amender + historique + badges), écran Régularisations, e2e Playwright. | PR 5 |
| **6 · Docs & démo** | doc circuits, guide, `SCENARIO-DEMO`, diagramme du cas d'avenant. | PR 6 |

Ordre pensé pour que **chaque PR reste verte et non destructive** : le socle (1) ne
change aucun comportement tant que l'endpoint (2) n'existe pas ; le trop-perçu (3)
durcit le périmètre payable ; le front (5) arrive en dernier.

---

## 7. Décisions métier à trancher (Phase 0)

Questions de gestion, pas techniques — elles déterminent le périmètre :

1. **Trop-perçu** — comment le récupère-t-on concrètement ? Retenue sur salaire, ordre
   de reversement papier, compensation sur une mission future ? (Détermine l'écran
   « Régularisations » et le statut `A_RECOUVRER`.)
2. **Amendement après l'état des reliquats déjà validé / payé** — dans le périmètre v1
   (avenant complet + re-signature) ou v2 (v1 se limite à « avant l'état reliquat ») ?
3. **Qui amende, et faut-il une validation ?** — le gestionnaire seul, ou un mini-circuit
   (contrôleur) sur l'avenant ?
4. **Portée de l'amendement** — modifie-t-il aussi les **dates de la mission**, ou
   seulement les **jours facturés** de l'OM ?

> Recommandation : trancher au moins **1** et **2** avant de lancer la Phase 1.

---

## 8. Références

| Sujet | Emplacement |
|---|---|
| Répartition avance/reliquat | `app/Services/CalculIndemniteService.php` |
| Circuit & propagation VALIDE | `app/Services/EtatPaiementService.php`, `docs/circuits-validation.md` |
| Périmètre payable | `Versement::scopeEligiblesLot()` |
| Part de l'avance paramétrable | `docs/parametrage-part-avance.md` |
| Générateurs de fichiers | `app/Services/Paiement/{MtnFileGenerator,MoovFileGenerator}.php` |
