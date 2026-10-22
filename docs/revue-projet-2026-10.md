# Revue complète du projet — octobre 2026

Revue du dépôt à l'état `92fc7ef` (fusion de la PR #124), suivie de trois campagnes de tests :

- **unitaires** : Pest `tests/Unit` et Vitest ;
- **intégrité** : Pest `tests/Feature`, qui joue l'API complète sur SQLite, avec scellés, idempotence et RBAC ;
- **fonctionnels** : Playwright (SPA réelle, API simulée) et une recette de bout en bout contre l'API Laravel **réellement démarrée** (seed de démo, 8 comptes).

Environnement de test : PHP 8.4.27, Node 22, Chromium.

## 1. Résultats

| Campagne | Outil | Résultat |
|----------|-------|----------|
| Unitaires backend | Pest `tests/Unit` | 127 / 127 |
| Intégrité backend | Pest `tests/Feature` | 408 / 408 (après correctifs, dont 4 nouveaux tests) |
| Style backend | Pint `--test` | conforme |
| Unitaires frontend | Vitest | 99 / 99, 23 fichiers (dont 3 nouveaux fichiers) |
| Statique frontend | `vue-tsc` + ESLint + oxlint | 0 erreur |
| Build frontend | `vite build` | OK |
| Fonctionnel UI | Playwright `e2e/parcours-critique` | 3 / 3 |
| Recette API réelle | script HTTP, 76 vérifications | 76 / 76 (après correctifs) |

La recette sur l'API réelle déroule le parcours suivant, profil par profil :

1. Connexion des 8 comptes.
2. Lectures autorisées et refus RBAC attendus.
3. Retranscription d'une mission de 2 OM : 3 jours, répartition 70/30, rejet d'un doublon SEMO.
4. Verrou de paiement tant que l'état n'est pas validé.
5. Circuit de l'état des avances : ordre des étapes, numéro exigé, OM passés à `VALIDE`, PDF.
6. Fichier MTN : en-tête, CRLF, MSISDN 229, total, re-téléchargement, confirmation, idempotence.
7. Amendement avec trop-perçu (`A_RECOUVRER`).
8. Circuit de l'état des reliquats, puis état général PDF.
9. Refus d'amender une fois les reliquats validés.
10. Document de retenue, puis passage à `RECOUVRE`.
11. Export Excel et journal d'audit.

## 2. Couverture par module

| Module | Écrans | Fichiers de tests backend | Tests |
|--------|--------|---------------------------|-------|
| Auth & sécurité | AUTH-01..03, SYS-04 | AuthTest, RoleMiddlewareTest, RoutesPermissionsTest, SeparationTachesTest | 31 |
| Personnel (agents) | PERS-01..06 | AgentTest, AgentFiltreActif, AgentStats, ImportModeleAgents, CategorieIndiceTransition, Unit/Categorie | 48 |
| Ordres de mission | OM-01..08 | MissionTest, MissionsIndex, OrdreIndexActifs, Pagination, PdfTest, WorkflowSocle, FicheOmEtat, Unit/MissionService | 58 |
| Barème & calcul per diem | TAUX-01..02 | BaremeTest, BaremeJumeau, AuditBareme, PartAvance, Unit/BaremeOfficiel, CalculIndemnite, MontantEnLettres | 119 |
| États de paiement (circuit) | états | EtatPaiement, CircuitValidation, CoherenceCircuits, EtatAutoSync, EtatBrouillonZero, EtatFinal, EtatIntegriteDocument, EtatSansEtat, SignatureParametres | 78 |
| Amendement & régularisations | fiche OM, Régularisations | AmendementOm, AmendementIntegriteEtat, AvanceEngagee, Regularisation, Solde | 35 |
| Paiements MTN / Moov | PAY-01..06 | PaiementTest, Paiement/AsyncGeneration, BarriereEtat, FileGenerator, PaiementHistorique, PaiementStatuts | 32 |
| Rapports | RAP-01..04 | RapportDateBorne, RapportImputation, RapportMensuelPipeline, RapportTropPercus, RapportsEtatsFiltres | 12 |
| Audit | AUD-01..02 | AuditDateBorne, AuditLogin | 4 |
| Administration & RBAC flexible | ADM-01..06 | UserManagement, UserPermission, UserStats, RolePermission, RolePersonnalise, SettingsController, AnneeFiscale, BackupController | 90 |
| Notifications | barre du haut | Notification, NotificationFeed | 10 |
| Seeders & recette | — | ComptesCircuitSeeder, DemoCircuitSeeder, RecetteE2E | 18 |
| **Total** | | | **535** |

