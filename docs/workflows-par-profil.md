# Workflows par profil — qui fait quoi, dans quel ordre

> Matrice extraite du code (`App\Support\Permissions`), pas rédigée de mémoire.
> Le circuit décrit ici est celui en mode **complet**
> (`WORKFLOW_DOUBLE_VALIDATION=true`). En mode simple, seule l'étape du contrôleur
> existe et elle valide directement.

---

## 1. Le parcours d'un ordre de mission, de bout en bout

```
   SEMO émet l'OM (hors application)
             │
             ▼
   ┌──────────────────┐
   │ GESTIONNAIRE_OM  │  retranscrit la mission + N bénéficiaires
   └──────────────────┘  calcul du per diem, création des versements
             │            statut : IMPORTE
             ▼
   ┌──────────────────┐
   │   CONTROLEUR     │  étape 1 — vérification de conformité
   └──────────────────┘  statut : VERIFIE                    ─── rejet possible
             │
             ▼
   ┌──────────────────┐
   │      DAF         │  étape 2 — « Vu »
   └──────────────────┘  statut : VISA_DAF                   ─── rejet possible
             │
             ▼
   ┌──────────────────┐
   │       DG         │  étape 3 — VALIDATION FINALE
   └──────────────────┘  statut : VALIDE — l'OM est verrouillé ─── rejet possible
             │
             ▼
   ┌──────────────────┐
   │ AGENT_FINANCIER  │  génère le fichier MTN (.txt) / Moov (.xlsx)
   └──────────────────┘  dépôt manuel sur la plateforme opérateur
             │            puis confirmation → statut : SOLDE
             ▼
        paiement effectué
```

**Le point de bascule est unique** : tant que le DG n'a pas validé, aucun
bénéficiaire n'entre dans un fichier de paiement. Ni la vérification du
contrôleur, ni le visa du DAF n'ouvrent le paiement — c'est vérifié par un test
qui compte les versements éligibles à chaque étape.

---

## 2. Ce que fait chaque profil

### GESTIONNAIRE_OM — la saisie

Retranscrit dans l'application les ordres de mission émis par SEMO. C'est le seul
profil qui crée de la donnée métier.

| Peut | Ne peut pas |
|---|---|
| Retranscrire une mission et ses N bénéficiaires | Valider quoi que ce soit |
| Gérer le référentiel agents (créer, modifier, importer) | Générer ou confirmer un paiement |
| Consulter OM, paiements, barème, rapports | Modifier le barème |
| Télécharger le brouillon d'un OM | Consulter le journal d'audit |

**Son geste quotidien** : `/ordres/nouveau` → mission + participants + CAV → le
per diem se calcule en temps réel → l'OM naît en `IMPORTE`, prêt pour le contrôle.

---

### CONTROLEUR — étape 1, la vérification

Premier regard sur l'OM retranscrit. Vérifie la conformité avant que le dossier ne
monte à la direction.

| Peut | Ne peut pas |
|---|---|
| **Vérifier** un OM `IMPORTE` → `VERIFIE` | Apposer le visa DAF |
| **Rejeter** avec motif (≥ 10 caractères) | Prononcer la validation finale |
| Consulter le journal d'audit | Générer ou confirmer un paiement |
| Consulter OM, paiements, barème, rapports | Modifier des données |

**Son geste** : bouton **Vérifier** sur la fiche OM. En circuit simple, ce même
bouton s'appelle **Valider** et mène directement à `VALIDE` — le libellé est piloté
par le serveur, l'utilisateur n'a rien à savoir du mode.

---

### DAF — étape 2, le visa

Deuxième signature. C'est le « **Vu** » qui figure sur l'état de paiement officiel.

| Peut | Ne peut pas |
|---|---|
| **Viser** un OM `VERIFIE` → `VISA_DAF` | Vérifier (étape du contrôleur) |
| **Rejeter** avec motif | **Prononcer la validation finale** |
| Consulter le journal d'audit | Générer ou confirmer un paiement |
| Consulter OM, paiements, barème, rapports | Gérer les agents ou les comptes |

**Ce que son visa ne fait pas** : il ne rend pas l'OM payable. Le dossier attend
encore le DG.

