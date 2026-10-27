# Revue complète du projet — octobre 2026

Revue du dépôt à l'état `92fc7ef` (fusion de la PR #124), suivie de trois campagnes de tests :

- **unitaires** : Pest `tests/Unit` et Vitest ;
- **intégrité** : Pest `tests/Feature`, qui joue l'API complète sur SQLite, avec scellés, idempotence et RBAC ;
- **fonctionnels** : Playwright (SPA réelle, API simulée) et une recette de bout en bout contre l'API Laravel **réellement démarrée** (seed de démo, 8 comptes).

Environnement de test : PHP 8.4.27, Node 22, Chromium.

> **État au 23/10/2026, fin de journée.** Les correctifs ont été livrés en trois passes (PR #125 à
> #131), puis la documentation a été confrontée au code document par document (PR #132 à #152).
> Cette seconde étape a fait apparaître dix-neuf défauts de plus, tous corrigés avec un test :
> - n° 10 à 16 lors de la relecture des documents ;
> - n° 17 au rejeu de la recette et du scénario de démo ;
> - n° 18 à la relecture du guide utilisateur ;
> - n° 19 à 22 en confrontant les derniers documents à l'interface et aux gardes du serveur ;
> - n° 23 et 24 (solde d'un OM) en relisant `docs/circuits-validation.md`, après la PR #153 ;
> - n° 25 et 26 (OM soldé amendable, reliquat ramené à 0) en relisant `docs/amendement-om.md`, après la PR #154 ;
> - n° 27 (notifications envoyées aux comptes désactivés) en relisant `docs/rbac-flexible.md`, après la PR #155 ;
> - n° 28 (révision du n° 25 : OM soldé de nouveau amendable, rouvert si un montant est à régler) en relisant
>   `docs/parametrage-part-avance.md`.
>
> **Mise à jour du 25/10/2026.** La relecture document par document s'est poursuivie (PR #153 à #162) :
> - n° 29 en relisant `DEPLOY-CPANEL.md` ;
> - n° 30 et 31 en relisant `docs/deploiement-circuit-validation.md` ;
> - n° 32 en confrontant `backend/docs/openapi.yaml` aux réponses réelles de l'API.
>
> **Mise à jour du 26/10/2026.** Les quatre décisions du §7.1 sont prises et appliquées (option (b)
> pour chacune ; D2 avec reprise des horodatages). Pest compte 598 tests, Vitest 124. Le §7 dresse
> le point de ce qui reste.
>
> Tout est fusionné sur `main` (cf. §6). La colonne « Fin de revue » est mesurée après la PR #131, la
> colonne « 23/10 » après la PR #152. La recette et le scénario de démo ont été rejoués sur ce dernier
> état, avec 9 sondes supplémentaires sur les correctifs n° 19 et 22.

## 1. Résultats

| Campagne | Outil | Première passe | Fin de revue | 23/10 |
|----------|-------|----------------|--------------|-------|
| Unitaires backend | Pest `tests/Unit` | 127 / 127 | 129 / 129 | 129 / 129 |
| Intégrité backend | Pest `tests/Feature` | 408 / 408 | 423 / 423 | 444 / 444 |
| Total backend | Pest | 535 (65 fichiers) | 552 (65 fichiers) | **573 / 573 (68 fichiers)** ; 581 après les n° 23 à 28 |
| Style backend | Pint `--test` | conforme | conforme | conforme |
| Unitaires frontend | Vitest | 99 / 99, 23 fichiers | 113 / 113, 26 fichiers | **120 / 120, 27 fichiers** |
| Statique frontend | `vue-tsc` + ESLint + oxlint | 0 erreur | 0 erreur | 0 erreur |
| Build frontend | `vite build` | OK | OK | OK |
| Fonctionnel UI | Playwright `e2e/parcours-critique` | 3 / 3 | 3 / 3 | 3 / 3 |
| Recette API réelle | script HTTP | 76 / 76 (après correctifs) | 76 / 76 | **83 / 83** (76 + 7 sur les correctifs n° 4, 11 et 16), plus **9 / 9** sondes sur les correctifs n° 19 et 22 |
| Scénario de démo | rejeu de `docs/SCENARIO-DEMO.md` sur l'API | — | conforme (1 défaut trouvé et corrigé, §3 n° 9) | conforme, §7 « avenant » et §8.2 « lot complémentaire » compris (défaut n° 17 au rejeu ; n° 21 à la relecture contre l'écran) |
| Description OpenAPI | `OpenApiTest` (routes ↔ spécification) | — | — | 96 / 96 opérations |
| CI GitHub Actions | Lint, Pest, Vitest + type-check, Playwright, build | — | 5 / 5 (PR #130 et #131) | 5 / 5 sur chaque PR #132 à #152 |

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
| Ordres de mission | OM-01..08 | MissionTest, MissionsIndex, OrdreIndexActifs, Pagination, PdfTest, WorkflowSocle, FicheOmEtat, Unit/MissionService | 62 |
| Barème & calcul per diem | TAUX-01..02 | BaremeTest, BaremeJumeau, AuditBareme, PartAvance, Unit/BaremeOfficiel, CalculIndemnite, MontantEnLettres | 121 |
| États de paiement (circuit) | états | EtatPaiement, CircuitValidation, CoherenceCircuits, EtatAutoSync, EtatBrouillonZero, EtatFinal, EtatIntegriteDocument, EtatSansEtat, SignatureParametres | 78 |
| Amendement & régularisations | fiche OM, Régularisations | AmendementOm, AmendementIntegriteEtat, AvanceEngagee, Regularisation, Solde | 37 |
| Paiements MTN / Moov | PAY-01..06 | PaiementTest, Paiement/AsyncGeneration, BarriereEtat, FileGenerator, PaiementHistorique, PaiementStatuts | 34 |
| Rapports | RAP-01..04 | RapportDateBorne, RapportImputation, RapportMensuelPipeline, RapportTropPercus, RapportsEtatsFiltres | 13 |
| Audit | AUD-01..02 | AuditDateBorne, AuditLogin | 4 |
| Administration & RBAC flexible | ADM-01..06 | UserManagement, UserPermission, UserStats, RolePermission, RolePersonnalise, SettingsController, AnneeFiscale, BackupController | 96 |
| Notifications | barre du haut | Notification, NotificationFeed | 10 |
| Seeders & recette | — | ComptesCircuitSeeder, DemoCircuitSeeder, RecetteE2E | 18 |
| **Total** | | | **552** (535 en première passe) |

Comptes établis par `pest --list-tests` sur `main` en fin de revue. Depuis, 21 tests se sont
ajoutés avec les correctifs n° 10 à 22 (total 573) :

| Fichier | Tests ajoutés | Défaut couvert |
|---------|---------------|----------------|
| `BackupControllerTest` | 2 | n° 10 (purge de la matrice à la restauration), n° 14 (tri des sauvegardes) |
| `OmPdfSignaturesEtatTest` (nouveau) | 5 | n° 11 (signatures de l'état sur le PDF de l'OM) |
| `Paiement/AsyncGenerationTest` | 2 | n° 12 (échec d'une génération en file) |
| `OpenApiTest` (nouveau) | 3 | n° 15 (description OpenAPI verrouillée sur les routes) |
| `CoherenceCircuitsTest` | 1 | état rejeté : circuit par OM toujours fermé (comportement documenté, non défaillant) |
| `OmPdfAmendementTest` (nouveau) | 5 + 1 | n° 16 (PDF de l'OM amendé), n° 17 (dernier amendement) |
| `SoldeTest` | 1 | n° 19 (OM soldé malgré une tranche nulle) |
| `AmendementOmTest` | 1 | n° 22 (amendement refusé quand le reliquat est déjà payé) |
| `RegularisationTest` | 2 | n° 23 (OM soldé une fois son trop-perçu recouvré, pas avant le paiement de l'avance) |
| `CircuitValidationTest` | 1 | n° 24 (OM exonéré soldé à la validation par OM) |
| `AmendementOmTest` | 2 | n° 25 (OM soldé non amendable, remplacé par le n° 28), n° 26 (OM soldé quand l'amendement ramène le reliquat à 0) |
| `AmendementOmTest` | 2 − 1 | n° 28 (OM soldé par une avance à 100 % rouvert par une rallonge ; resté soldé sans montant à régler) — remplace le test de refus du n° 25 |
| `NotificationTest` | 2 | n° 27 (compte désactivé : ni notification ni e-mail) |

Côté frontend, 19 vues sur 50 sont montées par au moins un test Vitest (16 en première passe), et le
composant `PaiementGeneration` — qui porte la génération MTN/Moov et le suivi des gros lots — est
désormais testé (`paiements-async.spec.ts`). Les écrans critiques encore **sans** test unitaire sont :

- `MissionCreateView`, qui fait l'aperçu du per diem ;
- `EtatDetailView`, qui porte le circuit de signature ;
- `SignaturesView` et `RolesView`.

Ils restent couverts côté serveur (calcul, circuit, signatures, matrice) ; c'est une dette de tests
frontend, pas un défaut connu.

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
| 9 | Mineur | *(trouvé au rejeu du scénario de démo, après la seconde passe)* Le badge « complémentaire n° N » ne s'affichait jamais : `LotPaiementResource` n'exposait pas `sequence`. | Champ `sequence` ajouté à la ressource, test dans `PaiementTest`. |
| 10 | Majeur | *(trouvé après la revue, en mettant à jour `docs/rbac-flexible.md`)* `db:restore` ne purgeait pas la matrice des permissions, en cache sans expiration : les droits d'avant la restauration restaient appliqués. | `DatabaseRestore` appelle `PermissionRepository::vider()` après une restauration réussie ; test dans `BackupControllerTest` (Pest : 553 tests). |
| 11 | Mineur | *(constaté en réécrivant `docs/workflows-par-profil.md`)* Le PDF d'un OM validé par le circuit par état affichait ses trois cases de signature « en attente » : les signatures vivent sur l'état et n'étaient pas reprises. | `PdfGenerator` reprend les signatures de l'état des avances qui couvre l'OM (scellé, paraphe archivé) ; OM rejeté exclu ; clé de cache incluant le statut de l'état. Tests `OmPdfSignaturesEtatTest` (Pest : 558 tests). |
| 12 | Majeur | *(trouvé en mettant à jour `backend/docs/queues.md`)* L'échec définitif d'une génération asynchrone de lot passait le lot en `ERREUR` sans remettre ses versements `EN_ATTENTE` : OM bloqué à l'amendement, coordonnées non rafraîchies, pas d'audit. | `GenererLotPaiementJob::failed()` passe par `LotPaiementService::marquerErreur` ; lot `CONFIRME` jamais touché. Deux tests dans `AsyncGenerationTest` (Pest : 560 tests). |
| 13 | Majeur | *(idem)* La tâche Cron `queue:work --stop-when-empty` de `DEPLOY-CPANEL.md` ne traitait que la file `default` : en `QUEUE_CONNECTION=database`, gros lots et exports asynchrones n'étaient jamais exécutés. | Ligne Cron avec `--queue=paiements,etats,default` ; checklist post-déploiement alignée. |
| 14 | Mineur | *(trouvé en réécrivant `backend/docs/openapi.yaml`)* La liste des sauvegardes, annoncée « les plus récentes d'abord », triait la date affichée `d/m/Y` comme une chaîne : la sauvegarde du 22/10 passait avant celle du 03/11. | Tri sur l'horodatage du fichier ; test dans `BackupControllerTest`. |
| 15 | Mineur | *(idem)* `backend/docs/openapi.yaml` décrivait encore le modèle initial (OM saisis à la main, soumission puis double validation N1/DG, paiement direct, catégories A1-D) : 15 chemins, dont la moitié n'existait plus ; rien sur les missions, les états de paiement, les lots ni l'administration. | Spécification réécrite sur les routes réelles (96 opérations, schémas des ressources, permissions) ; `OpenApiTest` la compare aux routes à chaque exécution (Pest : 564 tests). |
| 16 | Majeur | *(trouvé en mettant à jour `docs/amendement-om.md`)* Après un amendement, le PDF de l'OM restait celui d'avant : l'écran télécharge `/pdf/download`, qui sert le fichier en cache, et la clé ne dépendait que des statuts, inchangés par l'amendement. Le PDF présentait aussi un reliquat ajusté ou négatif comme « Reliquat (30 %) », tirait le pourcentage du paramètre courant plutôt que des montants de l'OM, et calculait le taux journalier affiché transport compris. | Clé de cache `-AMD{n}` ; libellés de répartition réels (avance engagée, reliquat ajusté, trop-perçu) ; mention du dernier amendement ; taux hors transport. Tests `OmPdfAmendementTest` (Pest : 570 tests). |
| 17 | Mineur | *(trouvé au rejeu du scénario de démo, §7, le 23/10)* Deux amendements enregistrés dans la même seconde (`amende_le` est précis à la seconde) : le PDF de l'OM rappelait le **premier** (« 6 → 8 jours — Rallonge ») au lieu du dernier, et le journal de la fiche OM les listait dans le désordre. Le document de retenue, qui cite le motif du dernier amendement, partageait le même tri. Introduit par le n° 16 pour le PDF de l'OM, antérieur pour les deux autres. | Tri sur `amende_le` puis `id` (ordre de création) aux trois endroits. Test dans `OmPdfAmendementTest` (Pest : 571 tests). |
| 18 | Majeur | *(trouvé en mettant à jour le guide utilisateur, le 23/10)* Aucun lien de l'interface ne menait à l'écran du PDF officiel de l'OM (OM-07, `/ordres/:id/pdf`) : la fiche ne proposait que « Télécharger le brouillon », masqué une fois l'OM validé. Le PDF signé (n° 11) et le PDF amendé (n° 16) n'étaient joignables qu'en tapant l'adresse. Le guide et le scénario de démo citaient un bouton « PDF » inexistant, et le guide un bouton « Constater le recouvrement » qui s'appelle « Marquer recouvré ». | Lien « PDF de l'OM » dans l'en-tête de la fiche, à côté de « Historique » ; libellés corrigés dans le guide et le scénario. Test dans `mission-detail.spec.ts` (Vitest : 114 tests). |
| 19 | Mineur | *(trouvé en mettant à jour `docs/parametrage-part-avance.md`, le 23/10)* Avec une part d'avance à 100 % (ou à 0 %), la tranche nulle garde un versement de montant 0. Ce versement n'entre dans aucun fichier, donc n'est jamais confirmé, et l'OM, une fois l'autre tranche payée et confirmée, restait `VALIDE` au lieu de passer `SOLDE`. | `LotPaiementService::confirmer` ignore les versements nuls pour décider du solde. Test dans `SoldeTest` (Pest : 572 tests). |
| 20 | Mineur | *(trouvé en mettant à jour `docs/annee-fiscale.md`, le 23/10)* Sans année enregistrée, l'écran Paramètres proposait l'année civile, sans tenir compte du mois de début d'exercice. Avec un début en juillet, en mars 2027, il affichait « 2027-2028 », et le premier enregistrement figeait cet exercice alors que le serveur déterminait « 2026-2027 ». Sans effet sur les montants : aucun traitement ne consomme encore l'exercice. | Défaut de l'écran déterminé comme le serveur (`utils/exercice.ts` → `exerciceDe`) ; une année enregistrée ou choisie n'est jamais écrasée. Tests dans `settings-exercice.spec.ts` (Vitest : 118 tests). |
| 21 | Majeur | *(trouvé en mettant à jour `docs/SCENARIO-DEMO.md`, le 23/10)* L'écran de génération MTN/Moov ne permettait pas de produire un **lot complémentaire**. Dès que le dernier lot de la sélection était `CONFIRME`, il masquait le bouton de génération (« Lot confirmé — réinclusion bloquée »), même quand l'aperçu listait des versements devenus payables. Exemple : un agent passé de Moov à MTN (§8.2 du scénario). Le serveur produisait le lot de séquence 2, mais seul un appel direct à l'API y donnait accès ; la recette l'avait validé par l'API, pas par l'écran. | Le blocage ne vaut plus que si rien n'est payable ; sinon le bouton « Générer le lot complémentaire » est proposé. Liste des lots départagée par `id` (lots créés dans la même seconde). Tests dans `paiements-async.spec.ts` (Vitest : 120 tests) ; mock Playwright aligné sur l'API (aperçu vide après confirmation). |
| 22 | Majeur | *(trouvé en mettant à jour `docs/amendement-om.md`, le 23/10)* Sur une mission **sans état**, un OM dont le reliquat était déjà payé (`CONFIRME`, OM soldé) restait amendable par l'API. `reventilerVersements` ne réécrit pas un versement confirmé : le reliquat de l'OM changeait sans versement correspondant. Une rallonge n'était donc jamais payée et une réduction ne produisait pas de trop-perçu. L'écran masquait le bouton pour un OM soldé, pas l'API. | Garde dans `MissionService::exigerAmendementPossible` : reliquat `CONFIRME` → refus, avenant formel (équivalent de l'état des reliquats validé). Test dans `AmendementOmTest` (Pest : 573 tests). |
| 23 | Mineur | *(trouvé en mettant à jour `docs/circuits-validation.md`, le 23/10)* Un OM dont le trop-perçu était recouvré (`RECOUVRE`) ne passait jamais à `SOLDE`. Le solde n'était examiné qu'à la confirmation d'un lot, et `RECOUVRE` comptait comme un versement restant : l'OM, entièrement régularisé, restait `VALIDE` (filtre « Soldé » de la liste des OM, statut affiché). | Règle commune `LotPaiementService::solderSiRegle` (déplacée sur `OrdreMission` avec le n° 26) : versements `CONFIRME` ou `RECOUVRE` = réglés ; appelée à la confirmation **et** au recouvrement. Deux tests dans `RegularisationTest` (Pest : 575 tests). |
| 24 | Mineur | *(idem)* Sur le circuit par OM, un OM exonéré (prise en charge par un tiers, montant nul, aucun versement) restait `VALIDE` après validation, alors que la validation de l'état des avances le solde aussitôt. | `MissionService::valider` solde un OM de montant nul. Test dans `CircuitValidationTest` (Pest : 576 tests). |
| 25 | Majeur | *(trouvé en mettant à jour `docs/amendement-om.md`, le 23/10)* Un OM `SOLDE` restait amendable par l'API (l'écran masquait le bouton). Avec une part d'avance à 100 %, l'OM est soldé dès le paiement de l'avance ; une rallonge créait alors un reliquat positif `EN_ATTENTE` que rien ne pouvait payer, le périmètre payable exigeant un OM `VALIDE`. | `MissionService::exigerAmendementPossible` refuse un OM `SOLDE` (avenant formel), après les gardes plus précises (reliquat payé, trop-perçu recouvré). Test dans `AmendementOmTest`. |
| 26 | Mineur | *(idem)* Un amendement qui ramenait le total à l'avance déjà payée (10 jours à 35 000 réduits à 7, avance 245 000) laissait l'OM `VALIDE` avec un reliquat nul : plus rien à régler, mais jamais soldé. | La règle du solde passe sur le modèle (`OrdreMission::solderSiRegle`) et s'applique aussi après un amendement. Test dans `AmendementOmTest` (Pest : 578 tests). |
| 27 | Majeur | *(trouvé en mettant à jour `docs/rbac-flexible.md`, le 24/10)* Les notifications partaient vers **tous** les comptes du profil, désactivés compris : un compte désactivé, qui ne peut plus se connecter, recevait encore par e-mail les montants, bénéficiaires et missions. | `NotificationService::notifierRole` ne retient que les comptes actifs ; `SendNotification` n'envoie pas l'e-mail d'un compte désactivé entre-temps (file d'attente). Deux tests dans `NotificationTest` (Pest : 580 tests). |
| 28 | Majeur | *(trouvé en mettant à jour `docs/parametrage-part-avance.md`, le 24/10)* Régression du correctif n° 25 : refuser tout amendement d'un OM `SOLDE` rendait l'avenant impossible après paiement avec une part d'avance à 100 % (l'OM est soldé dès la confirmation de son avance), alors que la même rallonge reste acceptée à 70 %. Même blocage pour un OM soldé par un amendement qui avait ramené le reliquat à 0 (n° 26). | Le refus général est retiré : les gardes du reliquat payé et du trop-perçu recouvré couvrent les cas dangereux. `MissionService::amender` remet un OM soldé à `VALIDE` avant la règle du solde, qui le resolde s'il ne reste rien à régler ; l'écran propose « Amender » sur un OM soldé dont le reliquat n'est ni payé ni recouvré. Tests dans `AmendementOmTest` et `mission-detail.spec.ts` (Pest : 581 tests ; Vitest : 123). |
| 29 | Mineur | *(trouvé en mettant à jour `DEPLOY-CPANEL.md`, le 25/10)* Le correctif n° 4 ne couvrait que le 401 : sans en-tête `Accept`, une requête `/api/*` recevait encore une page HTML pour un 405 (GET sur `/api/auth/login`, test du §5.3 du guide de déploiement), un 404, un 403 ou un 500, et une redirection pour un 422. Le SPA n'était pas touché (axios envoie l'en-tête). | `bootstrap/app.php` : tous les rendus d'exception partagent le critère `expectsJson()` ou chemin `api/*`. Test dans `AuthTest` (Pest : 582 tests). |
| 30 | Mineur | *(trouvé en mettant à jour `docs/deploiement-circuit-validation.md`, le 25/10)* Sans fonction saisie dans *Paramètres › Signatures*, le libellé par défaut du poste passait avant la fonction du compte signataire : la fonction posée par `ComptesCircuitSeeder` (`DG_FONCTION`…) n'était jamais imprimée sur l'état. | `EtatPaiementService::empreinte` : Paramètres, puis compte, puis libellé par défaut (`SignatureParametres::pour()` expose `fonction_saisie`). Tests dans `EtatPaiementTest`. |
| 31 | Majeur | *(idem)* `ComptesCircuitSeeder` alignait le rôle de tout compte existant : un e-mail de poste égal à celui du super administrateur, souvent le seul, le rétrogradait. | Le compte `SUPER_ADMIN` est laissé tel quel et le poste signalé non attribué. Test dans `ComptesCircuitSeederTest` (Pest : 586 tests). |
| 32 | Mineur | *(trouvé en confrontant `backend/docs/openapi.yaml` aux réponses réelles, le 25/10)* Sans réglage enregistré, `GET`/`PUT /admin/settings` renvoyaient `"data": []` (tableau PHP vide) au lieu d'un objet, et une écriture vide gravait `[]` dans `settings.json`. | `SettingsController` renvoie et écrit un objet. Test dans `SettingsControllerTest` (Pest : 589 tests). |

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

Chaque point a un test qui échoue sans le correctif et passe avec. En fin de revue : Pest 552 / 552, recette sur l'API réelle 76 / 76.

## 5. Documentation remise à niveau

Chaque document a été confronté au code (routes, services, migrations, configuration) avant
modification ; les montants et fichiers du scénario de démo sont ceux produits par l'API.

| Document | Contenu mis à jour | PR |
|----------|--------------------|----|
| `docs/Guide-Utilisateur-ANaTT.docx` (1.4) | Chapitre « Nouveautés de la version 1.4 », sauvegardes et restauration, annexes ; régénéré par `generer_guide_utilisateur.py` | #126 |
| `docs/ANaTT_Documentation_Technique_Fonctionnelle.docx` (1.3) | Évolutions 1.3, entités, workflows amendement et sauvegarde, chapitre « Qualité et tests » ; régénérée par `generer_documentation_technique.py` | #126 |
| `docs/SCENARIO-DEMO.md` | Rejoué sur l'API ; nouveau §8 (lot en erreur, lot complémentaire, mission sans état, sauvegarde/restauration) | #127 |
| `DEPLOY-CPANEL.md` | §9.1 restauration, *Notes de version 1.3*, dépannage | #128 |
| `README.md` | Concepts 1.3, section Exploitation, index de la documentation | #128 |
| `CLAUDE.md` | Stack, structure, API, règles, commandes et variables remises au niveau du code | #129 |
| `AGENTS.md` | Deux erreurs corrigées, pièges de la revue | #130 |
| `CHANGELOG.md` | Section « Non publié » regroupée sous « Version 1.3 » | #130 |
| `docs/checklist-post-deploiement.md` | Contrôles 1.3, parcours de fumée à 11 routes, contrôles réservés à la recette | #131 |
| `docs/runbook-mise-a-jour-cron.md` | Encadrés 1.3, tâches Cron permanentes, retour arrière (`down()` de la migration 1.3) | #131 |
| `UPLOAD-VENDOR.md` | Étape « faut-il renvoyer `vendor/` ? », PHP 8.4 en local, `--prefer-dist`, extraction sans résidu | #131 |

Seconde étape, du 22 au 23/10 : chaque document a été relu contre le code, un par un.

| Document | Contenu mis à jour | PR |
|----------|--------------------|----|
| `docs/circuits-validation.md` | Alignement 1.3 | #132 |
| `docs/revue-projet-2026-10.md` | État de fin de revue | #132 |
| `docs/amendement-om.md`, `docs/rbac-flexible.md`, `docs/parametrage-part-avance.md`, `docs/annee-fiscale.md` | Alignement 1.3 ; l'exercice est documenté tel qu'il est (aucun écran ne le consomme) ; défaut n° 10 corrigé | #133 |
| `docs/workflows-par-profil.md`, `docs/categories-indice.md` | Réécriture pour le circuit par état ; bornes et formule de secours des catégories | #134 |
| `docs/SCENARIO-DEMO.md`, `docs/checklist-post-deploiement.md` | Séparation des tâches, document à montrer, restauration, contrôles complémentaires | #135 |
| *(code)* PDF de l'OM | Défaut n° 11 : signatures de l'état des avances reprises sur le PDF de l'OM | #136 |
| Guide utilisateur, documentation technique, `README.md` | PDF de l'OM signé, restauration, exercice ; `.docx` régénérés | #137 |
| `DEPLOY-CPANEL.md`, `docs/runbook-mise-a-jour-cron.md`, `CLAUDE.md`, `AGENTS.md` | Restauration, contenu de `storage/`, PDF de l'OM, cache RBAC | #138 |
| `backend/docs/queues.md` | Réécrit (modes `sync` / `database` / `redis`, comportement à l'échec) ; défauts n° 12 et 13 | #139 |
| `backend/docs/openapi.yaml` | Réécrit sur les 96 opérations réelles, verrouillé par `OpenApiTest` ; défauts n° 14 et 15 | #140 |
| `docs/circuits-validation.md` | Séparation des tâches, état rejeté, §7 « signatures sur le PDF de l'OM » | #141 |
| `docs/amendement-om.md` | §4.6 « PDF de l'OM amendé », tableau des cas complété ; défaut n° 16 | #142 |
| `docs/revue-projet-2026-10.md`, exploitation (déploiement, runbook, checklist), guide, démo, documents historiques | Rapport au 23/10 ; cache des PDF d'OM, ligne Cron des trois files | #143 |
| *(code)* recette et démo rejouées | Défaut n° 17 (dernier amendement départagé par l'id) ; Form Requests inutilisées supprimées | #144 |
| Guide utilisateur ; *(code)* fiche de l'OM | Défaut n° 18 (lien « PDF de l'OM ») | #145 |
| Documentation technique | Évolutions 1.3 complétées, écarts au code corrigés (Docker, Nginx, `QUEUE_CONNECTION`) | #146 |
| `DEPLOY-CPANEL.md`, `docs/runbook-mise-a-jour-cron.md`, `docs/checklist-post-deploiement.md` | Node 20.19+/22.12+, extensions PHP de `composer.lock`, `SUPERADMIN_*`, `schedule:run` | #147 |
| `UPLOAD-VENDOR.md` | PHP 8.4.x en 64 bits (PhpSpreadsheet déclare `<8.5`), tailles retirées | #148 |
| `docs/workflows-par-profil.md`, `docs/rbac-flexible.md` | Rejet par OM du DAF/DG, document de retenue, prise d'effet immédiate côté serveur (tests renforcés) | #149 |
| `docs/parametrage-part-avance.md`, `docs/annee-fiscale.md` | Défauts n° 19 (tranche nulle) et n° 20 (exercice proposé) | #150 |
| `docs/categories-indice.md` | Méthodes de `CategorieIndice`, recette frontend en une passe | #151 |
| `docs/SCENARIO-DEMO.md`, `docs/circuits-validation.md`, `docs/amendement-om.md` | Libellés exacts de l'interface ; défauts n° 21 (lot complémentaire) et n° 22 (reliquat payé) | #152 |

## 6. Livraison et suites à donner

### PR fusionnées sur `main`

| PR | Objet |
|----|-------|
| #125 | Correctifs : première passe (§3), seconde passe (§4) et troisième passe (points mineurs) |
| #126 | Guide utilisateur 1.4 et documentation technique 1.3 |
| #127 | Scénario de démo rejoué + correctif `sequence` (§3 n° 9) |
| #128 | Manuel de déploiement, checklist (contrôles 1.3), README |
| #129 | `CLAUDE.md` |
| #130 | `AGENTS.md` et `CHANGELOG.md` |
| #131 | Checklist post-déploiement, runbook Cron, `UPLOAD-VENDOR.md` |
| #132 – #135 | Documentation alignée sur la 1.3 (circuits, amendement, RBAC, part d'avance, exercice, workflows, catégories, démo, checklist) ; défaut n° 10 |
| #136 | PDF de l'OM signé par l'état (défaut n° 11) |
| #137 – #138 | Guide utilisateur, documentation technique, README, déploiement, runbook, `CLAUDE.md`, `AGENTS.md` |
| #139 | Files d'attente (défauts n° 12 et 13) |
| #140 | Description OpenAPI et tri des sauvegardes (défauts n° 14 et 15) |
| #141 | Circuits de validation : état rejeté, signatures du PDF |
| #142 | PDF de l'OM amendé (défaut n° 16) |
| #143 – #144 | Rapport au 23/10, exploitation ; rejeu de la recette et de la démo (défaut n° 17), Form Requests supprimées |
| #145 | Lien « PDF de l'OM » sur la fiche (défaut n° 18), guide utilisateur |
| #146 – #149 | Documentation technique, déploiement, runbook, checklist, `UPLOAD-VENDOR.md`, workflows, RBAC |
| #150 | OM soldé malgré une tranche nulle (n° 19), exercice proposé déterminé (n° 20) |
| #151 | Catégories d'indice |
| #152 | Lot complémentaire depuis l'écran (n° 21), amendement d'un reliquat payé refusé (n° 22) |
| #153 | README, `CLAUDE.md`, `AGENTS.md`, rapport et CHANGELOG au 23/10 |
| #154 | Solde après recouvrement et OM exonéré validé par OM (n° 23, 24) ; menu accentué ; démo rejouée |
| #155 | OM soldé et amendement (n° 25, 26) |
| #156 – #157 | Notifications des comptes désactivés (n° 27) ; OM soldé de nouveau amendable (n° 28) ; workflows, part d'avance, exercice, catégories, checklist |
| #158 | Runbook de mise à jour par Cron |
| #159 | Erreurs de l'API en JSON (n° 29) ; `DEPLOY-CPANEL.md`, `UPLOAD-VENDOR.md` |
| #160 | Fonction imprimée et super administrateur protégé (n° 30, 31) ; circuit historique, files d'attente |
| #161 | `/admin/settings` en objet (n° 32) ; `openapi.yaml` confronté aux réponses |
| #162 | `AGENTS.md` ; §7 de ce rapport (point restant et cartographie) ; documentation technique |
| #163 | Guide utilisateur |
| #164 | Maquettes HTML annotées (`docs/annoter_maquettes.py`) |
| #165 | README du backend et du frontend |
| #166 | Décisions D1 à D4 (notifications par droits effectifs, heure du Bénin, dépendances, écran de génération) ; maquettes refaites à partir des captures de l'application (`docs/capturer_ecrans.cjs`, `docs/generer_maquettes.py`) |
| #167 | Trois défauts d'interface relevés par les captures : bandeau `AppAlert`, libellés avance / reliquat après amendement, modèles de notification de l'écran e-mail |
| #168 | Guide utilisateur : nouveautés D1 à D4 et correctifs d'interface, 12 figures tirées des captures de l'application |
| #169 | Documentation technique 1.4 : évolutions D1 à D4, mise à jour d'une instance (§9.6), garde-fous de tests |
| #170 | Scénario de démonstration rejoué en version 1.4 |
| #171 | README et `AGENTS.md` confrontés au dépôt ; `start-dev.ps1 -Install` passe à `npm ci` |
| #172 | `CLAUDE.md` : version 1.4, mise en page du §4 confrontée aux composants, constat sur la règle n° 6 (thème) |

### À faire côté exploitation

1. **Mettre à jour l'instance en ligne** en suivant `docs/runbook-mise-a-jour-cron.md` : une seule
   migration (`2026_10_22_090000_add_sequence_to_lots_paiement`), `vendor/` inchangé, SPA à
   redéployer. Contrôler ensuite avec `docs/checklist-post-deploiement.md`. Les correctifs n° 17 à 32
   ne portent que sur le code (`app/`, `bootstrap/app.php`, seeders, SPA) : aucune migration de plus.
2. **Éprouver la restauration sur une base MySQL de recette** : `db:restore` n'est exercé qu'en
   simulation dans la CI (§4, *Fonctionnalités annoncées*). Les contrôles qui créent des données
   (lot en erreur, lot complémentaire, gros lot) se font aussi en recette.
3. **Contrôler les fichiers de paiement antérieurs à la 1.3** : avant le correctif n° 2, deux
   missions de même objet partageaient un nom de fichier. Vérifier le contenu d'un tel fichier avant
   de le redéposer (*Notes de version 1.3* de `DEPLOY-CPANEL.md`).
4. **Si la file passe en `QUEUE_CONNECTION=database`** : remplacer la tâche Cron `queue:work` par la
   ligne qui nomme les trois files (`--queue=paiements,etats,default`, défaut n° 13). En `sync`, rien
   à faire.
5. **Vider le cache des PDF d'OM après la mise à jour** (défauts n° 11 et 16) : supprimer le contenu
   de `storage/app/private/ordres/` (racine du disque `local`).
   Pour un OM couvert par un état ou amendé, la clé a changé et l'ancien fichier n'est plus servi ;
   mais un OM sans état ni amendement garde la même clé, et son PDF en cache afficherait encore
   l'ancien taux journalier (transport compris). Les PDF se régénèrent à la demande.
6. **Sauvegarder `storage/` avec la base** : `settings.json`, les paraphes et les fichiers produits
   ne sont pas dans le dump (`DEPLOY-CPANEL.md` §9.1).

### Dette restante (hors périmètre de la revue)

- Tests frontend des écrans `MissionCreateView`, `EtatDetailView`, `SignaturesView` et `RolesView` (§2).
- Points « non testés » du §4 (minuteurs, page de maintenance, garde de route au rechargement,
  verrou `lockForUpdate` sous MySQL) : corrigés, mais sans test automatisé.
- ~~Tailles indicatives de `vendor/` dans `UPLOAD-VENDOR.md`~~ : **retirées** le 23/10 (#148). Elles ne se
  vérifiaient pas et dépendent du mode d'installation ; le contrôle des dossiers `.git` reste le garde-fou.
- ~~Code mort : `StoreOrdreMissionRequest` et `UpdateOrdreMissionRequest`~~ — **supprimés** le 23/10 :
  aucune route ni aucun test ne les utilisait (les OM sont retranscrits par `StoreMissionRequest`) et
  ils visaient une table `taux_per_diems` disparue.
- `docs/deploiement-circuit-validation.md` et `docs/plan-validation-daf-dg.md` (juillet-septembre
  2026) décrivent le circuit DAF/DG par OM, retiré depuis. Ils sont désormais marqués **historiques**,
  avec, en tête, ce qui reste valable (migrations, `ComptesCircuitSeeder`, arbitrages client) et ce
  qui est obsolète (drapeau `WORKFLOW_DOUBLE_VALIDATION`, contrôles sur l'OM).
- Non rejouables sans MySQL, et couverts seulement par Pest : restauration (n° 10), tri des
  sauvegardes (n° 14, `db:backup` exige `mysqldump`). L'échec d'une génération en file (n° 12) n'est
  pas reproductible en `QUEUE_CONNECTION=sync` sans provoquer une panne ; il est couvert par Pest.

## 7. Point restant et cartographie du restant (25/10/2026)

État : les 32 défauts relevés sont corrigés et testés. Les quatre décisions ont été prises le
26/10/2026 et appliquées (§7.1) ; restent une dette technique connue et les gestes d'exploitation
du §6.

### 7.1 Décisions (prises le 26/10/2026)

| # | Décision retenue | Mise en œuvre | Vérification |
|---|------------------|---------------|--------------|
| D1 | (b) notifier selon les **droits effectifs** | `NotificationService::notifier` : comptes actifs dont les permissions effectives portent la clé de l'étape (table dans `docs/rbac-flexible.md`, *Limites connues*) ; SUPER_ADMIN exclu, une notification par compte. Destinataires inchangés avec la matrice par défaut. | 5 tests dans `NotificationTest` (rôle personnalisé, droit accordé, droit retiré, super admin, doublon) |
| D2 | (b) `Africa/Porto-Novo`, **avec reprise** des horodatages | `config/app.php` (`APP_TIMEZONE`) ; migration `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif` (+1 h sur toutes les colonnes date-heure, `down()` −1 h), éprouvée sur MariaDB. L'API sérialise toujours en UTC. Déploiement en maintenance, caches purgés avant `migrate` (`DEPLOY-CPANEL.md`, *Notes de version 1.4*). | `FuseauHoraireTest` (4 tests : fuseau, rapport du 1er du mois à 0 h 30, sérialisation, migration aller-retour) |
| D3 | (b) retirer les dépendances | `npm uninstall @vueuse/core lucide-vue-next vue-pdf-embed` (pdfjs-dist et @napi-rs/canvas partent avec) ; Vue reste en `3.6.0-beta.9`. | type-check, lint, build, Vitest |
| D4 | (b) rafraîchir dans le `catch` | `PaiementGeneration.vue` recharge le lot après l'échec d'une génération. | `paiements-async.spec.ts` (lot passé en ERREUR : plus de « Confirmer ») |

Énoncé initial des décisions (pour mémoire) :

| # | Sujet | Constat dans le code | Options | Impact |
|---|-------|----------------------|---------|--------|
| D1 | **Destinataires des notifications** | `NotificationService::notifierRole` vise les comptes actifs des **profils système**. Un rôle personnalisé, ou un droit accordé individuellement (`user_permissions`), autorise l'action sans être notifié (`docs/rbac-flexible.md`, *Limites connues*). | (a) statu quo documenté ; (b) notifier selon les **droits effectifs** (`User::permissions()`). | (b) : requête par permission, et tests à revoir dans `NotificationTest`. |
| D2 | **Fuseau horaire du serveur** | `config/app.php` est en UTC ; l'écran calcule à l'heure du Bénin (UTC+1). Un paiement confirmé entre 23 h et minuit, heure du Bénin, tombe le lendemain dans les rapports par mois et dans `date_paiement` (`docs/annee-fiscale.md`, *Fuseau horaire*). | (a) statu quo ; (b) passer à `Africa/Porto-Novo`. | (b) : reprise des horodatages existants (décalage d'une heure) et revue des tests de dates. |
| D3 | **Dépendances frontend inutilisées** | `vue-pdf-embed`, `lucide-vue-next` et `@vueuse/core` sont déclarées dans `package.json`, mais aucun fichier ne les importe. | (a) les garder ; (b) les retirer (`npm uninstall`, nouveau `package-lock.json`). | (b) : build plus léger ; CI à rejouer, la bêta Vue étant figée par le lock. |
| D4 | **Rafraîchir l'écran après l'échec d'un gros lot en `sync`** | Si un lot `GENERE` existait déjà pour la même clé, `failed()` le passe en `ERREUR`, mais `PaiementGeneration.vue` ne recharge pas la liste. « Confirmer » reste alors affiché à tort (`backend/docs/queues.md`). | (a) consigne « recharger la page » (actuelle) ; (b) rafraîchir dans le `catch`. | (b) : une ligne et un test Vitest. |

### 7.2 Dette technique connue

Ces points reprennent le §6, *Dette restante*, complété le 25/10.

- **Tests frontend manquants** : `MissionCreateView`, `EtatDetailView`, `SignaturesView`, `RolesView`.
- **Correctifs sans test automatisé** : minuteurs, page de maintenance, garde de route au
  rechargement, `lockForUpdate` sous MySQL.
- **Non exercé sur un vrai MySQL** : restauration (n° 10) et tri des sauvegardes (n° 14) ne sont
  couverts que par Pest, sur SQLite ou par simulation.
- **Forme des réponses non vérifiée en CI** : `OpenApiTest` contrôle les routes et les permissions.
  La conformité des réponses aux schémas n'a été vérifiée qu'à la main, le 25/10, avec
  `openapi-core`. Une vérification en CI (test Pest qui valide les réponses, ou étape Python)
  éviterait une nouvelle dérive.
- **Recette et rejeu de la démo hors dépôt** : les scripts de recette (83 contrôles) et de rejeu
  ne sont pas versionnés. Seuls Pest, Vitest et Playwright tournent en CI.

### 7.3 Cartographie de la documentation

« Confronté au code » : relu contre les routes, les services, la configuration et les tests, avec
correction des écarts. La date et la PR sont celles de la dernière relecture.

| Document | État | Dernière relecture | Reste à faire |
|----------|------|--------------------|---------------|
| `README.md`, `CLAUDE.md`, `AGENTS.md`, `CHANGELOG.md` | Confrontés | 23-25/10 (#153, #162) | — |
| `DEPLOY-CPANEL.md`, `UPLOAD-VENDOR.md`, `docs/runbook-mise-a-jour-cron.md`, `docs/checklist-post-deploiement.md` | Confrontés | 24-25/10 (#157 à #161) | — |
| `docs/circuits-validation.md`, `docs/amendement-om.md`, `docs/rbac-flexible.md`, `docs/workflows-par-profil.md` | Confrontés | 23-24/10 (#154 à #157) | — |
| `docs/parametrage-part-avance.md`, `docs/annee-fiscale.md`, `docs/categories-indice.md` | Confrontés | 24/10 (#157) | — |
| `docs/SCENARIO-DEMO.md` | Rejoué sur l'API | 23/10 (#154) | — |
| `backend/docs/queues.md`, `backend/docs/openapi.yaml` | Confrontés ; réponses validées | 25/10 (#160, #161) | Validation des réponses en CI (§7.2) |
| `docs/deploiement-circuit-validation.md`, `docs/plan-validation-daf-dg.md` | **Historiques**, marqués comme tels | 25/10 et 23/10 | Rien, sauf décision de les archiver |
| `docs/Guide-Utilisateur-ANaTT.docx` | Confronté à l’interface, régénéré | 26/10 (#163, n° 1 à 32) | — |
| `docs/ANaTT_Documentation_Technique_Fonctionnelle.docx` | Confrontée, régénérée | 26/10 (#162, n° 1 à 32) | — |
| `docs/00_galerie_index.html` … `53_moov.html` (galerie + 53 pages) | **Refaites** à partir des captures de l'application réelle | 26/10 (`docs/capturer_ecrans.cjs`, `docs/generer_maquettes.py`) | 46 écrans capturés (build de production, données de démonstration) ; les 41 anciens noms sont conservés, ceux sans écran propre renvoient vers l'écran qui assure la fonction ; 12 pages nouvelles pour les écrans sans maquette. Les maquettes d'août 2026 restent dans l'historique git |
| `backend/README.md`, `frontend/README.md` | Réécrits pour le projet, confrontés au code | 26/10 (#165) | — |
| `backend/.env.example`, `backend/.env.production.example` | Relu pour le déploiement | 25/10 (#159) | — |
| `.github/workflows/ci.yml` | Exécuté sur chaque PR | — | — |

### 7.4 Exploitation

Les six gestes du §6, *À faire côté exploitation*, restent à faire sur l'instance en ligne. La
mise à jour couvre désormais les correctifs n° 1 à 32, sans autre migration que celle de la 1.3.