Côté frontend, 16 vues sur 50 sont montées par au moins un test Vitest. Les écrans critiques **sans** test unitaire sont :

- `MissionCreateView`, qui fait l'aperçu du per diem ;
- `EtatDetailView`, qui porte le circuit de signature ;
- `PaiementMtnView` et `PaiementMoovView` ;
- `SignaturesView` et `RolesView`.

## 3. Défauts corrigés dans cette revue

Chaque correctif a un test de non-régression, vérifié **rouge avant / vert après**.

| # | Gravité | Défaut | Correctif |
|---|---------|--------|-----------|
| 1 | Bloquant | Un OM **rejeté** avant le récapitulatif repassait à `VALIDE` à la validation de l'état des avances, et devenait **payable** alors qu'il est absent de l'état signé. Reproduit sur l'API réelle : l'état compte 1 bénéficiaire, le fichier en paie 2. | `EtatPaiementService::propager` exclut `REJETE`. |
| 2 | Bloquant | Deux missions de **même objet** écrivaient le même `paiements/{slug}_{TRANCHE}.txt`. Reproduit : le re-téléchargement du lot A servait le fichier du lot B. | Un dossier par mission : `paiements/{mission_id}/…`. Le nom remis à la plateforme est inchangé. |
| 3 | Bloquant | Circuit par OM (mission sans état) : un amendement après paiement de l'avance recalculait 70/30. L'avance déjà décaissée était ignorée, d'où un sur-paiement sans trop-perçu détecté. | `MissionService::amender` engage l'avance dès qu'elle est `GENERE` ou `CONFIRME`. |
| 4 | Majeur | Une requête API non authentifiée sans `Accept: application/json` renvoyait une page HTML **500** (« Route [login] not defined »). | `bootstrap/app.php` : 401 JSON sur `/api/*`. |
| 5 | Bloquant | Le téléversement d'un paraphe (Paramètres › Signatures) envoyait le fichier sérialisé en JSON (`{}`), donc aucun paraphe n'était archivé. | En-tête `multipart/form-data`. |
| 6 | Majeur | Le mois courant était calculé en UTC. Au Bénin (UTC+1), l'option « Octobre » portait la valeur `2026-09` dans le rapport mensuel, le tableau de bord et le tableau analytique. | `moisLocal()` dans `utils/benin.ts`. |
| 7 | Majeur | Agent : effacer l'indice (`''`) faisait suggérer « Indice < 400 » quel que soit le salaire. | `categorieDeduite` ignore les valeurs non numériques. |
| 8 | Majeur | L'historique OM (OM-08) affichait `OM-<id>`, des codes d'action bruts et aucun détail : la vue lisait `numero`, `details` et des codes obsolètes. | La vue lit `reference_semo`, la colonne `donnees` et les codes `IMPORT_OM`, `VALIDATE_OM`, `REJECT_OM`, `AMEND_OM`. |

## 4. Points restants — tous traités (seconde passe)

Chaque point est couvert par un test, sauf mention contraire.

### Règles métier tranchées

| Point | Décision appliquée |
|-------|--------------------|
| État des reliquats signé au montant net (ex. −11 500 FCFA) | L'état porte le montant **payable** (Σ des reliquats positifs). Les trop-perçus n'abaissent plus le total ; le PDF les affiche à part, « hors total, à recouvrer par retenue sur salaire ». Vérifié sur l'API réelle : signé 27 000 = décaissé 27 000. |
| Pas de lot complémentaire | Colonne `lots_paiement.sequence` (migration `2026_10_22_090000`, clé unique mission × tranche × opérateur × séquence). Un lot `CONFIRME` n'est jamais réécrit ; un versement devenu payable après coup part dans le lot suivant (`…_COMPL2.txt`). Seconde barrière : un versement porté par un lot `CONFIRME` n'est jamais réinclus. |

### Intégrité des paiements

