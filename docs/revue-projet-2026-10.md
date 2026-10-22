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

## 4. Points restants (non corrigés)

**C** = confirmé sur l'API réelle. **L** = vérifié à la lecture du code.

### À arbitrer (règle métier)

- **État des reliquats signé au montant net (C).** `montantsTranche` additionne les reliquats négatifs. Exemple observé : l'état est signé à **−11 500 FCFA**, alors que le fichier paie le seul reliquat positif. Il faut décider si l'état porte le montant payable (Σ > 0) et les trop-perçus en colonne distincte. Ce choix touche le PDF et le scellé HMAC.
- **Lot complémentaire (L).** Il n'existe qu'un lot par mission × tranche × opérateur, et un lot `CONFIRME` ne se régénère pas. Un versement devenu éligible plus tard ne peut donc plus jamais être payé. Exemples : changement d'opérateur, OM validé tardivement dans le circuit par OM.

### Intégrité des paiements

- **(L)** Amender un OM dont un versement est `GENERE` réécrit le montant d'une avance non engagée, ou décale `ordre.reliquat` par rapport au versement. `marquerErreur` laisse les versements en `GENERE` : la garde à ajouter doit en tenir compte.
- **(L)** Un changement de téléphone ou d'opérateur d'un agent n'est propagé qu'aux versements `EN_ATTENTE`. Après un lot passé en `ERREUR`, la régénération reprend l'ancien numéro.
- **(L)** La séparation ordonnateur/payeur n'est contrôlée que sur le profil. Un utilisateur ayant reçu `paiements.confirmer` à titre individuel cumule les deux droits si son profil reçoit ensuite `om.approuver`.
- **(L)** L'amendement lit les états sans `lockForUpdate`. Il peut entrer en concurrence avec un `controler` simultané.
- **(L)** `StoreMissionRequest` : il manque `distinct` sur `ordres.*.agent_id`, donc le même agent peut être retranscrit deux fois dans une mission.

### Frontend

- **(L)** Lot de plus de 50 lignes : la réponse 202 avec `tracking_id` s'affiche comme une erreur. Le suivi `GET /paiements/lots/job/:id` n'est jamais appelé.
- **(L)** `usePartAvance` met 70 en cache si l'appel échoue (423 au premier login). Toute la session affiche alors 70/30 même si `partAvance` vaut autre chose.
- **(L)** Le circuit par OM (`ordresService.valider` / `rejeter`) n'a aucun bouton sur la fiche OM-03.
- **(L)** `MissionDetailView` et `MissionPdfView` : un 404 laisse la page vide, sans message.
- **(L)** Le minuteur de recherche de `PaiementRegularisationView` n'est pas annulé au démontage.
- **(L)** Règles du design system :
  - le pied de page de `LoginView` n'a pas de `flag-bar` et son « © 2026 » est figé ;
  - un glyphe « ✓ » remplace l'icône SVG dans `RolesView` et `UserPermissionsView`.
- **(L)** `MaintenanceView` n'a pas le compte à rebours annoncé (SYS-03).

### Fonctionnalités annoncées absentes

- **ADM-06** : la restauration d'une sauvegarde n'existe pas. Seuls la création, le téléchargement et la suppression sont disponibles.
- Le statut versement `ANNULE` est documenté mais n'est jamais produit.

### Documentation (CLAUDE.md §7)

- « Agents / Barème : lecture tout authentifié » est faux : `LECTEUR` n'a ni `personnel.consulter` ni `baremes.consulter`, ce qui est conforme au tableau des profils.
- `POST /ordres/:id/rejeter` exige `om.rejeter`, pas `om.valider`.
- Routes existantes non documentées :
  - `missions/:id/etat/final` ;
  - `ordres/:id/pdf/brouillon` ;
  - `rapports/export/job/:id` ;
  - `admin/signatures*`, `admin/backups*` ;
  - `auth/mot-de-passe/changer`, `notifications*`.

### Exploitation des tests

- La suite Pest exige un `.env` avec `APP_KEY` : sans lui, 5 tests échouent (`MissingAppKeyException`). Il faut lancer `cp .env.example .env && php artisan key:generate` avant `php artisan test`.
- Le Chromium préinstallé ne correspond pas à la version attendue par `@playwright/test`. En local, passer `launchOptions.executablePath`.
