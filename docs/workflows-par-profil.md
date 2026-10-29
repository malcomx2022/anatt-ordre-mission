# Workflows par profil — qui fait quoi, dans quel ordre

> Matrice extraite du code (`config/profils.php`, lue par `App\Support\Permissions`),
> pas rédigée de mémoire. C'est la matrice **par défaut** : elle est éditable à chaud par
> le SUPER_ADMIN (cf. `docs/rbac-flexible.md`), une installation personnalisée peut donc
> s'en écarter.
>
> **Mise à jour 1.3 (octobre 2026).** Les trois signatures (contrôle → visa DAF →
> validation DG) se jouent sur l'**état de paiement** de la mission, une fois par tranche
> (avances, puis reliquats). Le circuit « double validation » par OM (statuts OM `VERIFIE`
> / `VISA_DAF`, drapeau `WORKFLOW_DOUBLE_VALIDATION`) a été **retiré** ; seule subsiste une
> validation par OM en **une étape**, pour les missions sans état. Référence :
> `docs/circuits-validation.md`.
>
> **Relu le 24/10/2026 contre le code** : matrice identique à `config/profils.php` ; ajout de
> la section *Qui est notifié* (§8), des règles de solde et d'amendement corrigées depuis
> (défauts n° 19 et 22 à 28) et de la place des rôles personnalisés.
>
> **Relu le 27/10/2026 (version 1.4)** : matrice (§3) et menu (§7) toujours conformes à
> `config/profils.php` et `navigation.ts` ; §8 réécrit — les notifications suivent désormais les
> **droits effectifs** (décision D1), plus les profils système.
>
> **Complété le 28/10/2026** : réglages de *Paramètres* et de *Messagerie* appliqués par le serveur —
> rôle du SUPER_ADMIN précisé (§2), effet du mode maintenance et de la durée de session sur tous les
> profils (§7), e-mails envoyés par le serveur de *Messagerie* (§8) ; puis refus expliqués et
> « Enregistrer tout » (§2), signature jamais bloquée par un e-mail qui ne part pas (§8) ; le 29/10, motif
> de tout refus du circuit affiché en français (§3).

---

## 1. Le parcours d'une mission, de bout en bout

```
   SEMO émet les OM (hors application)
             │
             ▼
   ┌──────────────────┐
   │ GESTIONNAIRE_OM  │  retranscrit la mission + N bénéficiaires (OM : IMPORTE)
   └──────────────────┘  calcul du per diem, création des versements
             │            puis établit le RÉCAPITULATIF de l'état des AVANCES (BROUILLON)
             ▼
   ┌──────────────────┐
   │   CONTROLEUR     │  étape 1 — contrôle de conformité     état : CONTROLE
   └──────────────────┘                                      ─── rejet motivé possible
             │
             ▼
   ┌──────────────────┐
   │      DAF         │  étape 2 — « Vu »                     état : VISA_DAF
   └──────────────────┘                                      ─── rejet motivé possible
             │
             ▼
   ┌──────────────────┐
   │       DG         │  étape 3 — VALIDATION FINALE          état : VALIDE
   └──────────────────┘  (numéro de registre exigé)          ─── rejet motivé possible
             │            les OM passent VALIDE, l'avance est figée (avance_engagee)
             ▼
   ┌──────────────────┐
   │ AGENT_FINANCIER  │  génère les fichiers AVANCE MTN (.txt) / Moov (.xlsx)
   └──────────────────┘  dépôt manuel sur la plateforme, puis confirmation
             │
             ▼
   même circuit pour l'état des RELIQUATS (établi seulement après validation
   de l'état des avances) → fichiers RELIQUAT → confirmation
```

**Le point de bascule est unique, par tranche** : tant que le DG n'a pas validé l'état
d'une tranche, aucun versement de cette tranche n'entre dans un fichier
(`Versement::scopeEligiblesLot`, doublé de `FileGenerator::exigerEtatValide`). Ni le
contrôle, ni le visa du DAF n'ouvrent le paiement — vérifié par les tests du circuit
(`EtatPaiementTest`, `Paiement/BarriereEtatTest`).

