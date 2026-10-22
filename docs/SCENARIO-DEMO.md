# 🎬 Scénario de démo ANaTT — de l'employé au fichier de paiement

> De la **création d'un employé** à la **génération des fichiers de paiement**
> MTN **et** Moov, à partir d'un ordre de mission (OM) retranscrit depuis SEMO.
>
> Les montants et les fichiers ci-dessous sont **réels** : ils ont été produits
> par le code de l'application (calcul du per diem + générateurs MTN/Moov), pas
> saisis à la main. Dernière vérification : **22 octobre 2026**, scénario complet
> rejoué sur l'API (version issue de la revue d'octobre 2026). Compléments du même jour,
> sans effet sur les montants ni les fichiers : séparation des tâches détaillée, document
> à montrer pour la validation, signatures de l'état reprises sur le PDF de l'OM,
> restauration (purge de la matrice des permissions, réglages hors sauvegarde).

---

## 1. Pré-requis — comptes de démonstration

Mot de passe commun : `password` (comptes de démo, **jamais en production**).

| Rôle | Compte | Sert à |
|---|---|---|
| Gestionnaire OM | `gestion@anatt.bj` | Créer les agents + retranscrire l'OM SEMO + établir le récapitulatif de l'état |
| Contrôleur | `controle@anatt.bj` | Étape 1 : contrôler l'état de paiement |
| DAF | `daf@anatt.bj` | Étape 2 : apposer le visa « Vu » sur l'état |
| Directeur Général | `dg@anatt.bj` | Étape 3 : valider définitivement l'état (numéro requis) |
| Agent financier | `finance@anatt.bj` | Générer les fichiers MTN / Moov + confirmer |

> 🔐 **Séparation des tâches** : les trois signataires du circuit (contrôleur, DAF,
> DG) doivent être **trois personnes distinctes** — d'où les trois comptes dédiés. Le DAF
> ne peut pas viser un état qu'il a contrôlé ; le DG ne peut pas valider un état qu'il a
> contrôlé **ou** visé. Même le `superadmin@` ne peut pas enchaîner deux signatures.

---

## 2. Les données réelles de la démo

**Mission SEMO** — *Dossiers de passation de marchés*
**Lieu** : BOHICON (zone **INTERIEUR**) · **Période** : du 18 au 23 mai 2026
**Répartition per diem** (état ANaTT) : **1 jour « un repas » (15 000) + 6 jours
« journée complète » (45 000)** = **285 000** par bénéficiaire (catégorie INDICE_SUP_800).

| Réf. SEMO | Bénéficiaire | Catégorie | Téléphone | → Opérateur (auto) | Transport |
|---|---|---|---|---|---|
| 0055000167 | SOUMANOU DJARA Sabirou | INDICE_SUP_800 | 01 97 98 48 77 | **MTN** (préfixe 97) | — |
| 0055000161 | ATCHA Gabriel | INDICE_SUP_800 | 01 96 69 58 24 | **MTN** (préfixe 96) | **10 000** |
| 0055000165 | MONHOUNVEDO Rousselot | INDICE_SUP_800 | 01 67 26 74 55 | **MOOV** (préfixe 67) | — |
| 0055000162 | ABLESSOU Sacré cœur | INDICE_SUP_800 | 01 67 75 20 11 | **MOOV** (préfixe 67) | — |

> 🔑 **Point clé** : l'opérateur n'est **pas choisi** — il est **déduit
> automatiquement du numéro** (référentiel agent : préfixes MTN 50-59/90-99,
> Moov 40-49/60-69). C'est ce qui sépare naturellement les deux cas MTN et Moov.

---

## 3. Déroulé pas à pas

### ÉTAPE A — Créer les employés  ·  compte `gestion@`
`Personnel → Nouveau` (`/agents/nouveau`) — créer les 4 agents ci-dessus.
À la saisie du numéro, l'app affiche en temps réel le badge **MTN** ou **MOOV**
(contrôle de cohérence numéro ↔ opérateur).

### ÉTAPE B — Retranscrire l'OM SEMO  ·  compte `gestion@`
`Ordres → Nouveau` (`/ordres/nouveau`), assistant en 3 étapes :

1. **Contexte** : objet « Dossiers de passation de marchés », destination BOHICON,
   dates 18 → 23/05/2026.
