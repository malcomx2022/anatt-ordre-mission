# Plan d'implémentation — Brouillon d'OM + double validation DAF → DG avec signatures

> Document de planification. **Aucun code applicatif n'est modifié par ce document.**
> Référence métier : état de paiement scanné `N°0069/ANaTT/DAF/C-SFC` du 23/06/2026.

---

## 1. Ce que dit le document de référence

Le PDF fourni est un **ÉTAT DE PAIEMENT**, pas un ordre de mission individuel.

| Élément | Valeur relevée |
|---|---|
| N° | `N°0069/ANaTT/DAF/C-SFC` |
| Objet | ÉTAT DE PAIEMENT : Frais de mission |
| Bénéficiaires | CCMP, ASS-DG, C/SPC, PF/SU, RML, CVA |
| Motif | Mission de vérification de la conformité des hangars destinés à abriter les candidats aux examens du permis de conduire |
| Période | Du lundi 15 au vendredi 19 juin 2026 |
| Ligne budgétaire | (6384) frais de mission |
| Lieux | COTONOU, PARAKOU, KANDI, NATITINGOU, SAVALOU |
| Colonnes | N° · NOM & PRENOMS · STRUCTURE · GRADE/INDICE · Lieu de Mission · MOTIF · TAUX/JOUR · NBRE DE JOURS · TOTAL PARTIEL · TOTAL · AVANCE · RELIQUAT · CONTACT |
| Totaux | TOTAL 1 320 000 · AVANCE 880 000 · RELIQUAT 440 000 |
| Arrêté | « Arrêté le présent état de paiement à la somme de : Quatre cent quarante mille (440 000) FRANCS CFA. » |
| Signature 1 | **Vu / LE DIRECTEUR DE L'ADMINISTRATION ET DES FINANCES** — Euloge D. ATOHOUN |
| Signature 2 | **Cotonou, le 23/06/2026 / LE DIRECTEUR GENERAL** — Richard DADA |

### Conséquences de lecture

**(a) L'état est produit par mission × tranche.** Le montant arrêté (440 000) est exactement
la somme de la colonne RELIQUAT : cet état est celui de la tranche **RELIQUAT**. Il couvre
les deux opérateurs (contacts en `01 96`, `01 66`, `01 97` mélangés).

> À retenir : **état de paiement = mission × tranche** (toutes opérateurs confondus),
> alors que le **fichier de paiement = mission × tranche × opérateur**. Ce sont deux
> granularités différentes et il ne faut pas les confondre.

**(b) La double signature se joue au niveau de l'état, donc de la mission — pas OM par OM.**
Faire signer 40 OM un par un au DG est inexploitable, et le circuit papier réel ne connaît
qu'un état par mission. Le plan retient donc : **action groupée au niveau mission**, mais
**visa stocké par OM** (traçabilité fine + rejet partiel possibles).

**(c) Les lignes multi-types sont déjà correctement modélisées.** Chaque bénéficiaire porte
3 sous-lignes (Mission à 1 repas / à 2 repas / complète) avec taux et nombre de jours propres :
c'est exactement `ordre_missions.lignes_per_diem`. Vérification sur la ligne 1 (OKRI I. Laure,
indice ≥ 800) : `30 000 × 1 + 45 000 × 4 = 210 000` ✓.

**(d) Le barème en base est conforme au document.** `BaremeSeeder` donne
ORDRE_1/INTERIEUR = 15 000 / 30 000 / 45 000 et ORDRE_3/INTERIEUR = 8 500 / 15 000 / 30 000,
identiques au scan. Rien à corriger de ce côté.

---

## 2. Anomalie à trancher avant la bascule (bloquante pour la conformité)

**La répartition avance/reliquat du code ne correspond pas au document officiel.**

| Source | Formule | 210 000 donne | 135 000 donne |
|---|---|---|---|
| Code actuel (`CalculIndemniteService::repartir`) | `avance = round(total × 0,70)` | 147 000 / 63 000 | 94 500 / 40 500 |
| État de paiement officiel | 2/3 – 1/3 | **140 000 / 70 000** | **90 000 / 45 000** |

Les deux lignes du scan confirment le 2/3 – 1/3 (`140 000 / 210 000 = 0,6667`,
`90 000 / 135 000 = 0,6667`). En 70/30, les fichiers MTN/Moov générés porteraient des
montants faux.