**Mission sans état** (reprise de données, correction ponctuelle) : le contrôleur valide
l'OM directement sur sa fiche (`IMPORTE → VALIDE`, une étape, `om.valider`). Le rejet de l'OM
relève de `om.rejeter` : le contrôleur, le DAF et le DG peuvent le prononcer. Ce repli est
fermé dès qu'un état existe pour la mission.

---

## 2. Ce que fait chaque profil

### GESTIONNAIRE_OM — la saisie

Retranscrit dans l'application les ordres de mission émis par SEMO. C'est le seul profil
qui crée de la donnée métier.

| Peut | Ne peut pas |
|---|---|
| Retranscrire une mission et ses N bénéficiaires | Signer un état (contrôle, visa, validation) |
| **Établir / rafraîchir le récapitulatif** de l'état d'une tranche | Numéroter un état |
| **Amender** les jours facturés d'un OM (motif ≥ 10 caractères) | Générer ou confirmer un paiement |
| Gérer le référentiel agents (créer, modifier, importer) | Modifier le barème |
| Consulter OM, états, paiements, barème, rapports ; télécharger les brouillons | Consulter le journal d'audit |

**Son geste quotidien** : `/ordres/nouveau` → mission + participants + CAV → le per diem
se calcule en temps réel → les OM naissent en `IMPORTE` → sur la mission, **Établir le
récapitulatif** de l'état des avances, qui part au contrôle.

**Amendement** : refusé pour un reliquat déjà payé ou un trop-perçu déjà recouvré (avenant formel,
hors application), un état en cours de signature ou un fichier en attente de dépôt. Un amendement
qui ramène le reliquat à 0, avance payée, solde l'OM ; une rallonge sur un OM soldé (avance versée à
100 %) le rouvre (`docs/amendement-om.md`).
Il est notifié du **rejet** d'un OM ou d'un état, et de chaque **lot confirmé**.

---

### CONTROLEUR — étape 1, le contrôle

Premier regard sur l'état de paiement. Vérifie la conformité avant que le dossier ne
monte à la direction.

| Peut | Ne peut pas |
|---|---|
| **Contrôler** un état `BROUILLON` → `CONTROLE` | Apposer le visa DAF |
| **Rejeter** un état avec motif (≥ 10 caractères) | Prononcer la validation finale |
| **Numéroter** un état (registre papier) | Générer ou confirmer un paiement |
| Valider / rejeter un OM d'une mission **sans état** | Retranscrire ou amender |
| Consulter le journal d'audit, OM, paiements, barème, rapports | |

**Son écran** : `/etats` — la file s'ouvre sur les états `BROUILLON`, ceux qui attendent
son contrôle. Il est notifié dès qu'un récapitulatif est établi (avances ou reliquats).

---

### DAF — étape 2, le visa

Deuxième signature. C'est le « **Vu** » qui figure sur l'état de paiement officiel.

| Peut | Ne peut pas |
|---|---|
| **Viser** un état `CONTROLE` → `VISA_DAF` | Contrôler (étape 1) |
| **Rejeter** un état avec motif | **Prononcer la validation finale** |
| **Numéroter** un état | Générer ou confirmer un paiement |
| Rejeter un OM d'une mission **sans état** (pas le valider) | Gérer les agents ou les comptes |
| Consulter le journal d'audit, OM, paiements, barème, rapports | |

**Ce que son visa ne fait pas** : il ne rend rien payable. Le dossier attend encore le DG.
Sa file s'ouvre sur les états `CONTROLE` ; il est notifié à chaque contrôle.

---

### DG — étape 3, la validation finale

Dernière signature. **C'est elle qui verrouille l'état, fait passer les OM à `VALIDE`
(état des avances) et ouvre les fichiers de paiement de la tranche.**

| Peut | Ne peut pas |
|---|---|
| **Valider définitivement** un état `VISA_DAF` → `VALIDE` (numéro exigé) | Contrôler ou viser |
| **Rejeter** un état avec motif | Générer ou confirmer un paiement |
| **Numéroter** un état | Gérer les agents ou les comptes |
| Rejeter un OM d'une mission **sans état** (pas le valider) | |
| Consulter le journal d'audit, OM, paiements, barème, rapports | |

Sa file s'ouvre sur les états `VISA_DAF` ; il est notifié à chaque visa du DAF.

