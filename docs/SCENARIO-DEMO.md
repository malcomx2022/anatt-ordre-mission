# 🎬 Scénario de démo ANaTT — de l'employé au fichier de paiement

> De la **création d'un employé** à la **génération des fichiers de paiement**
> MTN **et** Moov, à partir d'un ordre de mission (OM) retranscrit depuis SEMO.
>
> Les montants et les fichiers ci-dessous sont **réels** : ils ont été produits
> par le code de l'application (calcul du per diem + générateurs MTN/Moov), pas
> saisis à la main.

---

## 1. Pré-requis — comptes de démonstration

Mot de passe commun : `password` (comptes de démo, **jamais en production**).

| Rôle | Compte | Sert à |
|---|---|---|
| Gestionnaire OM | `gestion@anatt.bj` | Créer les agents + retranscrire l'OM SEMO |
| Contrôleur | `controle@anatt.bj` | Valider les OM |
| Agent financier | `finance@anatt.bj` | Générer les fichiers MTN / Moov + confirmer |

---

## 2. Les données réelles de la démo

**Mission SEMO** — *Dossiers de passation de marchés*
**Lieu** : BOHICON (zone **INTERIEUR**) · **Période** : du 18 au 23 mai 2026
**Répartition per diem** (état ANaTT) : **1 jour « un repas » (15 000) + 6 jours
« journée complète » (45 000)** = **285 000** par bénéficiaire (catégorie ORDRE_1).

| Réf. SEMO | Bénéficiaire | Catégorie | Téléphone | → Opérateur (auto) | Transport |
|---|---|---|---|---|---|
| 0055000167 | SOUMANOU DJARA Sabirou | ORDRE_1 | 01 97 98 48 77 | **MTN** (préfixe 97) | — |
| 0055000161 | ATCHA Gabriel | ORDRE_1 | 01 96 69 58 24 | **MTN** (préfixe 96) | **10 000** |
| 0055000165 | MONHOUNVEDO Rousselot | ORDRE_1 | 01 67 26 74 55 | **MOOV** (préfixe 67) | — |
| 0055000162 | ABLESSOU Sacré cœur | ORDRE_1 | 01 67 75 20 11 | **MOOV** (préfixe 67) | — |

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
3. **Récapitulatif** : le per diem s'affiche en temps réel (avance **70 %** /
   reliquat **30 %**), puis **Enregistrer**.

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

### ÉTAPE C — Valider les OM  ·  compte `controle@`
`Ordres` → ouvrir chaque OM (statut IMPORTE) → **Valider** → statut **VALIDE**.

> ⚠️ **Préalable obligatoire** : un OM non validé (resté IMPORTE) n'apparaît **pas**
> parmi les bénéficiaires éligibles lors de la génération d'un fichier de paiement.

### ÉTAPE D — CAS 1 : fichier MTN  ·  compte `finance@`
`Paiements → MTN` (`/paiements/mtn`) → mission = *Dossiers de passation de marchés*
· tranche = **AVANCE** → **Générer**.

**Fichier réel produit** — `dossiers-de-passation-de-marches_AVANCE.txt` :

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
nouveau fichier (protection anti-double-paiement). Régénération possible tant
que le lot n'est pas confirmé.

### Bonus — le reliquat (30 %)
Rejouer les étapes D et E avec la tranche **RELIQUAT** :

- MTN reliquat : 85 500 + 88 500 = **174 000**
- Moov reliquat : 85 500 + 85 500 = **171 000**

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
   par chaque plateforme, avec garde **anti-double-paiement** à la confirmation.

---

## 6. Barème INTERIEUR utilisé (rappel)

| Catégorie | Un repas | Deux repas | Journée complète |
|---|---|---|---|
| ORDRE_1 (indice ≥ 800) | 15 000 | 30 000 | 45 000 |
| ORDRE_2 (indice 400-799) | 10 000 | 20 000 | 35 000 |
| ORDRE_3 (indice < 400) | 8 500 | 15 000 | 30 000 |

---

*Document de préparation de démonstration — ANaTT, Agence Nationale des Transports
Terrestres, République du Bénin. Données de démonstration uniquement.*