**Décision requise** (hors périmètre strict de la demande, mais à arbitrer avant toute mise
en production du circuit de validation, puisque le DG signerait des montants erronés) :

- **Option A** — passer à `avance = intdiv(total × 2, 3)`, reliquat = reste. Conforme au scan.
- **Option B** — conserver 70/30 si une note de service récente le prévoit, et corriger
  l'état de référence.

Impact Option A : `CalculIndemniteService::repartir()` (1 méthode), plus la mise à jour des
libellés « Avance (70 %) / Reliquat (30 %) » dans `resources/views/pdf/ordre-mission.blade.php`
et du CLAUDE.md. Les OM déjà enregistrés garderaient leurs montants historiques (pas de
recalcul rétroactif) — à confirmer.

---

## 3. État des lieux du circuit actuel

```
IMPORTE ──valider (om.valider, CONTROLEUR)──▶ VALIDE ──paiement confirmé──▶ SOLDE
   └────rejeter (motif ≥ 10 car.)───────────▶ REJETE
```

Points d'appui repérés dans le code :

| Fait | Emplacement | Pourquoi c'est important |
|---|---|---|
| `VALIDE` est la **porte du paiement** | `Versement::scopeEligiblesLot()` exige `statut = 'VALIDE'` | Si `VALIDE` conserve son sens, **le module paiement n'est pas touché du tout** |
| Transitions sous verrou pessimiste + audit | `MissionService::transition()` | Le nouveau workflow réutilise ce mécanisme, rien à réinventer |
| `EN_ATTENTE` présent dans l'ENUM mais **inutilisé** | migration `create_ordre_missions_table` | Slot libre ; on préfère malgré tout un `VISA_DAF` explicite |
| Toggle `doubleValidation` **déjà présent** | `SettingsView.vue:24,176` + liste blanche `routes/api.php:151` | Le flag UI existe et ne pilote rien → point d'ancrage tout prêt |
| `statut` et `users.role` sont des **ENUM MySQL** | migrations | Tout nouveau statut/rôle exige une migration `ALTER` |
| `MissionStatusBadge` utilise `Record<StatutOM, …>` | `components/missions/MissionStatusBadge.vue` | TS strict ⇒ l'oubli d'un statut est une **erreur de compilation** : garde-fou gratuit |
| PDF mis en cache sur **un chemin unique** | `PdfGenerator::chemin()` → `ordres/OM-{slug}.pdf` | **Collision brouillon / final** à corriger avant tout |

---

## 4. Machine à états cible

```
                        ┌───── rejeter (motif ≥ 10) ─────────────────┐
                        │                                            ▼
IMPORTE ──viser (DAF)──▶ VISA_DAF ──approuver (DG)──▶ VALIDE ──▶ SOLDE
   │                        │                         (payable)
   └── rejeter ─────────────┴──────────────────────▶ REJETE
```

Règles :

1. `VALIDE` **garde exactement son sens actuel** = double signature obtenue = payable.
2. `VISA_DAF` **n'est pas payable** : `scopeEligiblesLot` reste inchangé, ligne pour ligne.
3. Le rejet reste possible aux **deux** étapes (le DG peut rejeter un OM déjà visé DAF) ;
   le rejet depuis `VISA_DAF` efface le visa DAF (il n'a plus d'objet).
4. **Séparation des tâches** : le signataire du visa DAF ne peut pas être le signataire DG
   du même OM (contrôle vidé de sens sinon). Y compris pour un SUPER_ADMIN.
5. Toute évolution future rendant un OM modifiable après visa **doit invalider les visas**
   (les montants signés changeraient). Aujourd'hui sans objet : les OM n'ont ni `PUT` ni
   `DELETE`, ils sont uniquement retranscrits.

### Le flag de non-régression

`config('workflow.double_validation')`, alimenté par le toggle **déjà existant** de
`SettingsView` :

| Flag | Comportement | Effet sur l'existant |
|---|---|---|
| **OFF** (valeur de déploiement) | `IMPORTE --/valider--> VALIDE`, un seul acteur | **Strictement identique à aujourd'hui.** Toute la suite de tests passe sans être touchée |
| **ON** (après création des comptes DAF/DG) | `IMPORTE --/viser--> VISA_DAF --/valider--> VALIDE` | Nouveau circuit |