**Séparation des tâches** (`workflow.separation_des_taches`, active par défaut) : une même
personne ne peut pas porter deux signatures du même état. Le DAF doit être distinct du
contrôleur ; le DG, distinct **des deux** — y compris un super administrateur, qui cumule
pourtant les permissions (`EtatPaiementService::viser` / `approuver`).

---

### AGENT_FINANCIER — l'exécution

N'autorise rien, exécute. Sépare l'ordonnateur du payeur.

| Peut | Ne peut pas |
|---|---|
| Prévisualiser les bénéficiaires éligibles d'un lot | **Signer un état à quelque étape que ce soit** |
| Générer le fichier MTN (`.txt`) / Moov (`.xlsx`) d'une tranche | Retranscrire ou amender un OM |
| Confirmer un lot après dépôt sur la plateforme | Modifier le barème |
| Marquer un lot en **erreur** (versements remis en attente, coordonnées de l'agent rafraîchies) | Consulter le journal d'audit |
| Télécharger le **document de retenue** d'un trop-perçu, à remettre à la RH | |
| Constater le **recouvrement** d'un trop-perçu (retenue RH appliquée), seul profil à le faire | |

**Son geste** : `/paiements/mtn` ou `/paiements/moov` → mission + tranche → aperçu →
télécharger (un gros lot se génère en arrière-plan, avec suivi) → déposer sur la plateforme
opérateur → revenir **confirmer**.

**Garde-fous** : un versement `CONFIRME`, ou porté par un lot `CONFIRME`, n'est jamais
réinclus ; un lot confirmé n'est jamais réécrit — un versement devenu payable ensuite part
dans un **lot complémentaire** (`…_COMPL2`), que l'écran propose (« Générer le lot
complémentaire »). C'est l'anti-double-paiement.

**Solde** : la confirmation d'un lot, comme le constat d'un recouvrement, passe l'OM à `SOLDE`
dès que tous ses versements non nuls sont réglés (payés ou recouvrés).

Il est notifié quand un **état est validé** (fichiers ouverts), quand un **OM est validé** par
le circuit par OM, et quand un amendement crée un **trop-perçu**.

---

### ADMIN — l'administration fonctionnelle

Supervise sans jamais exécuter ni signer.

| Peut | Ne peut pas |
|---|---|
| Gérer les utilisateurs (sauf super admins) ; **consulter** la matrice des permissions | **Signer à une quelconque étape** |
| Modifier le barème per diem | Retranscrire un OM |
| Gérer le référentiel agents | Générer ou confirmer un paiement |
| Consulter le journal d'audit, les paiements, les rapports | Modifier la matrice, les paramètres système, les sauvegardes |
| Changer le rôle d'un compte, l'activer ou le désactiver (sessions révoquées) | |

Il est notifié de chaque **lot confirmé**.

---

### LECTEUR — la consultation

Lecture seule, périmètre volontairement étroit.

| Peut | Ne peut pas |
|---|---|
| Consulter le tableau de bord, les missions, les OM, les états, les rapports | Voir le référentiel agents |
| — | Voir les paiements, le barème, l'audit |
| — | Toute action d'écriture |

---

### SUPER_ADMIN — l'accès total

Passe toutes les gardes (`Gate::before`). Seul à gérer les autres super administrateurs,
les paramètres système (dont la part d'avance et l'exercice), les paraphes des
signataires, les sauvegardes et la matrice des permissions (`roles.gerer`). Il existe
toujours au moins un super admin actif : le dernier ne peut être ni supprimé, ni désactivé,
ni rétrogradé.

Les réglages qu'il enregistre agissent pour **tous les profils** (depuis le 28/10/2026) :

| Écran | Ce qu'il règle | Effet sur les autres profils |
|---|---|---|
| *Paramètres › Paiements* | MTN / Moov ouverts | Un opérateur fermé ne peut plus être généré par l'`AGENT_FINANCIER` (bandeau, bouton grisé) |
| *Paramètres › Notifications* | Une bascule par type | Un type coupé n'est plus émis pour personne (§8) |
| *Paramètres › Sécurité* | Durée de session, tentatives de connexion, HTTPS forcé, journal des connexions échouées | Reconnexion au-delà de la durée ; connexion refusée une minute après trop d'échecs |
| *Paramètres › Maintenance* | Mode maintenance | Tous voient l'écran *Maintenance*, sauf lui ; ils reviennent seuls à leur page quand il le lève |
| *Messagerie* (`/admin/email`) | Serveur SMTP, expéditeur | Les e-mails de tous partent par ce serveur (sinon le `.env`) ; « Envoyer un e-mail de test » le vérifie |