2. **Bénéficiaires** : ajouter les 4 ; cocher **« Répartition détaillée par type de
   repas »** → 1 j *un repas* + 6 j *journée complète*. Pour **ATCHA**, saisir
   **Transport = 10 000**.
   Un même agent ne peut figurer **qu'une fois** dans la mission : l'ajouter deux
   fois est refusé (« Le même agent figure deux fois dans la mission : un OM par
   bénéficiaire. »).
3. **Récapitulatif** : le per diem s'affiche en temps réel (avance **70 %** /
   reliquat **30 %** — part de l'avance **paramétrable** par le SUPER_ADMIN,
   70 % par défaut), puis **Enregistrer**.

**Résultat — données réelles générées par l'application :**

```
Réf SEMO     Bénéficiaire            Op.   Total     Avance    Reliquat  Transport
0055000167   SOUMANOU DJARA Sabirou  MTN   285 000   199 500    85 500        0
0055000161   ATCHA Gabriel           MTN   295 000   206 500    88 500   10 000
0055000165   MONHOUNVEDO Rousselot   MOOV  285 000   199 500    85 500        0
0055000162   ABLESSOU Sacré cœur     MOOV  285 000   199 500    85 500        0
```

À l'enregistrement, chaque OM est créé au statut **IMPORTE** avec ses versements
**AVANCE** et **RELIQUAT** (statut EN_ATTENTE).

### ÉTAPE C — Valider l'état des AVANCES (circuit contrôle → DAF → DG)

La validation ne se fait **pas** OM par OM : c'est **l'état de paiement de la
mission** — et d'abord celui des **avances** — qui passe le circuit. Une mission
porte deux états (avances puis reliquats) ; chacun a son propre circuit.

1. **Établir le récapitulatif**  ·  `gestion@` — depuis la mission, tranche
   **Avances**, cliquer **« Établir le récapitulatif »** → l'état passe en
   **BROUILLON** (PDF filigrané « BROUILLON — NON VALIDE » téléchargeable).
2. **Contrôler**  ·  `controle@` — `États de paiement` → ouvrir l'état → **Contrôler**
   → **CONTRÔLÉ**.
3. **Viser (Vu)**  ·  `daf@` — **Viser** → **VISÉ DAF** (interdit au contrôleur :
   séparation des tâches).
4. **Numéroter puis valider**  ·  `dg@` — saisir le **numéro** de l'état (registre)
   puis **Valider** → **VALIDÉ** (interdit au contrôleur et au DAF de cet état).
   Cette validation **arrête l'état**, fait passer les OM de la mission à **VALIDE**
   (un OM rejeté auparavant reste **REJETÉ**), fige l'avance de chacun et **ouvre les
   fichiers d'avance**.