C'est le cœur de la stratégie « sans casser l'existant » : le code des deux circuits cohabite,
et la bascule est une décision d'exploitation, réversible, prise dans l'écran Paramètres.

> Effet de bord bénéfique : aujourd'hui le toggle affiche `true` par défaut alors que le
> comportement réel est une validation simple — l'UI ment. L'implémentation corrige cela.

---

## 5. Profilage des comptes

### 5.1 Deux nouveaux profils

| Profil | Libellé | Rôle dans le circuit |
|---|---|---|
| `DAF` | Directeur de l'Administration et des Finances | **1re** signature — « Vu » |
| `DG` | Directeur Général | **2e et dernière** signature — arrête l'état |

### 5.2 Deux nouvelles permissions

| Clé | Porte | Détenteurs |
|---|---|---|
| `om.viser` | `IMPORTE → VISA_DAF` | `DAF` |
| `om.approuver` | `VISA_DAF → VALIDE` | `DG` |

`om.valider` est **conservée telle quelle** : elle pilote le circuit simple (flag OFF) et reste
la permission du `CONTROLEUR`. Aucune permission existante n'est renommée ni retirée — c'est
ce qui garantit que `RoleMiddlewareTest` et les guards frontend restent valides.

### 5.3 Matrice cible (ajouts uniquement)

```php
'DAF' => [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.viser',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
],
'DG' => [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.approuver',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
],
```

Ni `DAF` ni `DG` ne reçoivent `paiements.generer` / `paiements.confirmer` : ils **autorisent**,
l'`AGENT_FINANCIER` **exécute**. C'est la séparation ordonnateur / payeur.

### 5.4 Points de synchronisation du RBAC (6 fichiers, tous à tenir alignés)

1. `backend/app/Support/Permissions.php` — constantes, `PROFILS`, `LIBELLES`, `TOUTES`, `MATRICE`
2. `backend/config/profils.php` — source de vérité éditable (`profils`, `libelles`, `permissions`, `matrice`)
3. Migration `ALTER` sur l'ENUM `users.role` (+ `DAF`, `DG`)
4. `frontend/src/utils/permissions.ts` — type `Permission` + matrice de repli
5. `frontend/src/views/admin/RolesView.vue` — matrice affichée
6. `frontend/src/views/admin/UserCreateView.vue` + `UserManagementView.vue` — listes de rôles

> Garde-fou à ajouter : un test qui compare `Permissions::TOUTES` à `config('profils.permissions')`
> et à la matrice frontend, pour qu'une désynchronisation casse la CI au lieu de produire un
> 403 silencieux en production.

---

## 6. Mécanique de la signature

### 6.1 Données (nouvelles colonnes `ordre_missions`)

```
visa_daf_par          FK users nullable      qui a visé
visa_daf_le           timestamp nullable     quand
visa_daf_nom          string nullable        SNAPSHOT du nom au moment du visa
visa_daf_fonction     string nullable        SNAPSHOT de la fonction
visa_daf_commentaire  text nullable          observation facultative

valide_dg_par         FK users nullable
valide_dg_le          timestamp nullable
valide_dg_nom         string nullable
valide_dg_fonction    string nullable

signature_daf_hash    string(64) nullable    empreinte d'intégrité
signature_dg_hash     string(64) nullable
```

**Pourquoi des snapshots de nom/fonction ?** Un document signé doit rester fidèle à ce qui a
été signé. Si le DAF est renommé, muté ou son compte désactivé six mois plus tard, la
réimpression de l'état ne doit pas réécrire l'histoire. La FK sert au lien technique, le
snapshot au document.

### 6.2 Intégrité

À chaque signature, on calcule :

```
hash = HMAC-SHA256(
    reference_semo | agent_id | nb_jours | montant_total | avance | reliquat
    | signataire_id | horodatage,
    clé = APP_KEY
)
```

Usages : détecter une altération des montants après signature, et imprimer un **code de
vérification** (12 premiers caractères) sur le PDF. Ce n'est pas une signature
cryptographique à valeur légale (pas de PKI/certificat) — c'est un scellé d'intégrité
interne. À dire explicitement au client pour éviter tout malentendu sur la portée juridique.

### 6.3 Signature visuelle (`users`)

```
signature_path    string nullable   image de signature (disque privé, jamais public)
fonction          string nullable   ex. « Directeur de l'Administration et des Finances »
```