Dans *Paramètres*, « Enregistrer tout » enregistre **et applique** tous les onglets d'un coup :
il relit chacun avant d'enregistrer. Une valeur refusée (part d'avance à 120 % ; dans *Messagerie*, port à 70000)
affiche son motif — « Le pourcentage d'avance doit être un entier entre 0 et 100. » — et rien
n'est enregistré.

Il n'est jamais notifié (§8) : c'est à lui de vérifier, après un changement de *Messagerie*,
que l'e-mail de test arrive. Un serveur faux ne bloque personne (§8) : seuls les e-mails
manquent, et l'e-mail de test est le seul à le lui signaler.

**Une exception à son omnipotence** : la séparation des tâches s'applique aussi à lui. Il
ne peut pas porter deux signatures du même état.

---

## 3. Matrice des permissions par défaut

Générée depuis `config/profils.php`. `SUPER_ADMIN` a tout, implicitement.

| Permission | SUPER_ADMIN | ADMIN | GESTION. OM | AGENT FIN. | DAF | DG | CONTRÔLEUR | LECTEUR |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `dashboard.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `personnel.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `personnel.gerer` | ✅ | ✅ | ✅ | — | — | — | — | — |
| `om.consulter` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `om.retranscrire` (saisie, récapitulatif, amendement) | ✅ | — | ✅ | — | — | — | — | — |
| **`om.valider`** (étape 1 — contrôle) | ✅ | — | — | — | — | — | **✅** | — |
| `om.numeroter` (numéro de registre) | ✅ | — | — | — | ✅ | ✅ | ✅ | — |
| **`om.viser`** (étape 2 — visa) | ✅ | — | — | — | **✅** | — | — | — |
| **`om.approuver`** (étape 3 — validation finale) | ✅ | — | — | — | — | **✅** | — | — |
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
| `roles.gerer` | ✅ | — | — | — | — | — | — | — |

Les trois permissions de signature sont **mutuellement exclusives** entre contrôleur, DAF
et DG : aucun profil métier n'en détient deux. Un **rôle personnalisé** (`docs/rbac-flexible.md`,
niveau 3) peut aussi porter une clé du circuit : il signe alors sous les mêmes garde-fous, et
reçoit les mêmes notifications que le profil système qui la détient (§8). Combinées à la séparation des tâches, elles
rendent le circuit infalsifiable sans complicité entre trois personnes. Un refus du circuit
s'affiche avec son motif, en français (« Vous avez contrôlé cet état : le visa DAF revient à une autre
personne. », « Renseignez le numéro de l'état avant la validation finale. »). Le serveur refuse
aussi qu'un profil — ou un utilisateur, compte tenu de ses droits individuels — cumule
`om.approuver` (ordonnateur) et `paiements.generer` / `paiements.confirmer` (payeur).

---

## 4. Les brouillons, disponibles à tous les relecteurs

Tout profil disposant de `om.consulter` peut télécharger :

- le **brouillon d'un OM** (`GET /api/ordres/:id/pdf/brouillon`) ;
- le **brouillon de l'état** d'une tranche (`GET /api/missions/:id/etat/brouillon`) ; le
  téléchargement « normal » de l'état reste filigrané tant que la tranche n'est pas validée.

L'épreuve porte un filigrane « BROUILLON — NON VALIDE », un bandeau « DOCUMENT PROVISOIRE —
SANS VALEUR D'AUTORISATION DE PAIEMENT », des cadres de signature vides et **aucun code de
vérification**. Elle ne montre jamais de signature : un document qui autorise un paiement
doit être impossible à confondre avec un document approuvé.

Le **document de retenue** d'un trop-perçu (`/paiements/regularisations` › *Document*) suit
`paiements.consulter`. Tous les profils qui voient les paiements peuvent le télécharger, donc
tous sauf le lecteur. Seul l'agent financier (`paiements.confirmer`) constate ensuite le
recouvrement (*Marquer recouvré*).

---

## 5. Les documents signés