> 📄 **Quel document montrer ?** Le document signé est le **PDF de l'état de paiement**
> (bouton **« Télécharger l'état »** sur l'écran de l'état) : il porte les trois signatures,
> le numéro et les paraphes. Le PDF de **chaque OM** reprend ces mêmes signatures (même
> code de vérification) — pratique pour montrer qu'un bénéficiaire est couvert, mais le
> document de référence reste l'état.

> ⚠️ **Préalable obligatoire** : un versement n'est éligible à un fichier que si
> l'état **de sa tranche** est **VALIDÉ** par le DG. Tant que l'état des avances
> n'est pas validé, aucun bénéficiaire n'apparaît à la génération des avances.

### ÉTAPE D — CAS 1 : fichier MTN  ·  compte `finance@`
`Paiements → MTN` (`/paiements/mtn`) → mission = *Dossiers de passation de marchés*
· tranche = **AVANCE** → **Générer**.

**Fichier réel produit** — `dossiers-de-passation-de-marches_AVANCE.txt`
(rangé côté serveur dans le dossier de **sa** mission : deux missions de même objet
ne s'écrasent plus) :

```
HDR,SARALY,2,406000
1,MSISDN,2290197984877,199500,SOUMANOU DJARA SABIROU DOSSIERS DE PASSATION DE MARCHES
2,MSISDN,2290196695824,206500,ATCHA GABRIEL DOSSIERS DE PASSATION DE MARCHES
```

- Séparateur **virgule**, fins de ligne **CRLF**, saut de ligne final, ASCII.
- En-tête `HDR,{compte},{nb_lignes},{montant_total}` (compte = `SARALY` en config).
- Détail `{seq},MSISDN,{229 + numéro},{montant},{libellé MAJUSCULES}`.
- Total avance MTN = **406 000** (dont les +6 500 d'ATCHA correspondent à son transport).

### ÉTAPE E — CAS 2 : fichier Moov  ·  compte `finance@`
`Paiements → Moov` (`/paiements/moov`) → même mission · tranche **AVANCE** → **Générer**.

**Fichier réel produit** — `dossiers-de-passation-de-marches_AVANCE.xlsx`
(feuille `Sheet1`, en-tête à 9 colonnes) :

| A · Receiver Id Type | B · IdentifierValue | C → F | G · Amount | H · Comment | I |
|---|---|---|---|---|---|
| MSISDN | `2290167267455` *(texte)* | *(vides)* | 199500 | MONHOUNVEDO ROUSSELOT DOSSIERS… | *(vide)* |
| MSISDN | `2290167752011` *(texte)* | *(vides)* | 199500 | ABLESSOU SACRE COEUR DOSSIERS… | *(vide)* |

- 9 colonnes : `Receiver Identifier Type | IdentifierValue | Validation KYC |
  Validation KYC value | PayerAccountName | AccountName | Amount | Comment |
  VoucherSecretCode`.
- Numéro en **texte** (préserve le 0 initial), montant **numérique** en colonne G.
- Total avance Moov = **399 000**.

### ÉTAPE F — Confirmer  ·  compte `finance@`
Après dépôt réussi sur les plateformes MTN / Moov → **Confirmer le lot**.
Les versements passent à **CONFIRME** et ne seront **jamais réinclus** dans un
nouveau fichier (protection anti-double-paiement). Seul un lot au statut **GÉNÉRÉ**
se confirme : un lot marqué « en erreur » doit d'abord être **régénéré**.
Régénération possible tant que le lot n'est pas confirmé (voir la variante 8.1).

> 💡 **Gros lot** : au-delà de 50 bénéficiaires, la génération part en arrière-plan ;
> l'écran affiche une barre de progression puis télécharge le fichier tout seul.

### Bonus — le reliquat (30 %)
Le reliquat vient **après** l'avance. Une fois l'état des avances **VALIDÉ** :

1. `gestion@` — établir le récapitulatif de la tranche **Reliquats** (l'onglet
   n'est débloqué qu'à ce moment) → **BROUILLON**.
2. Rejouer le circuit **contrôle → visa DAF → validation DG** (`controle@` → `daf@`
   → `dg@`) sur l'état des reliquats → **VALIDÉ** : les fichiers de reliquat s'ouvrent.
3. `finance@` — rejouer les étapes D et E avec la tranche **RELIQUAT** :

- MTN reliquat : 85 500 + 88 500 = **174 000**
- Moov reliquat : 85 500 + 85 500 = **171 000**

> Une fois les **deux** états (avances + reliquats) validés, un **état général**
> consolidé (avances + reliquats par bénéficiaire) est téléchargeable : c'est le
> document de clôture de la mission.

---

## 4. Récapitulatif des montants

| | Per diem | Transport | Total | Avance (70 %) | Reliquat (30 %) |
|---|---|---|---|---|---|
| SOUMANOU (MTN) | 285 000 | 0 | 285 000 | 199 500 | 85 500 |
| ATCHA (MTN) | 285 000 | 10 000 | 295 000 | 206 500 | 88 500 |
| MONHOUNVEDO (MOOV) | 285 000 | 0 | 285 000 | 199 500 | 85 500 |
| ABLESSOU (MOOV) | 285 000 | 0 | 285 000 | 199 500 | 85 500 |
| **Fichier MTN — AVANCE** | | | | **406 000** (2 lignes) | *(174 000 en reliquat)* |
| **Fichier MOOV — AVANCE** | | | | **399 000** (2 lignes) | *(171 000 en reliquat)* |

---

## 5. Les 3 messages à faire passer

1. **Séparation automatique MTN / Moov** par le numéro de téléphone (aucun choix
   manuel de l'opérateur).
2. **Fidélité à l'état de paiement officiel** : répartition multi-repas + montant
   de transport, avance **70 %** / reliquat **30 %**.
3. **Un fichier = 1 mission × 1 tranche × 1 opérateur**, au format exact attendu
   par chaque plateforme, avec garde **anti-double-paiement** à la confirmation :
   un lot confirmé n'est jamais réécrit, un retardataire part dans un **lot
   complémentaire** (variante 8.2).

---

## 6. Barème INTERIEUR utilisé (rappel)

| Catégorie | Un repas | Deux repas | Journée complète |
|---|---|---|---|
| INDICE_SUP_800 (indice ≥ 800) | 15 000 | 30 000 | 45 000 |
| INDICE_400_799 (indice 400-799) | 10 000 | 20 000 | 35 000 |
| INDICE_INF_400 (indice < 400) | 8 500 | 15 000 | 30 000 |

---

## 7. Variante — avenant : amendement d'un OM après paiement de l'avance

> Cas réel : la mission est partie, **l'avance a été payée**, puis la durée d'un
> ordre de mission change (on ajoute ou on retire des jours). L'avance est
> **engagée** : elle ne bouge plus. C'est le **reliquat** qui absorbe l'écart —
> quitte à devenir **négatif** (trop-perçu à récupérer).

**Point de départ** (bénéficiaire d'illustration, catégorie INDICE_SUP_800, zone
INTERIEUR, **6 jours journée complète** = 6 × 45 000) :

```
Total     Avance (70 %)   Reliquat (30 %)
270 000   189 000         81 000
```

L'état des **avances** de la mission est **VALIDÉ** (circuit contrôle → DAF → DG) :
l'avance **189 000** est décaissée et **figée** (`avance_engagee`). Désormais,
`reliquat = montant_total − 189 000`.

> ⚠️ **Avant d'amender** : l'avance doit être **confirmée**. Tant qu'un fichier
> contenant l'OM attend son dépôt (lot **GÉNÉRÉ**), l'amendement est refusé :
> confirmer le lot, ou le passer en erreur, puis amender.

### CAS A — rallonge (mission prolongée de 2 jours)  ·  compte `gestion@`

`Ordres → détail de l'OM` (`/ordres/:id`) → bouton **« Amender »** → **8 jours**
+ motif → **Enregistrer**.

```
Nouveau total   Avance (figée)   Reliquat
8 × 45 000       189 000          360 000 − 189 000
= 360 000                         = 171 000
```

Le versement **RELIQUAT** passe de 81 000 à **171 000** (statut `EN_ATTENTE`).
Il sera payable une fois l'**état des reliquats** validé. Le **journal des
amendements** de la fiche trace `6 j → 8 j`, delta **+90 000**.

### CAS B — réduction forte (mission écourtée à 3 jours) → **trop-perçu**  ·  `gestion@`

Même bouton **« Amender »** → **3 jours** + motif → **Enregistrer**.

```
Nouveau total   Avance (figée)   Reliquat
3 × 45 000       189 000          135 000 − 189 000
= 135 000                         = −54 000   ← trop-perçu
```

- La fiche affiche le badge **« Trop-perçu »** ; le versement RELIQUAT bascule en
  **A_RECOUVRER** et **sort du périmètre payable** (il ne part jamais dans un
  fichier MTN / Moov).
- `finance@` — `Paiements → Régularisations` (`/paiements/regularisations`) : la
  ligne apparaît, **à recouvrer = 54 000**.
- Bouton **« Document »** → **PDF de retenue sur salaire** (en-tête ANaTT) à
  remettre à la RH : bénéficiaire, mission, décomposition (avance engagée 189 000
  − montant dû 135 000 = **54 000**), motif de l'amendement, blocs de signature.
- Après la **retenue sur salaire** appliquée par la RH (hors Mobile Money),
  cliquer **« Marquer recouvré »** → statut **RECOUVRE** (le document repasse alors
  la mention « Recouvré le … »).

- **État des reliquats** : il porte le montant **réellement payé** (somme des
  reliquats **positifs**). Pour ce bénéficiaire seul, il est donc arrêté à **0** —
  rien à décaisser — et le PDF mentionne à part « trop-perçus hors total, à
  recouvrer par retenue sur salaire : **54 000 FCFA** ». Le montant signé est
  toujours égal à ce que les fichiers verseront.

> 🔒 **Intégrité** : l'état des **avances validé reste scellé** (HMAC) et
> **inchangé** après l'amendement (l'avance est figée). Un état des **reliquats**
> encore en `BROUILLON` se met à jour automatiquement (montant payable, trop-perçu
> sur la ligne du bénéficiaire) ; un état des reliquats **en cours de signature**
> doit être **rejeté** avant d'amender (rejet guidé). Cf.
> `docs/circuits-validation.md` §6 et `docs/amendement-om.md`.

### Le message à faire passer

L'application **ne re-décaisse pas** l'avance et **ne rembourse pas** toute seule :
elle **retranscrit** l'avenant, **réajuste le reliquat** et **trace** le trop-perçu
pour que la RH applique la retenue — cohérent avec la règle d'or (ni décaissement,
ni retenue automatiques).

---

## 8. Variantes de paiement — erreur de dépôt et lot complémentaire

> Fichiers ci-dessous **réels**, produits en rejouant le scénario §3 sur l'API.

### 8.1 Le dépôt échoue sur un mauvais numéro  ·  `gestion@` puis `finance@`

Le fichier MTN de l'étape D est refusé par la plateforme : le numéro d'**ATCHA**
est erroné.

1. `gestion@` — corriger la fiche d'ATCHA : **01 91 23 45 67** (toujours MTN).
2. `finance@` — `Paiements → MTN`, même mission et tranche → **« Marquer en erreur »**. Les
   versements du lot redeviennent **EN_ATTENTE** et reprennent le numéro
   **actuel** de l'agent.
3. **Générer** à nouveau → le fichier repart avec le bon numéro :

```
HDR,SARALY,2,406000
1,MSISDN,2290197984877,199500,SOUMANOU DJARA SABIROU DOSSIERS DE PASSATION DE MARCHES
2,MSISDN,2290191234567,206500,ATCHA GABRIEL DOSSIERS DE PASSATION DE MARCHES
```

4. Déposer, puis **Confirmer** : le lot MTN AVANCE est **CONFIRMÉ**.

### 8.2 Un bénéficiaire change d'opérateur → lot complémentaire  ·  `gestion@` puis `finance@`

Avant le paiement Moov, **ABLESSOU** signale qu'il est passé chez MTN
(**01 90 11 22 33**).

1. `gestion@` — mettre à jour sa fiche (numéro + opérateur **MTN**). Ses
   versements encore en attente suivent automatiquement le référentiel.
2. `finance@` — `Paiements → MTN`, même mission, tranche **AVANCE** → **Générer**.
   Le lot MTN déjà confirmé n'est **pas réécrit** : un **lot complémentaire** est
   créé, avec le seul nouveau bénéficiaire.

**Fichier réel** — `dossiers-de-passation-de-marches_AVANCE_COMPL2.txt` :

```
HDR,SARALY,1,199500
1,MSISDN,2290190112233,199500,ABLESSOU SACRE COEUR DOSSIERS DE PASSATION DE MARCHES
```

Dans la liste des lots, il porte le badge **« complémentaire n° 2 »** :

| Séquence | Statut | Lignes | Montant | Fichier |
|---|---|---|---|---|
| 1 | CONFIRMÉ | 2 | 406 000 | `…_AVANCE.txt` |
| 2 | GÉNÉRÉ | 1 | 199 500 | `…_AVANCE_COMPL2.txt` |

3. Le fichier **Moov** AVANCE ne contient plus que **MONHOUNVEDO** (1 ligne,
   **199 500**).

> Le message : SOUMANOU et ATCHA, déjà payés, ne sont **jamais** réinclus ;
> ABLESSOU est payé **une seule fois**, sur le bon opérateur.

### 8.3 (Option) Mission sans état de paiement — reprise de données

Pour une mission ancienne sans état de paiement, la fiche d'un OM **IMPORTE**
propose **« Valider »** (`controle@`) et **« Rejeter »** (motif ≥ 10 caractères).
Un OM rejeté voit ses versements **ANNULÉS** : il n'apparaîtra dans aucun fichier.
Dès qu'un état existe pour la mission, ces boutons disparaissent.

### 8.4 (Option) Sauvegarde et restauration  ·  `superadmin@`

`Administration → Sauvegardes` : **Nouvelle sauvegarde**, puis icône
**Télécharger**. Pour **Restaurer**, recopier le nom exact du fichier : l'application
crée d'abord une **sauvegarde de sécurité** de l'état courant (opération
réversible). À ne montrer que sur une base de démonstration.

Pour une démonstration parlante : prendre une sauvegarde, retirer une permission à un
profil dans **Matrice des rôles**, puis restaurer → la matrice revient à son état
d'avant **dès la requête suivante** (la restauration purge la matrice mise en cache).
Les réglages de `Paramètres` (part d'avance, exercice) ne font **pas** partie de la
sauvegarde : ils vivent dans `storage/app/settings.json` et ne bougent pas.

> Prérequis : le client `mysql` doit être accessible au serveur (`DB_RESTORE_BINARY`,
> `exec()` autorisé) — cf. `DEPLOY-CPANEL.md` §9.1. Sans lui, la restauration échoue
> proprement, base intacte.

---

*Document de préparation de démonstration — ANaTT, Agence Nationale des Transports
Terrestres, République du Bénin. Données de démonstration uniquement.*