Rendu sur le PDF, par ordre de préférence :
1. image de signature + nom + date + code de vérification ;
2. à défaut : mention « Signé électroniquement le JJ/MM/AAAA à HH:MM » + nom + code.

Contraintes de sécurité pour l'upload : PNG/JPG uniquement, taille max ~500 Ko, validation
MIME réelle (pas l'extension), stockage sur disque **privé**, servi seulement via une route
authentifiée. Une image de signature est une donnée sensible : elle ne doit jamais être
accessible en URL publique devinable.

---

## 7. Le brouillon téléchargeable

### 7.1 Problème à régler d'abord

`PdfGenerator::chemin()` renvoie `ordres/OM-{slug}.pdf`, chemin **unique** ; `telecharger()`
sert le fichier en cache s'il existe. Un brouillon écraserait le final — ou pire, un final
serait servi comme brouillon. **Correction préalable** : faire varier le chemin selon l'état
du document, ex. `ordres/OM-{slug}-{BROUILLON|VISA_DAF|FINAL}.pdf`.

### 7.2 Routes

```
GET /api/ordres/{ordre}/pdf/brouillon        → OM individuel, filigrané   (om.consulter)
GET /api/missions/{mission}/etat/brouillon    → état de paiement filigrané (om.consulter)
    ?tranche=AVANCE|RELIQUAT
```

Route dédiée plutôt qu'un `?brouillon=1` sur l'existant : la sémantique de `/pdf` reste
intacte, et l'audit distingue clairement les deux usages.

### 7.3 Rendu du brouillon — non négociable

Un brouillon d'un document qui autorise un paiement **doit être impossible à confondre**
avec un document approuvé :

- filigrane diagonal répété **« BROUILLON — NON VALIDÉ »** ;
- cadres de signature **vides**, avec la mention « En attente de visa » ;
- **aucun** code de vérification ;
- bandeau d'en-tête et pied de page portant `DOCUMENT PROVISOIRE` ;
- **aucun numéro d'état définitif** (`N°…/ANaTT/DAF/C-SFC` attribué seulement à l'arrêt).

Le même gabarit sert au brouillon et au final, piloté par une variable `$etat`
(`BROUILLON` | `VISA_DAF` | `FINAL`) : un seul gabarit à maintenir, pas de dérive entre les
deux rendus.

---

## 8. L'état de paiement (document du scan)

Nouveau générateur `EtatPaiementGenerator` + gabarit `resources/views/pdf/etat-paiement.blade.php`
reproduisant la maquette : en-tête ANaTT, bloc `ÉTAT DE PAIEMENT / BÉNÉFICIAIRES / MOTIF /
PÉRIODE / Ligne budgétaire`, tableau 13 colonnes avec sous-lignes par type de prise en charge,
ligne de totaux, phrase d'arrêt, puis les **deux blocs de signature**.

À prévoir :

- **Montant en lettres** en français (« Quatre cent quarante mille ») — petit utilitaire
  `MontantEnLettres`, testé unitairement (les pièges : 80, 71, 100, 200, 1000, accord de
  « quatre-vingts », « cent », « mille »).
- **Numérotation** `N°{séquence}/ANaTT/DAF/C-SFC`, séquence annuelle, attribuée **au moment
  de l'arrêt par le DG** et non à la génération du brouillon (sinon on brûle des numéros).
- Orientation **paysage** (13 colonnes).
- `etat.blade.php` existant est le gabarit **générique des exports de rapports** — ne pas le
  modifier, c'est un autre besoin.

---

## 9. Découpage en phases

Chaque phase est livrable, testable et déployable seule. Les phases 1 et 2 n'ont **aucun**
impact sur le circuit de validation.

### Phase 0 — Socle (aucun changement de comportement)

| # | Tâche | Fichiers |
|---|---|---|
| 0.1 | Migration : `VISA_DAF` dans l'ENUM `ordre_missions.statut` | nouvelle migration |
| 0.2 | Migration : colonnes de visa (§6.1) | nouvelle migration |
| 0.3 | Migration : `DAF`, `DG` dans l'ENUM `users.role` | nouvelle migration |
| 0.4 | Migration : `users.signature_path`, `users.fonction` | nouvelle migration |
| 0.5 | `config/workflow.php` + lecture du toggle `doubleValidation` de `settings.json` | nouveau |
| 0.6 | Permissions `om.viser` / `om.approuver` + profils `DAF`/`DG` | `Permissions.php`, `config/profils.php` |
| 0.7 | `fillable` + `casts` du modèle `OrdreMission` | `app/Models/OrdreMission.php` |