**L'état de paiement** (une tranche) est le document que le circuit signe. Il porte les
trois blocs contrôle / « Vu » DAF / validation DG, le numéro de registre et, pour l'état
des reliquats, les trop-perçus mentionnés **hors total**. Une fois les deux tranches
validées, l'**état général** consolidé (`GET /api/missions/:id/etat/final`) les réunit.

Chaque signature enregistre un **instantané** du nom et de la fonction du signataire, et
**archive le paraphe** en vigueur : un renommage, une mutation, un changement de titulaire
ou la suppression du compte ne réécrit pas un document déjà signé. Un rejet purge les
paraphes archivés des signatures effacées.

Le **code de vérification** est dérivé d'un scellé HMAC-SHA256 calculé sur les montants
signés (et la tranche). Si un montant est altéré après signature, le scellé recalculé
diverge. C'est un contrôle d'intégrité interne — **pas** une signature électronique
qualifiée : aucune PKI, aucun certificat, aucune valeur probante au sens légal.

> **Le PDF de l'OM** porte quatre blocs (bénéficiaire, contrôleur, « Vu » DAF, DG). Sur le
> circuit par état, il **reprend les signatures de l'état des avances** qui couvre l'OM, au
> fur et à mesure qu'elles sont apposées : même nom, même fonction, même paraphe archivé,
> même code de vérification que sur l'état (`PdfGenerator::etatSignataire`). Un OM absent
> de l'état signé (rejeté, annulé) n'en reçoit aucune ; une signature portée par l'OM
> lui-même (validation par OM d'une mission sans état, données héritées) reste prioritaire.
> Le document probant demeure l'**état de paiement**. Le PDF de l'OM, lui, s'ouvre pour tout
> profil disposant de `om.consulter` depuis la fiche de l'OM, par le lien **« PDF de l'OM »**
> (écran `/ordres/:id/pdf`). C'est le seul accès une fois l'OM validé, le brouillon n'étant plus proposé.
>
> Après un **amendement** (gestionnaire OM), le PDF de l'OM est régénéré et affiche la
> répartition réelle : « Avance engagée », puis « Reliquat (ajusté par amendement) » ou
> « Trop-perçu (à recouvrer par retenue sur salaire) », avec un encadré qui rappelle
> l'amendement (date, jours avant → après, motif). Les signatures de l'état des avances
> restent affichées : elles portent sur l'avance, que l'amendement ne modifie pas
> (`docs/amendement-om.md` §4.6).

---

## 6. Absence d'un signataire

**Aucun mécanisme de délégation n'est prévu, et c'est un choix.** L'application étant
accessible en ligne, le DAF et le DG peuvent signer depuis n'importe où, y compris en
congé — exactement comme ils signeraient un parapheur à distance. La digitalisation
reproduit le circuit manuel sans y ajouter d'intérim qui n'existe pas dans la procédure
papier.

Si un poste devait être vacant durablement, la voie reste l'administration des comptes :
un administrateur réattribue le profil `DAF` ou `DG` à la personne désignée (changement de
rôle audité, sessions révoquées), et le SUPER_ADMIN met à jour son nom, sa fonction et son
paraphe dans **Signatures** (`/admin/signatures`).

---

## 7. Menu et écran de travail de chaque profil

Après connexion, chacun arrive sur le tableau de bord ; le menu ne montre que ce que ses
permissions ouvrent.

| Profil | Voit dans le menu | Écran de travail principal |
|---|---|---|
| `GESTIONNAIRE_OM` | Tableau de bord, Personnel, Missions, Ordres de mission, États de paiement, Paiements (consultation), Barèmes, Rapports | `/ordres/nouveau`, puis `/missions` (récapitulatifs) |
| `CONTROLEUR` | idem, sans Retranscrire, Nouvel agent ni Import, + Audit | `/etats` (file : brouillons) |
| `DAF` | idem contrôleur | `/etats` (file : contrôlés) |
| `DG` | idem contrôleur | `/etats` (file : visés DAF) |
| `AGENT_FINANCIER` | Tableau de bord, Personnel, Missions, Ordres, États, Paiements (génération, régularisations), Barèmes, Rapports | `/paiements/mtn`, `/paiements/moov`, `/paiements` |
| `ADMIN` | tout sauf Retranscrire, Générer MTN / Moov, Signatures et Paramètres (Messagerie, Sauvegardes) | `/admin/utilisateurs` |
| `LECTEUR` | Tableau de bord, Missions, Ordres de mission, États de paiement, Rapports | `/` |
| `SUPER_ADMIN` | tout | `/admin/parametres` |

Le menu est piloté par les permissions renvoyées par `/api/auth/me/permissions` : un
profil ne voit jamais une entrée qu'il ne peut pas ouvrir (verrouillé par
`frontend/src/__tests__/navigation.spec.ts`).