---

### DG — étape 3, la validation finale

Dernière signature du circuit. **C'est elle qui verrouille l'OM et ouvre le
paiement.**

| Peut | Ne peut pas |
|---|---|
| **Valider définitivement** un OM `VISA_DAF` → `VALIDE` | Apposer le visa DAF |
| **Rejeter** avec motif | Vérifier (étape du contrôleur) |
| Consulter le journal d'audit | Générer ou confirmer un paiement |
| Consulter OM, paiements, barème, rapports | Gérer les agents ou les comptes |

**Séparation des tâches** : une même personne ne peut pas porter deux signatures
du même OM. Si quelqu'un a visé en tant que DAF, la validation finale lui est
refusée sur ce dossier — y compris un super administrateur, qui cumule pourtant
les permissions. Une double signature par une seule personne viderait le contrôle
de sa substance.

---

### AGENT_FINANCIER — l'exécution

N'autorise rien, exécute. Sépare l'ordonnateur du payeur.

| Peut | Ne peut pas |
|---|---|
| Prévisualiser les bénéficiaires éligibles d'un lot | **Valider un OM à quelque étape que ce soit** |
| Générer le fichier MTN (`.txt`) / Moov (`.xlsx`) | Retranscrire un OM |
| Confirmer un lot après dépôt sur la plateforme | Modifier le barème |
| Marquer un lot en erreur (autorise la régénération) | Consulter le journal d'audit |

**Son geste** : `/paiements/mtn` ou `/paiements/moov` → choisir mission + tranche →
aperçu → télécharger → déposer sur la plateforme opérateur → revenir confirmer.

**Garde-fou** : un versement `CONFIRME` n'est jamais réinclus dans un nouveau
fichier, et un lot confirmé ne peut plus être régénéré. C'est l'anti-double-paiement.

---

### ADMIN — l'administration fonctionnelle

Supervise sans jamais exécuter ni signer.

| Peut | Ne peut pas |
|---|---|
| Gérer les utilisateurs (sauf super admins) | **Signer à une quelconque étape** |
| Modifier le barème per diem | Retranscrire un OM |
| Gérer le référentiel agents | Générer ou confirmer un paiement |
| Consulter le journal d'audit, les rapports | Toucher aux paramètres système |

---

### LECTEUR — la consultation

Lecture seule, périmètre volontairement étroit.

| Peut | Ne peut pas |
|---|---|
| Consulter le tableau de bord, les OM, les rapports | Voir le référentiel agents |
| — | Voir les paiements, le barème, l'audit |
| — | Toute action d'écriture |

---

### SUPER_ADMIN — l'accès total

Passe toutes les gardes (`Gate::before`). Seul à gérer les autres super
administrateurs et les paramètres système. Il existe toujours au moins un super
admin actif : le dernier ne peut être ni supprimé, ni désactivé, ni rétrogradé.

**Une exception à son omnipotence** : la séparation des tâches s'applique aussi à
lui. Il ne peut pas viser puis valider le même OM.

---

## 3. Matrice complète des permissions

Générée depuis `App\Support\Permissions` — source de vérité du RBAC.

| Permission | SUPER_ADMIN | ADMIN | GESTION. OM | AGENT FIN. | DAF | DG | CONTRÔLEUR | LECTEUR |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `dashboard.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `personnel.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `personnel.gerer` | ✅ | ✅ | ✅ | — | — | — | — | — |
| `om.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `om.retranscrire` | ✅ | — | ✅ | — | — | — | — | — |
| **`om.valider`** (étape 1) | ✅ | — | — | — | — | — | **✅** | — |
| **`om.viser`** (étape 2) | ✅ | — | — | — | **✅** | — | — | — |
| **`om.approuver`** (étape 3) | ✅ | — | — | — | — | **✅** | — | — |
| `om.rejeter` | ✅ | — | — | — | ✅ | ✅ | ✅ | — |
| `paiements.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `paiements.generer` | ✅ | — | — | ✅ | — | — | — | — |
| `paiements.confirmer` | ✅ | — | — | ✅ | — | — | — | — |
| `baremes.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `baremes.modifier` | ✅ | ✅ | — | — | — | — | — | — |
| `rapports.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `audit.consulter` | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | — |
| `utilisateurs.gerer` | ✅ | ✅ | — | — | — | — | — | — |
| `superadmins.gerer` | ✅ | — | — | — | — | — | — | — |
| `parametres.gerer` | ✅ | — | — | — | — | — | — | — |