**Critère de sortie : `./vendor/bin/pest` passe sans qu'aucun test existant soit modifié.**

> Attention ENUM : sur MySQL un `ALTER` d'ENUM sur une grosse table verrouille. Volumétrie ici
> faible, mais prévoir la migration hors heures de service. Vérifier aussi que SQLite (tests)
> avale les migrations — SQLite ignore les ENUM, donc pas de souci côté CI.

### Phase 1 — Brouillon (indépendante)

| # | Tâche |
|---|---|
| 1.1 | `PdfGenerator::chemin()` dépendant de l'état → fin de la collision de cache |
| 1.2 | Variable `$etat` + filigrane + cadres de signature dans `pdf/ordre-mission.blade.php` |
| 1.3 | Route + méthode `pdfBrouillon` |
| 1.4 | Front : bouton « Télécharger le brouillon » (`MissionDetailView`, `MissionPdfView`) |
| 1.5 | Tests : le brouillon porte le filigrane, aucun code de vérification, pas d'écrasement du final |

### Phase 2 — État de paiement

| # | Tâche |
|---|---|
| 2.1 | `MontantEnLettres` + tests unitaires |
| 2.2 | Gabarit `pdf/etat-paiement.blade.php` (paysage, 13 colonnes, sous-lignes) |
| 2.3 | `EtatPaiementGenerator` (mission × tranche, agrégation, totaux) |
| 2.4 | Routes brouillon + final, séquence de numérotation |
| 2.5 | Tests : totaux = Σ des lignes ; le scan reproduit à l'identique sur un jeu de données équivalent |

### Phase 3 — Double validation

| # | Tâche |
|---|---|
| 3.1 | `ValidationWorkflowService` : `viser()`, `approuver()`, `rejeter()` — réutilise `transition()` (verrou pessimiste) |
| 3.2 | Contrôle de séparation des tâches (`visa_daf_par !== user->id`) |
| 3.3 | Calcul + stockage des hash de signature |
| 3.4 | Routes OM : `POST /ordres/{id}/viser`, `POST /ordres/{id}/approuver` |
| 3.5 | Routes mission (action groupée) : `POST /missions/{id}/viser`, `POST /missions/{id}/approuver` — 1 clic, N OM, 1 événement de signature |
| 3.6 | `/valider` conservée : route du circuit simple (flag OFF) — inchangée |
| 3.7 | Audit : `VISA_DAF_OM`, `APPROVE_DG_OM`, `GENERATE_ETAT` |
| 3.8 | Notifications : DAF prévenu à la retranscription, DG prévenu après visa DAF, gestionnaire prévenu au rejet |

### Phase 4 — Frontend & profilage