Pour **tous les profils** :

- **Session** : elle dure le temps réglé dans *Paramètres › Sécurité* (480 min par défaut) ; au-delà,
  ou si le compte est désactivé, son rôle changé ou son mot de passe réinitialisé, retour à la connexion.
- **Erreur passagère** : un incident momentané de l'API au rechargement d'une page (trop de requêtes,
  erreur, coupure réseau) ne déconnecte pas. L'écran *Erreur serveur* s'ouvre et « Réessayer » ramène à
  la page demandée.
- **Maintenance** : pendant une mise à jour ou le mode maintenance de *Paramètres*, l'écran
  *Maintenance* s'affiche (sauf pour le SUPER_ADMIN en mode maintenance) et revient seul à la page.

---

## 8. Qui est notifié

Chaque notification apparaît dans la cloche et part par e-mail (`NotificationService::notifier`).
Depuis la version 1.4 (décision D1), elle vise les comptes **actifs** qui détiennent, dans leurs
**droits effectifs**, la permission de l'étape suivante : profil, rôle personnalisé et droits
accordés ou retirés à titre individuel compris. Un compte désactivé ne reçoit rien (défaut n° 27).

| Événement | Permission visée | Profils notifiés (matrice par défaut) |
|---|---|---|
| Récapitulatif d'un état établi ou rafraîchi (avances ou reliquats) | `om.valider` | `CONTROLEUR` |
| État contrôlé | `om.viser` | `DAF` |
| État visé par le DAF | `om.approuver` | `DG` |
| État validé par le DG (fichiers de la tranche ouverts) | `paiements.generer` | `AGENT_FINANCIER` |
| État rejeté (motif) | `om.retranscrire` | `GESTIONNAIRE_OM` |
| OM validé (circuit par OM, mission sans état) | `paiements.generer` | `AGENT_FINANCIER` |
| OM rejeté | `om.retranscrire` | `GESTIONNAIRE_OM` |
| Lot de paiement confirmé | `om.retranscrire` ou `utilisateurs.gerer` | `GESTIONNAIRE_OM`, `ADMIN` |
| Trop-perçu créé par un amendement | `paiements.confirmer` | `AGENT_FINANCIER` |

Avec la matrice par défaut, les destinataires sont donc ceux d'avant la 1.4. Ce qui change dès
qu'on la personnalise :

- un **rôle personnalisé** ou un compte à qui l'on **accorde** la permission est notifié ;
- un membre du profil à qui l'on **retire** la permission ne l'est plus ;
- le **SUPER_ADMIN** n'est jamais notifié : son accès implicite n'est pas une responsabilité
  dans le circuit ;
- un compte visé à deux titres (lot confirmé : `om.retranscrire` **et** `utilisateurs.gerer`)
  ne reçoit qu'**une** notification.

Les modèles (objet, texte) sont listés dans *Paramètres › Messagerie* (`/admin/email`), avec la
permission visée par chacun ; détail dans `docs/rbac-flexible.md`. Depuis le 28/10/2026, le SUPER_ADMIN
peut **couper** un type dans *Paramètres › Notifications* : il n'est alors plus émis pour personne (ni
cloche ni e-mail). L'objet des e-mails est préfixé du nom de l'application. Les e-mails partent par
le serveur SMTP réglé dans *Messagerie* ou, champ vide, par celui du `.env` ; le SUPER_ADMIN vérifie
la configuration avec « Envoyer un e-mail de test ».

Un e-mail qui ne part pas (serveur injoignable, identifiants refusés) **ne bloque jamais** l'action
qui notifie : le `CONTROLEUR` qui contrôle, le `DAF` qui vise, le `DG` qui valide voient leur
signature enregistrée normalement, et le destinataire trouve la notification dans sa **cloche**.
Seul l'e-mail manque ; l'échec est journalisé côté serveur (`storage/logs/`) pour le SUPER_ADMIN.