| Point | Correctif |
|-------|-----------|
| Amendement d'un OM dont un versement est `GENERE` | Refusé (422) : il faut confirmer le lot ou le passer en `ERREUR`. |
| Numéro corrigé non repris à la régénération | Passer un lot en `ERREUR` remet ses versements `EN_ATTENTE` avec le téléphone et l'opérateur **courants** de l'agent. |
| Ordonnateur-payeur via des droits individuels | L'édition et la réinitialisation d'un profil contrôlent l'effectif de **chaque membre** et nomment les comptes en cause. |
| Amendement concurrent d'un contrôle | États lus avec `lockForUpdate` dans la transaction de l'amendement (non testable sous SQLite). |
| Même agent deux fois dans une mission | `ordres.*.agent_id` est `distinct`. |
| Statut `ANNULE` jamais produit | Rejeter un OM annule ses versements `EN_ATTENTE`, et `ANNULE` est exclu des fichiers. |

### Frontend

| Point | Correctif |
|-------|-----------|
| Lot > 50 lignes affiché en erreur | Réponse 202 détectée, suivi `GET /lots/job/:id` toutes les 2 s avec barre de progression, téléchargement à la fin, arrêt du suivi au démontage. |
| Part d'avance figée à 70 % | Pas de mise en cache en cas d'échec ; rechargement à la sortie du changement de mot de passe imposé ; lectures réactives dans les vues. |
| Pas d'interface pour le circuit par OM | Boutons Valider (`om.valider`) et Rejeter (`om.rejeter`, motif ≥ 10) sur la fiche, pour un OM `IMPORTE` d'une mission sans état. |
| Page blanche sur un 404 | Carte d'erreur avec le message serveur et un bouton « Réessayer » (fiche OM et aperçu PDF). |
| Minuteur non annulé | Annulé au démontage (non testé). |
| Design system | `flag-bar` et année dynamique sur la page de connexion ; icône SVG `check` à la place du glyphe « ✓ » (non testé). |
| Page de maintenance | Compte à rebours (`?fin=`) et revérification automatique de l'API toutes les 30 s (non testé). |
| Garde de route au rechargement | Repasse par la redirection vers le changement de mot de passe imposé (non testé). |
| Message serveur perdu | `getErrorMessage` conserve le message d'une `Error` levée par l'application. |
| Lots complémentaires | Badge « complémentaire n° N » dans la liste des lots. |

### Fonctionnalités annoncées

- **ADM-06** : téléchargement (`GET /admin/backups/:name/download`) et **restauration** (`POST /admin/backups/:name/restaurer`), réservés au SUPER_ADMIN.
  - La confirmation se fait en recopiant le nom du fichier.
  - La commande `db:restore` prend une sauvegarde de sécurité de l'état courant avant d'écraser la base ; un `.gz` est décompressé en PHP.
  - L'opération est auditée (`BACKUP_RESTORE`) et l'écran a une modale de confirmation.
  - Limite : l'exécution réelle du client `mysql` n'est pas testée (pas de serveur MySQL en CI) ; elle est simulée dans les tests.

### Documentation et exploitation des tests

- `CLAUDE.md` §7 corrigé :
  - droits de lecture de `LECTEUR` ;
  - `om.rejeter` ;
  - routes manquantes ajoutées : état général, brouillon d'OM, suivi d'export, signatures, sauvegardes, changement de mot de passe, notifications.
- `docs/amendement-om.md` et `docs/rbac-flexible.md` sont alignés sur ces changements.
- `phpunit.xml` porte une `APP_KEY` de test : la suite passe sans `.env`. Il reste des avertissements de phpdotenv, sans effet ; le code de sortie est 0.
- `playwright.config.ts` accepte `PLAYWRIGHT_CHROMIUM_EXECUTABLE`.

### Points mineurs hors rapport — traités (troisième passe)

| Point | Correctif |
|-------|-----------|
| Pas de plafond de `per_page` sur `/etats` et `/missions` | Taille de page bornée entre 5 et 100 par `getPerPage`. Le défaut de ces deux écrans reste 20. |
| Le filtre `operateur` de `/ordres` portait sur l'agent | Il porte sur l'opérateur du versement, celui du paiement. Un OM sans versement (exonéré) se rabat sur l'opérateur de l'agent. |
| Deux exports lancés dans la même seconde s'écrasaient | Le nom de fichier reçoit un suffixe aléatoire. |
| Transport perdu quand un OM passe à 0 jour | Seule l'exonération annule tout. Sans jour facturé, le per diem vaut 0 mais le transport reste dû. |

Chaque point a un test qui échoue sans le correctif et passe avec. Pest : 552 / 552. La recette sur l'API réelle donne 76 / 76.