| # | Tâche |
|---|---|
| 4.1 | `StatutOM` + `MissionStatusBadge` : ajout `VISA_DAF` (« Visé DAF », variante `info`) |
| 4.2 | `useAuth` : `canViserOM()`, `canApprouverOM()` |
| 4.3 | `MissionDetailView` : boutons contextuels selon statut × permission × flag |
| 4.4 | Écran de validation groupée par mission (file d'attente DAF, puis DG) |
| 4.5 | `RolesView`, `UserCreateView`, `UserManagementView` : profils DAF/DG |
| 4.6 | Écran « Ma signature » (upload, prévisualisation, remplacement) |
| 4.7 | Filtre `statut=VISA_DAF` dans `MissionListView` |
| 4.8 | Seeder : comptes de démo `daf@anatt.bj`, `dg@anatt.bj` |

### Phase 5 — Bascule

| # | Tâche |
|---|---|
| 5.1 | Test E2E **flag OFF** = comportement actuel (non-régression) |
| 5.2 | Test E2E **flag ON** : IMPORTE → VISA_DAF → VALIDE → paiement → SOLDE |
| 5.3 | Test : un OM `VISA_DAF` **n'est pas** payable |
| 5.4 | Test : refus de la double signature par le même utilisateur |
| 5.5 | Création des comptes réels, upload des signatures, activation du toggle |
| 5.6 | Mise à jour `CLAUDE.md` (§ workflow, § profils, § endpoints) |

---

## 10. Plan de test

| Nature | Cas |
|---|---|
| Non-régression | Suite existante **non modifiée**, verte en flag OFF |
| Transitions | `IMPORTE→VISA_DAF` ok ; `IMPORTE→VALIDE` refusé en flag ON ; `VISA_DAF→VALIDE` ok ; double visa refusé |
| RBAC | DAF ne peut pas approuver ; DG ne peut pas viser ; CONTROLEUR sans effet en flag ON ; LECTEUR 403 |
| Séparation des tâches | Même utilisateur visa + approbation ⇒ refus |
| Paiement | `VISA_DAF` exclu de `scopeEligiblesLot` ; `VALIDE` inclus (inchangé) |
| Concurrence | Deux approbations simultanées ⇒ une seule aboutit (verrou pessimiste) |
| PDF | Filigrane présent sur brouillon / absent sur final ; signatures apposées après chaque étape ; pas de collision de cache |
| Intégrité | Hash stable au recalcul ; altération d'un montant ⇒ hash divergent |
| Montant en lettres | 440 000, 80, 71, 100, 200, 1 000, 1 320 000 |
| Audit | Chaque signature journalisée avec acteur, horodatage, IP |

---

## 11. Risques et parades

| Risque | Parade |
|---|---|
| Rupture du module paiement | `VALIDE` garde son sens ; `scopeEligiblesLot` **non touché** |
| Régression du circuit actuel | Feature flag OFF par défaut, réversible depuis l'écran Paramètres |
| ENUM MySQL verrouillant | Migration hors heures de service ; SQLite (CI) insensible |
| Désynchronisation RBAC back/front | Test de cohérence des trois matrices en CI |
| Brouillon pris pour un document approuvé | Filigrane + cadres vides + pas de code de vérification + pas de n° d'état |
| Collision de cache PDF | Chemin dépendant de l'état (Phase 1.1, préalable) |
| Numéros d'état brûlés | Numérotation attribuée à l'arrêt DG, pas à la génération du brouillon |
| Fuite d'images de signature | Disque privé + route authentifiée + validation MIME réelle |
| Montants signés faux (70/30) | **Arbitrer le §2 avant la bascule** |
| Blocage si DAF/DG absent | Prévoir la délégation (intérim) — voir §12 |

---

## 12. Questions ouvertes (arbitrage client)

1. **Répartition avance/reliquat** : 2/3 – 1/3 (conforme au scan) ou 70/30 ? — *bloquant*
2. **Rôle du CONTROLEUR** en flag ON : disparaît du circuit de signature, ou conserve un
   contrôle de conformité préalable au DAF (`IMPORTE → CONTROLE → VISA_DAF`) ?
3. **Délégation / intérim** : en cas d'absence du DAF ou du DG, qui signe ? (profil
   `DAF_INTERIM` ? délégation nominative datée ? aucun mécanisme ?)
4. **Granularité de signature** : confirmez-vous l'action groupée par mission (recommandée)
   plutôt qu'OM par OM ?
5. **Rejet par le DG** d'un OM déjà visé DAF : retour à `IMPORTE` pour re-visa, ou `REJETE`
   définitif ? (le plan retient `REJETE`)
6. **Portée juridique** de la signature : scellé d'intégrité interne (proposé) ou vraie
   signature électronique avec certificat (autre projet, autre budget) ?
7. **Numérotation** `N°xxxx/ANaTT/DAF/C-SFC` : séquence annuelle repartant à 1 chaque année ?
   Le `0069` du scan est-il un compteur d'états ou de courrier ?
8. **Signature sur l'OM individuel** aussi, ou uniquement sur l'état de paiement ?

---

## 13. Ordre d'exécution recommandé

```
§2 (arbitrage 70/30)  ─┐
                       ├─▶ Phase 0 ─┬─▶ Phase 1 (brouillon OM)      ─┐
                       │            └─▶ Phase 2 (état de paiement)  ─┤
                       │                                             ├─▶ Phase 3 ─▶ Phase 4 ─▶ Phase 5
                       └─────────────────────────────────────────────┘
```

Phases 1 et 2 parallélisables et livrables immédiatement : elles apportent déjà le
« brouillon téléchargeable avant validation » demandé, sans toucher au circuit de validation.