Les trois permissions de signature sont **mutuellement exclusives** entre DAF, DG
et contrôleur : aucun profil métier n'en détient deux. C'est ce qui rend le circuit
infalsifiable sans complicité entre trois personnes.

---

## 4. Le brouillon, disponible à tous les relecteurs

Tout profil disposant de `om.consulter` peut télécharger le **brouillon** d'un OM
tant que le circuit n'est pas achevé (`IMPORTE`, `VERIFIE`, `VISA_DAF`).

L'épreuve porte un filigrane diagonal « BROUILLON — NON VALIDE » sur chaque page,
un bandeau « DOCUMENT PROVISOIRE — SANS VALEUR D'AUTORISATION DE PAIEMENT », des
cadres de signature vides, et **aucun code de vérification**. Elle ne montre jamais
de signature, même si l'OM en porte déjà : un document qui autorise un paiement
doit être impossible à confondre avec un document approuvé.

---

## 5. Le document signé

Le PDF de l'OM porte quatre blocs, dans l'ordre du circuit :

| Bloc | Rempli par | Contenu une fois signé |
|---|---|---|
| Le bénéficiaire | — (signature manuscrite) | nom de l'agent |
| Le Contrôleur — vérification | étape 1 | nom, date, code de vérification |
| **Vu** — Le Directeur de l'Administration et des Finances | étape 2 | nom, date, code de vérification |
| Le Directeur Général *(validation finale)* | étape 3 | nom, date, code de vérification |

Chaque signature enregistre un **instantané** du nom et de la fonction du
signataire : un renommage, une mutation ou la suppression du compte ne réécrit pas
un document déjà signé.

Le **code de vérification** est dérivé d'un scellé HMAC-SHA256 calculé sur les
montants signés. Si un montant est altéré après signature, le scellé recalculé
diverge. C'est un contrôle d'intégrité interne — **pas** une signature électronique
qualifiée : aucune PKI, aucun certificat, aucune valeur probante au sens légal.

---

## 6. Absence d'un signataire

**Aucun mécanisme de délégation n'est prévu, et c'est un choix.** L'application
étant accessible en ligne, le DAF et le DG peuvent signer depuis n'importe où, y
compris en congé — exactement comme ils signeraient un parapheur à distance. La
digitalisation reproduit le circuit manuel sans y ajouter d'intérim qui n'existe
pas dans la procédure papier.

Si un poste devait être vacant durablement, la voie reste l'administration des
comptes : un super administrateur réattribue le profil `DAF` ou `DG` à la personne
désignée. Ce changement est journalisé.

---

## 7. Où chaque profil atterrit en se connectant

| Profil | Voit dans le menu | Écran de travail principal |
|---|---|---|
| `GESTIONNAIRE_OM` | Dashboard, Personnel, Ordres, Paiements, Barème, Rapports | `/ordres/nouveau` |
| `CONTROLEUR` | Dashboard, Personnel, Ordres, Paiements, Barème, Rapports, Audit | `/ordres?statut=IMPORTE` |
| `DAF` | idem contrôleur | `/ordres?statut=VERIFIE` |
| `DG` | idem contrôleur | `/ordres?statut=VISA_DAF` |
| `AGENT_FINANCIER` | Dashboard, Personnel, Ordres, Paiements, Barème, Rapports | `/paiements` |
| `ADMIN` | tout sauf Paramètres et génération de paiements | `/admin/utilisateurs` |
| `LECTEUR` | Dashboard, Ordres, Rapports | `/` |
| `SUPER_ADMIN` | tout | `/admin/parametres` |

Le menu est piloté par les permissions renvoyées par `/api/auth/me/permissions` :
un profil ne voit jamais une entrée qu'il ne peut pas ouvrir.
