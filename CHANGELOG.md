# Changelog

Toutes les modifications notables de l'application ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money.

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Versionnement : dates ISO (l'application n'a pas encore de release taguée).

---

## [Non publié]

### Guide du paramétrage de la part d'avance (29/10/2026)

- `docs/parametrage-part-avance.md` : refus d'une part hors bornes affiché **une seule fois** (avertissement
  global et écran donnent le même motif, #188), champ vidé refusé lui aussi, correction puis nouvel
  « Enregistrer tout » ; test `refus-motif.spec.ts` ; historique (#185, #188).

### Circuit de validation : motif d'un refus affiché (28/10/2026)

- **Écart corrigé (tous les écrans, circuit en tête)** : un refus du serveur (422) n'affichait que
  « Donnees invalides. » — au DAF qui avait contrôlé l'état (séparation des tâches), au DG sans numéro de
  registre, à un numéro déjà attribué. `getErrorMessage` et l'avertissement global d'un 422 donnent désormais
  le premier motif de `errors` ; un même message n'est plus affiché deux fois (avertissement global repris
  par l'écran). Test `refus-motif.spec.ts` (vu en échec avant) ; Vitest 210.
- **Écart corrigé (API)** : sans fichier de traduction, une règle de validation générique (`required`,
  `unique`, `min`…) renvoyait sa **clé brute** (« validation.unique ») en production (`APP_LOCALE=fr`,
  `APP_FALLBACK_LOCALE=fr`) — désormais affichée comme motif. Messages français dans
  `resources/lang/fr/validation.php` (déployé avec `resources/`, aucune étape de plus) : « Cette adresse
  e-mail est déjà utilisée. », « Le champ motif doit contenir au moins 10 caractères. ».
  Test `ValidationFrancaisTest` (vu en échec avant) ; Pest 690.
- **Guide des circuits de validation** : §1 « Refus expliqués » (motifs du circuit), en-tête, références (§8).
- **Guide de l'amendement d'un OM** : révision du 28/10 « refus expliqués », §4.3 refus à l'écran (motif de
  chaque garde), références (§8).

### Paramètres : motif d'un refus affiché ; guide de la part d'avance (28/10/2026)

- **Écart corrigé (écran Paramètres)** : un réglage refusé par le serveur (part d'avance à 120 %, durée de
  session hors bornes…) n'affichait que « Donnees invalides. », sans dire lequel corriger ; l'écran affiche
  désormais le motif (`errors`), comme l'écran Messagerie. Test `settings-refus.spec.ts` (vu en échec avant) ;
  Vitest 206.
- **Guide de la part d'avance** (`docs/parametrage-part-avance.md`) : « Enregistrer tout » enregistre et
  applique aussi les autres onglets (à relire), refus expliqué, `settings.json` porte les réglages appliqués ;
  tests et historique.
- **Guide utilisateur** (`.docx` régénéré) : nouveautés 1.4 « Refus expliqués » et « E-mail qui ne part pas » ;
  §7.2 signature enregistrée même si l'e-mail ne part pas, type de notification coupé ; §9.5 alerte du
  trop-perçu ; §13.2 refus expliqué et encadré « Enregistrer tout » (relire les autres onglets).
- **Scénario de démo** : §8.5 refus expliqué d'une part à 120 % et bouton « Enregistrer tout » ; §8.6 variante —
  validation qui aboutit malgré un serveur de messagerie injoignable (cloche reçue, seul l'e-mail manque).
  Rejoué sur l'API réelle, base neuve, `QUEUE_CONNECTION=sync`.
- **README** (racine, `backend/`) : refus expliqués et « Enregistrer tout » ; un e-mail en échec n'empêche jamais
  l'action qui notifie (exploitation en `sync`, e-mail de test après une modification de *Messagerie*) ;
  variantes du scénario de démo.
- **Checklist post-déploiement** (§06) : refus expliqué de *Paramètres* (part à 120 %, rien d'enregistré) ;
  sur la recette, action qui aboutit malgré un serveur de messagerie injoignable (cloche reçue, échec au journal).
- **Runbook de mise à jour** : encadré « Réglages appliqués » (e-mail en échec sans effet sur l'action, refus
  expliqués) ; étape 1, serveur SMTP faux révélé par le seul e-mail de test ; étape 8, « Enregistrer tout »
  applique tous les onglets, refus expliqué, trace d'un échec d'envoi dans le journal du jour.
- **Guide des workflows par profil** : §2, « Enregistrer tout » et refus expliqués (SUPER_ADMIN), serveur de
  messagerie faux révélé par le seul e-mail de test ; §8, une signature n'est jamais bloquée par un e-mail
  qui ne part pas (cloche reçue).

### E-mail de notification en échec : la signature ne répond plus 500 (28/10/2026)

- **Écart corrigé** : avec `QUEUE_CONNECTION=sync` (production mutualisée), un e-mail de notification qui ne
  part pas (serveur SMTP injoignable, mal saisi dans *Messagerie*) faisait répondre **500** à la requête qui
  notifie — validation d'un OM, signature ou rejet d'un état, confirmation d'un lot — alors que l'opération était
  enregistrée ; les destinataires suivants n'étaient pas notifiés (cloche comprise). `SendNotification` relançait
  l'exception après l'avoir journalisée, contrairement à `backend/docs/queues.md`. Elle est désormais journalisée
  seulement. Trouvé en relisant `docs/circuits-validation.md` ; risque accru depuis la configuration SMTP à l'écran.
  Test `MessagerieSmtpTest` (vu en échec avant) ; Pest 671.
- **Circuits de validation** (`docs/circuits-validation.md`) : réglages qui touchent les circuits — notification
  coupée, e-mails par le serveur de *Messagerie* (un échec n'annule pas la signature), mode maintenance (§1) ;
  opérateur fermé à la porte de paiement (§3) ; références (§8).
- **Amendement d'un OM** (`docs/amendement-om.md`) : révision du 28/10 — un e-mail de trop-perçu en échec ne fait
  plus répondre 500 à l'amendement (même correctif), notification `trop_percu_cree` coupable, mode maintenance ;
  §4.4 et références (§8).

### Configuration SMTP de l'écran Messagerie appliquée (28/10/2026)

- **Écran Messagerie** (`/admin/email`) : la configuration SMTP, jusque-là « indicative », est **appliquée à
  l'envoi** (`App\Support\Messagerie::appliquer()`, appelé par `SendNotification`, le lien de réinitialisation
  et l'e-mail de test) :
  - **serveur renseigné** : il remplace le transport du `.env` (`MAIL_MAILER` / `MAIL_*`) ; chiffrement `TLS`
    (STARTTLS exigé), `SSL` (TLS implicite) ou `Aucun` ; mot de passe déchiffré au moment d'envoyer seulement ;
  - **serveur vide** ou jamais saisi : le `.env` s'applique, comme avant ; **expéditeur** renseigné : il remplace
    `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` (champ « Nom d'expéditeur » ajouté à l'écran) ;
  - relu à chaque envoi : un worker de file en cours prend le changement dès l'e-mail suivant.
- **Valeurs validées** à l'écriture (422) : nom d'hôte, port 1-65535, chiffrement, adresse d'expéditeur valide.
  L'écran affiche désormais le refus du serveur (il le taisait).
- **E-mail de test** : `POST /api/admin/settings/test-email` (`parametres.gerer`) écrit au compte connecté avec
  la configuration enregistrée ; un échec répond 422 avec le message du serveur SMTP. Bouton « Envoyer un e-mail
  de test » (enregistre puis teste).
- À la mise à jour : un serveur déjà saisi à l'écran **s'applique** désormais — le vérifier (checklist
  post-déploiement). `MessagerieSmtpTest` (18 tests, vus en échec avant) ; Vitest `admin-email-backup.spec.ts`.
  Compteurs : Pest 670 / 75 fichiers, Vitest 197.
- **Écart corrigé (SPA) : déconnexion sur erreur passagère.** Au rechargement d'une page, toute erreur de
  `/auth/me` (429 au-delà de 60 requêtes/min, 500, réseau) déconnectait l'utilisateur — et `logout()` **révoquait**
  son jeton côté serveur. Seul un 401 met désormais fin à la session ; sinon le jeton est gardé et l'écran d'erreur
  s'ouvre, son bouton « Réessayer » revenant à la page demandée (503 : écran Maintenance). Accents de l'écran 500
  rétablis. `session-erreur-passagere.spec.ts` (8 tests, vus en échec avant) ; Vitest 205.
- **Guide utilisateur** (`.docx` régénéré) : nouveau §3.4 « Erreur momentanée du serveur, maintenance »
  (session conservée, « Réessayer » ramène à la page demandée), nouveauté 1.4 « Erreur passagère », capture de
  l'écran Configuration e-mail au §13.2.
- **Scénario de démo** : variantes §8.6 « configuration e-mail » et §8.7 « mode maintenance, erreur passagère »,
  relevées sur l'API réelle (base neuve) ; réglages de la messagerie hors sauvegarde (§8.4).
- **Écart corrigé (écran Messagerie)** : un refus du serveur (port invalide, échec de l'e-mail de test)
  s'affichait « Donnees invalides. », message générique des 422 ; l'écran affiche désormais le motif
  (`errors`), p. ex. « Échec de l'envoi : Connection could not be established… ». Constaté en rejouant le
  scénario ; le test Vitest simulait une réponse que l'API ne renvoie pas — il suit désormais la vraie forme.
- **Documentation technique** (`.docx` régénéré) : §3.1 session restaurée (seul un 401 y met fin), §6.3 secret SMTP
  déchiffré à l'envoi et session, §7.5 routes `settings/test-email` et `parametres/application`, §7.6 forme des
  réponses 422 (message générique + `errors`) et code 503, §10 recette du 28/10, évolution « Messagerie, refus ».
- **README** (racine, `backend/`, `frontend/`) : réglages de Paramètres et de Messagerie appliqués, session
  conservée sur erreur passagère, secret SMTP, forme des 422 ; compteurs Pest 670 / 75 fichiers (la section
  *Tests* et `backend/README.md` indiquaient encore 652 / 74) ; scénario de démo §8.6-8.7 ; galerie des écrans
  réels (`frontend/README.md` parlait encore des maquettes d'origine).
- **Checklist post-déploiement** : §05 routes `/admin/email` et `/500?redirect=` (13 routes) ; §06 refus
  expliqués de l'écran Messagerie, session conservée au rechargement ; en recette, échec d'envoi expliqué et
  erreur passagère simulée (requête `/api/auth/me` bloquée dans le navigateur).
- **Runbook de mise à jour** : encadré « Réglages appliqués (28/10) » ; étape 1, relire `settings.json` avant
  l'envoi du code (HTTPS forcé, maintenance, serveur SMTP, opérateur fermé, session : les valeurs déjà
  enregistrées s'appliquent aussitôt) ; étape 8, e-mail de test et session au rechargement ; retour arrière.
  `DEPLOY-CPANEL.md` (notes 1.4) corrigé : il disait que rien ne changeait tant que les réglages n'étaient
  pas enregistrés, alors que les valeurs déjà présentes s'appliquent.
- **Workflows par profil** (`docs/workflows-par-profil.md`) : réglages du SUPER_ADMIN et leur effet sur les
  autres profils (§2, tableau) ; session, erreur passagère et maintenance pour tous les profils (§7) ;
  e-mails envoyés par le serveur de *Messagerie* (§8).
- **Galerie des écrans recapturée** (`docs/ecrans/`, pages `docs/*.html`) : écran Messagerie avec sa
  configuration appliquée et le bouton d'e-mail de test. `capturer_ecrans.cjs` reconnecte le compte et
  recommence la capture quand une page retombe sur la connexion (limite de 60 requêtes/min par compte :
  l'écran Sauvegardes avait été capturé sur la page de connexion).

### Réglages de Paramètres appliqués, écrans testés, contrat OpenAPI en CI (28/10/2026)

- **Réglages de l'écran Paramètres appliqués par le serveur** (#175) — enregistrés jusque-là sans effet
  (« indicatifs ») ; absents de `settings.json`, ils gardent le comportement d'avant, hors domaine → 422 :
  - **MTN / Moov** : opérateur fermé → génération refusée (422, avant toute mise en file) ; l'aperçu renvoie
    `operateur_actif`, l'écran affiche un bandeau et grise le bouton ; lots déjà générés confirmables ;
  - **tentatives de connexion** (3-20/min, défaut 5) : limiteur du login ;
  - **durée de session** (5-1440 min) : remplace `SANCTUM_EXPIRATION` (callback Sanctum) ;
  - **forcer HTTPS** (défaut non) : middleware `ForcerHttps`, lectures redirigées (301), écritures refusées (403) ;
  - **journaliser les connexions** (défaut non) : échecs tracés (`LOGIN_ECHEC`) ;
  - **mode maintenance** : middleware `MaintenanceApplication`, 503 pour tous sauf le SUPER_ADMIN ;
  - **notifications** par type : un type coupé n'est plus émis (cloche ni e-mail) ;
  - **nom de l'application** : titre de l'onglet (route publique `GET /admin/parametres/application`) et préfixe
    de l'objet des e-mails. `ReglagesExploitationTest` (20 tests, vus en échec avant).
- **Écrans critiques testés** (#175, dette §7.2) : `MissionCreateView`, `EtatDetailView`, `SignaturesView`,
  `RolesView` — 45 tests Vitest. **Écart corrigé** : un même agent ajouté deux fois n'était signalé qu'au 422
  de l'enregistrement ; l'assistant le signale désormais sur la fiche et bloque l'étape.
- **Contrat OpenAPI vérifié en CI** (#175) : `OpenApiReponsesTest` (26 tests) valide les réponses réelles d'une
  trentaine de routes contre `openapi.yaml`, en mode strict (validateur `tests/Support/OpenApiSchema.php`,
  `composer.lock` inchangé) ; `openapi.yaml` complété (pagination Laravel, régularisations, `LignePerDiem`,
  `Signataire`, enveloppe `message`, `Apercu`, nouvelle route).
- **Écart corrigé (SPA)** : une limite de requêtes atteinte (429 : connexion au-delà des tentatives réglées,
  exports) affichait le message anglais du serveur (« Too Many Attempts. ») ; `getErrorMessage` le traduit.
  Test `erreur-429.spec.ts`, vu en échec avant.
- Documentation finale : `CLAUDE.md` (§1 notifications, §5 ADM-04, §7 réglages d'exploitation et nouvelle
  route), `AGENTS.md` (règle des réglages, contrat OpenAPI), README et compteurs (Pest 652 / 74 fichiers,
  Vitest 194), `DEPLOY-CPANEL.md` (note *Réglages de Paramètres*, précaution HTTPS, quatre lignes de dépannage),
  checklist post-déploiement (§06), `docs/workflows-par-profil.md` §8 et `docs/rbac-flexible.md` (notifications
  coupables), guide utilisateur (§13.2 : tableau des réglages ; §6.1 ; nouveautés) et documentation technique
  régénérés, revue (#175, #176 ; dette §7.2 levée pour les tests d'écrans et le contrat d'API).
- Galerie des écrans réels **recapturée** (`docs/capturer_ecrans.cjs`, données de démonstration, build de production)
  et pages régénérées (`docs/generer_maquettes.py`) : écran *Paramètres* accentué, sans mention « indicatif ».

### Pourcentages et montants alignés sur le réglage de la part d'avance ; thème vert confirmé (27/10/2026)

- **Écart corrigé (SPA + API)** : l'écran d'un état de paiement (« Avances (70 %) », montant de la tranche) et le
  sélecteur de tranche de l'écran de génération affichaient la part **courante**, même pour une mission
  retranscrite avec une autre part : montants justes, pourcentage faux. Nouveau `Mission::partAvanceReelle()`
  (Σ avances / Σ montants des OM, hors rejetés / annulés et hors OM amendés à avance engagée, même règle que
  `libellesRepartition`), exposé en `part_avance` sur `GET /missions/:id/etat`, le récapitulatif et
  `GET /paiements/lots/previsualiser` (pas sur la file `GET /etats`, pour éviter une requête par ligne) ; les
  deux écrans l'affichent, repli sur le réglage pour une mission exonérée. OpenAPI à jour.
- **Vérification de la chaîne des montants** : `ChaineMontantsPartAvanceTest` retranscrit une mission à 55 % et
  contrôle OM, versements, état des avances, aperçus MTN / Moov et état des reliquats, puis passe le réglage à
  70 % sans effet sur la mission. Calcul serveur et aperçu de saisie (`stores/ordres-mission.ts`) relus : même
  formule, même arrondi ; aucune part codée en dur hors défaut (70) et factory de test.
- Tests vus en échec avant le correctif : `PartAvanceReelleTest` (6), `part-avance-reelle.spec.ts` (2 sur 4).
  Pest 606 (72 fichiers), Vitest 139, Playwright 3.
- **Règle n° 6 (thème) tranchée** : l'application de gestion reste au **vert ANaTT** ; la règle de `CLAUDE.md` est
  réécrite en ce sens et les couleurs `app.primary` / `app.sidebar`, jamais utilisées, sont retirées de
  `tailwind.config.js` (build inchangé). Le bleu standard reste une couleur d'accent (badges, bandeaux).
- `docs/parametrage-part-avance.md` : la limite relevée la veille est levée (section *Libellés*, fichiers, tests).
- **Guide utilisateur** (`docs/generer_guide_utilisateur.py`, `.docx` régénéré) : nouveauté 1.4 « Part de l'avance
  d'une mission » ; §7.1 (onglets de l'état), §8 (une mission saisie garde ses montants et sa part), §9.1 (tranche
  libellée avec la part de la mission) et §13 (Paramètres, onglet Général : la part ne vaut que pour les OM saisis ensuite).
- **Scénario de démo** (`docs/SCENARIO-DEMO.md`) : variante **§8.5 « Changer la part de l'avance »**, rejouée sur l'API
  réelle (base neuve) — réglage passé à 60 % : la mission déjà saisie garde 199 500 / 85 500 et « Avances (70 %) », une
  nouvelle mission est répartie 171 000 / 114 000 et affiche 60 % (état, aperçu du lot MTN à 171 000) ; étape C : les
  onglets de l'état portent la part de la mission.
- **README** : répartition figée à la retranscription et part de la mission affichée sur ses écrans ; thème vert
  ANaTT dans la stack ; variante §8.5 du scénario de démo.

### Guide du paramétrage de la part d'avance — version 1.4 (27/10/2026)

- **Écart corrigé** : `docs/parametrage-part-avance.md` affirmait que tous les libellés à l'écran suivent la
  part **courante**. Depuis la 1.4, la fiche et l'aperçu de l'OM la tirent des **montants de l'OM**, comme le
  PDF (`utils/repartition.ts`) ; le guide distingue désormais ce qui reste à créer (part courante) de ce qui
  décrit un OM existant (part de l'OM), avec fichiers, tests (`repartition.spec.ts`) et PR #167.
- **Limite relevée, non corrigée** : l'écran d'un état de paiement titre ses tranches avec la part courante
  (« Avances (70 %) ») ; montants justes, pourcentage du titre faux si la part a changé depuis la
  retranscription. Le PDF de l'état n'imprime pas ce pourcentage.
- Chemin de l'écran corrigé : menu *Système* › Paramètres, onglet *Général*.

### Guide de l'amendement d'un OM — version 1.4 (27/10/2026)

- `docs/amendement-om.md` confronté au code (méthodes, migrations, tests cités : tous présents). Ajouts 1.4 :
  - en-tête *Révisé (version 1.4)* : libellés de la fiche et de l'aperçu de l'OM alignés sur le PDF
    (`utils/repartition.ts`), notification par droits effectifs, heure du Bénin ;
  - **Écart corrigé** §4.4 : la notification du trop-perçu était décrite comme adressée à « l'agent
    financier » ; elle vise les détenteurs effectifs de `paiements.confirmer` (décision D1) ;
  - §4.6 : libellés à l'écran (miroir et tests) ; cache des PDF à vider à une mise à jour de version ;
  - §8 : références `utils/repartition.ts` et `NotificationTest`.

### Guide des circuits de validation — version 1.4 (27/10/2026)

- **Écart corrigé** : `docs/circuits-validation.md` §7 annonçait « trois cases de signature » sur le PDF de
  l'OM ; le gabarit (`ordre-mission.blade.php`) en porte **quatre** (bénéficiaire, contrôle, visa DAF,
  validation DG). Il affirmait aussi que le cache des PDF se renouvelle « sans purge manuelle » : vrai pour
  les signatures et les amendements, pas pour une mise à jour de version (clé sans version : purge en 1.3
  et en 1.4, cf. runbook).
- §1 : ligne *Notifications* par circuit (permission visée à chaque étape, droits effectifs, décision D1) ;
  §7 : date et heure de signature imprimées à l'heure du Bénin ; §8 : `NotificationService` et
  `NotificationTest` ajoutés, toutes les références (classes, méthodes, tests) vérifiées.

### Guide des workflows par profil — version 1.4 (27/10/2026)

- **Écart corrigé** : `docs/workflows-par-profil.md` §8 décrivait encore des notifications « par profil »
  (rôle personnalisé et droit individuel jamais notifiés, droit retiré toujours notifié), contraire à la
  décision D1. Section réécrite : permission visée par chaque événement (`NotificationService`), profils
  notifiés avec la matrice par défaut, effet d'une personnalisation, SUPER_ADMIN exclu, une seule
  notification par compte. Même correction au §3 et dans `docs/rbac-flexible.md` (circuit de signature
  d'un rôle personnalisé).
- Matrice (§3) et menu (§7) reconfrontés à `config/profils.php` et à `navigation.ts` : conformes.

### Runbook de mise à jour — version 1.4 (27/10/2026)

- **Écart corrigé (procédure)** : le runbook et `DEPLOY-CPANEL.md` § B bis annonçaient que, en maintenance,
  le SPA affiche « Maintenance en cours ». Or le SPA servi pendant l'opération est encore l'**ancien** (il
  n'est remplacé qu'à l'étape 7), et la prise en charge du 503 n'existe qu'à partir de la 1.4 : lors de la
  mise à jour 1.3 → 1.4, les écrans échouent sans message. Prévenir les utilisateurs ; le contrôle de la
  checklist (§06) se fait dès la mise à jour suivante ou sur la recette.
- `docs/runbook-mise-a-jour-cron.md` : encadré d'en-tête **1.4** ; `APP_TIMEZONE` du `.env` vérifiée avant la
  migration ; horodatage témoin relevé à l'étape 2, contrôlé à l'étape 6 (+1 h en base) et en recette (même
  heure à l'écran) ; `maintenance.log` et `--render` si `vendor/` est réenvoyé (étape 3 bis) ; arrêt de la
  commande 5b = application laissée en maintenance ; sortie de maintenance vérifiée par `/api/auth/me`
  (401, pas 503) ; recette 1.4 en lecture seule ; ménage (3 bis, `maintenance.log`) ; retour arrière :
  caches purgés et `artisan up`, cohérence dump UTC ↔ code 1.3, `migrate:rollback` avant le retour au code.

### Checklist post-déploiement mise à jour — version 1.4 (27/10/2026)

- **Écart corrigé (procédure 1.4)** : le cache des PDF d'OM (`storage/app/private/ordres/`) n'était à vider
  qu'en 1.3. Or, en 1.4, un PDF d'OM resté en cache imprime ses heures de signature en UTC, alors que le PDF
  de l'état, régénéré à chaque téléchargement (`EtatPaiementPdf::telecharger`), les imprime à l'heure du
  Bénin : les deux documents ne concordaient plus. Purge ajoutée aux notes de version 1.4 de
  `DEPLOY-CPANEL.md`, au runbook (« 1.3 et 1.4 ») et à la checklist (§04, plus un contrôle au §06).
- §01 : contrôle de **sortie de maintenance** (`/api/auth/me` en 401, pas 503 ; `/up` répond 200 même en
  maintenance) ; 34 migrations attendues.
- §05 : comportement réel de l'écran `/maintenance` (nouvelle vérification après 30 s, retour à l'accueil
  quand l'API répond, heure de fin seulement avec `?fin=`).
- §06 (1.4) : contrôle « Configuration e-mail » corrigé — il annonçait « plus « Mission soumise » », un modèle
  justement retiré (7 notifications et leurs destinataires) ; contrôle de maintenance à faire pendant la
  fenêtre ; en-tête relu le 27/10.

### UPLOAD-VENDOR.md mis à jour — échange de `vendor/` sous maintenance (27/10/2026)

- **Écart corrigé (procédure)** : entre le renommage de l'ancien `vendor/` et la fin de l'extraction, l'API
  répondait **500** à toute requête. §4 : maintenance posée **avant** par tâche Cron, avec
  `artisan down --render="errors::503"` — un `down` simple a lui-même besoin de `vendor/` pour répondre 503
  (constaté : sans `vendor/`, `/api/*` répond 500 sans `--render`, 503 avec) ; étape 7 : sortie de
  maintenance (`artisan up`, ou fin de la commande de migration du § D) après vérification ; §5 : `/up`
  traverse Laravel même en maintenance et prouve que `vendor/` se charge ; deux lignes de dépannage.
- §0 : versions 1.3 **et 1.4** sans ré-upload (`composer.lock` inchangé depuis la 1.2 ; les dépendances
  retirées en 1.4 sont celles du SPA) ; en-tête relu le 27/10.
- `DEPLOY-CPANEL.md` § B bis : renvoi à `--render` quand la mise à jour renvoie aussi `vendor/`.

### DEPLOY-CPANEL.md mis à jour — mise en maintenance sans SSH (27/10/2026)

- **Écart corrigé (SPA)** : en maintenance (`php artisan down`), l'API répond **503** à toute requête, mais
  rien ne menait à l'écran *Maintenance* (SYS-03) : les écrans échouaient sans message. L'intercepteur de
  `useApi.ts` ouvre désormais l'écran Maintenance, avec retour à la page demandée dès que l'API répond.
  Test `maintenance-503.spec.ts`, vu en échec avant le correctif (Vitest 135).
- `DEPLOY-CPANEL.md` : nouveau § *Mise à jour*, **B bis**, mise en maintenance par tâche Cron (`artisan down`),
  comportement éprouvé (503 JSON, `/up` toujours à 200, commandes `artisan` disponibles) ; la commande de
  migration du § D se termine par `artisan up`, joué seulement si tout a réussi ; repli Terminal ; notes de
  version 1.4 complétées (correctifs d'interface du SPA, documentation) ; `APP_TIMEZONE` au § 5.1 ; chemin
  *Paramètres › Sauvegardes* au § 9.1 ; deux lignes de dépannage (heures décalées, SPA resté en maintenance) ;
  en-tête relu (34 migrations, versions 1.3 et 1.4).
- `docs/runbook-mise-a-jour-cron.md` : étape **3 bis** (maintenance), même commande de migration, contrôle
  du message `Application is now live.`.

### CLAUDE.md mis à jour (27/10/2026)

- §1 : libellés d'un OM amendé sur la fiche et l'aperçu (`utils/repartition.ts`) ; §3 : `utils/repartition`,
  `start-dev.ps1 -Install` (npm ci) ; §5 : descriptions réelles d'ADM-04 (Paramètres) et d'ADM-05 (e-mail).
- §4 « Layout application » confronté aux composants (`AppLayout`, `AppSidebar`, `AppTopBar`) : barre latérale
  `w-64` en dégradé vert, logo `AppLogo` sur carte blanche, profil en bas de barre, barre du haut sticky,
  contenu `ml-64 p-6`. L'ancien schéma (260 px, `bg-app-sidebar`, logo base64 inversé) ne correspondait plus.
- Règle n° 6 (thème bleu de l'app de gestion) : **constat** ajouté, sans modifier la règle — l'application
  est entièrement au vert ANaTT et les couleurs `app.*` ne sont utilisées nulle part ; arbitrage à rendre.

### AGENTS.md mis à jour (27/10/2026)

- Décisions D1–D4 appliquées (plus de « points ouverts » au §7 de la revue) ; `npm ci` obligatoire
  (bêta Vue figée par le lock, script `start-dev.ps1` en ASCII) ; fuseau `Africa/Porto-Novo` et purge des
  caches avant la migration ; piège de `Carbon::setTestNow` en UTC dans les tests ; ajout d'un type de
  notification (`NotificationContenu`, permission, écran e-mail, `NotificationModelesEcranTest`) ;
  `utils/repartition.ts` miroir de `PdfGenerator::libellesRepartition` ; rechargement de l'écran de
  génération (D4) ; `AppAlert` ; captures de la galerie (build de production, `DemoCircuitSeeder`,
  `settings.json`) et figures du guide.

### README mis à jour (27/10/2026)

- `README.md` confronté au dépôt : stack (Vue 3.6 bêta figée par le lock, heure du Bénin), `npm ci` au
  démarrage manuel, notifications selon les droits effectifs, horodatages à l'heure du Bénin, libellés
  d'un OM amendé, mise à jour en version 1.4 (maintenance, caches purgés avant `migrate`), structure
  (`backend/README.md`, `frontend/README.md`, `utils/`), table de la documentation (documentation
  technique v1.4, scénario rejoué, galerie et script de capture).
- Chemin de menu corrigé : la restauration se fait depuis *Paramètres › Sauvegardes* (et non
  *Administration › Sauvegardes*, absent du menu).
- **`start-dev.ps1 -Install`** lançait `npm install` : avec `"vue": "beta"` dans `package.json`, la bêta de
  Vue pouvait avancer à l'insu du développeur, contre la règle du projet (version figée par
  `package-lock.json`). Le script lance désormais `npm ci`.

### Scénario de démonstration mis à jour (27/10/2026)

- `docs/SCENARIO-DEMO.md` **rejoué sur l'API réelle** (base neuve, version 1.4) : fichiers MTN / Moov,
  montants, avenant, document de retenue et lot complémentaire identiques au rejeu du 23/10.
  Vérifiés en plus : la notification reçue par chaque signataire à son tour puis par l'agent financier,
  et la date de paiement enregistrée à l'heure du Bénin.
- Ajouts : préparation de la démo (galerie des écrans, `DemoCircuitSeeder`), notifications du circuit
  (étape C), heure du Bénin et état mensuel (étape F), libellés de la fiche d'un OM amendé (§7),
  écran de génération rechargé après un échec (§8.1). Documentation technique (§10) régénérée.

### Documentation technique mise à jour (27/10/2026)

- `docs/generer_documentation_technique.py` et le `.docx` régénéré, **version 1.4.0** :
  - nouveau chapitre « Évolutions de la version 1.4 » (D1 à D4, correctifs d'interface, maquettes, tests) ;
  - §2.3 structure (`utils/repartition.ts`, galerie des écrans), §3.4 libellés de la fiche OM amendée,
    §4.6 et §6.3 destinataires des notifications selon les droits effectifs ;
  - §9.2 `APP_TIMEZONE`, §9.3 `npm ci` et purge des caches avant `migrate`, nouveau §9.6 « Mise à jour
    d'une instance (version 1.4) » (maintenance, `optimize:clear` → `migrate`, contrôle du décalage) ;
  - §10 : 70 fichiers Pest, `NotificationModelesEcranTest` et `FuseauHoraireTest` parmi les garde-fous ;
    plus de « points ouverts » (les décisions du §7.1 sont appliquées).

### Guide utilisateur mis à jour (27/10/2026)

- `docs/generer_guide_utilisateur.py` et `Guide-Utilisateur-ANaTT.docx` régénéré :
  - **Nouveautés 1.4** : notifications selon les droits réels (D1), heure du Bénin (D2), écran de
    génération rechargé après un échec (D4), libellés de la fiche et de l'aperçu d'un OM amendé,
    notifications listées sur l'écran Configuration e-mail ;
  - chapitres 6.3 (libellés après amendement), 7.2 (destinataires des notifications), 9.1 (plus de
    « rechargez la page »), 11 (rapport mensuel à l'heure du Bénin), 13.2 (fuseau en lecture seule,
    notifications de l'écran e-mail) ;
  - **12 figures** tirées des captures de l'application réelle (`docs/ecrans/`) : connexion, tableau de
    bord, personnel, retranscription, fiche OM, file et détail d'un état, génération MTN,
    régularisations, état mensuel, utilisateurs, matrice des permissions.

### Trois défauts d'interface relevés par les captures (27/10/2026)

- **Bandeau de l'écran Signatures coupé en colonnes.** `AppAlert` est en `flex` : un texte brut
  entrecoupé de `<strong>` devenait une suite de colonnes. Le contenu du slot forme désormais un
  seul bloc (`AppAlert.vue`). Test `app-alert.spec.ts`.
- **Libellés avance / reliquat après un amendement.** La fiche OM et l'aperçu du PDF affichaient
  « Avance (70 %) / Reliquat (30 %) » avec le paramètre courant, même pour un trop-perçu. Ils
  reprennent maintenant la règle du PDF officiel (`utils/repartition.ts`, miroir de
  `PdfGenerator::libellesRepartition`) : pourcentage réel de l'OM, puis « Avance engagée » et
  « Reliquat (ajusté par amendement) » ou « Trop-perçu (à recouvrer par retenue sur salaire) ».
  Tests `repartition.spec.ts` et `mission-detail.spec.ts`.
- **Modèles de notification de l'écran Configuration e-mail.** Ils dataient de l'ancien circuit
  (« Mission soumise », « validé par le chef de service »). L'écran liste les 7 notifications réelles
  de `NotificationContenu`, avec leur sujet et la permission de leurs destinataires.
  `NotificationModelesEcranTest` (Pest) échoue si l'écran et `NotificationContenu` divergent.
- Galerie des écrans recapturée (`docs/ecrans/`).

### Version 1.4 — décisions D1 à D4 (26/10/2026)

Mise à jour d'une instance : *Notes de version 1.4* de `DEPLOY-CPANEL.md` (maintenance obligatoire
pour la migration du fuseau).

- **D1 — Notifications selon les droits effectifs.** `NotificationService::notifier` remplace
  `notifierRole` : une notification vise les comptes actifs dont les permissions effectives portent
  la clé de l'étape (`om.valider` / `om.viser` / `om.approuver`, `paiements.generer`,
  `paiements.confirmer`, `om.retranscrire`, `utilisateurs.gerer`). Rôles personnalisés et droits
  accordés individuellement sont notifiés, un droit retiré ne l'est plus, le SUPER_ADMIN n'est pas
  visé, un compte visé à deux titres n'est notifié qu'une fois. 5 tests (`NotificationTest`).
- **D2 — Heure du Bénin.** `config/app.php` : `timezone` = `APP_TIMEZONE`, défaut
  `Africa/Porto-Novo`. Migration `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif` :
  +1 h sur toutes les colonnes date-heure existantes (même instant, scellés inchangés ; dates seules
  intactes), réversible. Un paiement confirmé le 1er du mois entre 0 h et 1 h compte désormais dans
  ce mois. L'API sérialise toujours en UTC. Procédures de migration : `optimize:clear` **avant**
  `migrate`. 4 tests (`FuseauHoraireTest`) ; migration éprouvée sur MariaDB.
- **D3 — Dépendances frontend inutilisées retirées** : `@vueuse/core`, `lucide-vue-next`,
  `vue-pdf-embed` (et leurs dépendances `pdfjs-dist`, `@napi-rs/canvas`). Vue reste figée en
  `3.6.0-beta.9`.
- **D4 — Écran de génération rechargé après un échec** (`PaiementGeneration.vue`) : un lot passé en
  `ERREUR` par `failed()` n'affiche plus « Confirmer ». 1 test Vitest.
- **Maquettes refaites à partir de l'application réelle.** `docs/capturer_ecrans.cjs` (Playwright)
  fait avancer la mission DEMO-CIRCUIT dans le circuit (état des avances validé, amendement en
  trop-perçu, lot MTN généré) puis capture 46 écrans du build de production (`docs/ecrans/*.jpg`) ;
  `docs/generer_maquettes.py` en tire la galerie et 53 pages à la charte (Sora / DM Sans, logo local,
  flag-bar). Les 41 anciens noms sont conservés ; une ancienne maquette sans écran propre renvoie vers
  l'écran qui assure la fonction ; 12 pages nouvelles couvrent les écrans qui n'avaient pas de
  maquette. `docs/annoter_maquettes.py` est retiré.
- Paramètres : le champ « Fuseau horaire » devient une valeur en lecture seule (réglée sur le serveur).
- Documentation : CLAUDE.md, AGENTS.md, README, `docs/rbac-flexible.md`, `docs/annee-fiscale.md`,
  `backend/docs/queues.md`, `DEPLOY-CPANEL.md`, `docs/runbook-mise-a-jour-cron.md`,
  `docs/checklist-post-deploiement.md`, `docs/revue-projet-2026-10.md` §7.1, documentation technique.


Contenu de la **version 1.3** (revue d'octobre 2026, du 22 au 23/10/2026) en tête ; les entrées
plus anciennes suivent. Rapport détaillé : `docs/revue-projet-2026-10.md`. Mise à jour d'une
instance : *Notes de version 1.3* de `DEPLOY-CPANEL.md` :
- une seule migration, `2026_10_22_090000_add_sequence_to_lots_paiement` (33 migrations en tout) ;
  les correctifs n° 10 à 32 ne touchent que le code (`app/`, seeders, SPA) ;
- `vendor/` inchangé (même `composer.lock`), SPA à redéployer ;
- vider `storage/app/private/ordres/` (PDF d'OM en cache) et supprimer sur le serveur les deux
  Form Requests retirées (voir *Supprimé*).

### Version 1.3 — revue d'octobre 2026

#### Défauts corrigés — index

Numérotation de `docs/revue-projet-2026-10.md` §3, qui donne le détail et la reproduction.
Chaque correctif a un test de non-régression vu en échec sans lui.

| N° | Gravité | Défaut | Rubrique ci-dessous | PR |
|----|---------|--------|---------------------|----|
| 1-8 | 4 bloquants, 4 majeurs | OM rejeté repassé `VALIDE`, collision des fichiers de paiement, amendement ignorant l'avance payée (circuit par OM), 500 HTML sans `Accept`, paraphe non téléversé, mois en UTC, catégorie suggérée fausse, historique OM-08 muet | Corrigé (première passe) | #125 |
| 9 | Mineur | Badge « complémentaire n° N » jamais affiché (`sequence` absent de la ressource) | Corrigé (scénario de démo) | #127 |
| 10 | Majeur | `db:restore` laissait l'ancienne matrice des permissions en cache | Corrigé — restauration et permissions | #133 |
| 11 | Mineur | PDF de l'OM sans les signatures de l'état des avances | Modifié — signatures sur le PDF de l'OM | #136 |
| 12-13 | Majeurs | Échec d'un gros lot sans libérer ses versements ; Cron `queue:work` limité à la file `default` | Corrigé (files d'attente) | #139 |
| 14-15 | Mineurs | Tri des sauvegardes sur la date affichée ; `openapi.yaml` obsolète | Documentation — description OpenAPI | #140 |
| 16 | Majeur | PDF de l'OM servi d'avant l'amendement (cache) et répartition mal libellée | Corrigé (PDF de l'OM amendé) | #142 |
| 17 | Mineur | Dernier amendement mal identifié (même seconde) | Corrigé (rejeu de la recette et de la démo) | #144 |
| 18 | Majeur | PDF officiel de l'OM inaccessible depuis l'interface | Corrigé (guide utilisateur) | #145 |
| 19 | Mineur | OM jamais soldé avec une part d'avance à 0 ou 100 % | Corrigé (part d'avance aux bornes) | #150 |
| 20 | Mineur | Exercice proposé par l'écran Paramètres ignorant le mois de début | Corrigé (exercice proposé par l'écran Paramètres) | #150 |
| 21 | Majeur | Lot complémentaire impossible à générer depuis l'écran | Corrigé (lot complémentaire depuis l'écran) | #152 |
| 22 | Majeur | Amendement accepté sur un reliquat déjà payé (mission sans état) | Corrigé (amendement d'un OM au reliquat payé) | #152 |
| 23 | Mineur | OM au trop-perçu recouvré jamais soldé | Corrigé (solde d'un OM, 23/10/2026) | #154 |
| 24 | Mineur | OM exonéré resté `VALIDE` après validation par OM | Corrigé (solde d'un OM, 23/10/2026) | #154 |
| 25 | Majeur | OM soldé encore amendable (reliquat recréé impayable) | Corrigé (amendement d'un OM soldé, 23/10/2026) | #155 |
| 26 | Mineur | OM jamais soldé quand l'amendement ramène le reliquat à 0 | Corrigé (amendement d'un OM soldé, 23/10/2026) | #155 |
| 27 | Majeur | Notifications et e-mails envoyés aux comptes désactivés | Corrigé (notifications des comptes désactivés, 24/10/2026) | #156 |
| 28 | Majeur | Amendement d'un OM soldé refusé (régression du n° 25 : avenant impossible avec une avance à 100 %) | Corrigé (OM soldé de nouveau amendable, 24/10/2026) | #157 |
| 29 | Mineur | Erreurs de l'API hors 401 (405, 404, 403, 422, 500) rendues en page HTML sans en-tête `Accept` | Corrigé (erreurs de l'API en JSON, 25/10/2026) | #159 |
| 30 | Mineur | Fonction du compte signataire (`*_FONCTION` de `ComptesCircuitSeeder`) jamais imprimée sur l'état | Corrigé (comptes du circuit, 25/10/2026) | #160 |
| 31 | Majeur | `ComptesCircuitSeeder` rétrogradait un super administrateur dont l'e-mail était celui d'un poste | Corrigé (comptes du circuit, 25/10/2026) | #160 |
| 32 | Mineur | `GET/PUT /admin/settings` renvoyait `"data": []` (tableau) sans réglage, au lieu d'un objet | Corrigé (description OpenAPI confrontée aux réponses, 25/10/2026) | #161 |

#### Corrigé (première passe)

- **Revue complète d'octobre 2026 — huit défauts corrigés** (détail et points
  restants : `docs/revue-projet-2026-10.md`). Bloquants reproduits sur l'API réelle :
  un OM **rejeté** avant le récapitulatif redevenait `VALIDE` et **payable** à la
  validation de l'état des avances (`EtatPaiementService::propager` exclut `REJETE`) ;
  deux missions de **même objet** partageaient le même fichier de paiement, si bien que
  le re-téléchargement d'un lot servait celui de l'autre (un dossier par mission,
  `paiements/{mission_id}/`) ; sur le circuit par OM, un amendement après paiement de
  l'avance recalculait 70/30 en ignorant l'avance décaissée (l'avance `GENERE`/`CONFIRME`
  est désormais engagée). Également : 401 JSON au lieu d'une page 500 sur `/api/*` sans
  en-tête `Accept` ; téléversement des paraphes (le FormData partait sérialisé en JSON) ;
  mois courant calculé en UTC (décalé d'un mois au Bénin dans les rapports) ; catégorie
  suggérée fausse quand l'indice est vidé ; historique OM-08 muet (champs et codes
  d'action obsolètes).

#### Modifié (seconde passe)

- **Revue d'octobre 2026, seconde passe — points restants traités** (détail :
  `docs/revue-projet-2026-10.md` §4).
  - **État des reliquats** : il est signé au montant **payable**, c'est-à-dire la somme des
    reliquats positifs. Les trop-perçus sont mentionnés à part sur le PDF.
  - **Lots complémentaires** : nouvelle colonne `lots_paiement.sequence`, avec une migration.
    Un lot confirmé n'est jamais réécrit. Un versement devenu payable ensuite part dans un lot
    de séquence suivante.
  - **Lot en `ERREUR`** : ses versements reviennent à `EN_ATTENTE`, avec les coordonnées
    actuelles de l'agent.
  - **Amendement** : il est refusé tant qu'un fichier contenant l'OM attend son dépôt, et
    les états sont verrouillés pendant l'opération.
  - **OM rejeté** : ses versements passent à `ANNULE`.
  - **Mission** : un même agent ne peut y figurer qu'une fois.
  - **Séparation ordonnateur/payeur** : elle est désormais contrôlée sur l'effectif de
    chaque membre d'un profil.
  - **Sauvegardes** : ajout du téléchargement et de la restauration, avec une sauvegarde
    de sécurité préalable (commande `db:restore`).
  - **Frontend** :
    - suivi des gros lots générés en asynchrone ;
    - part d'avance qui ne reste plus figée à 70 % ;
    - boutons Valider et Rejeter pour le circuit par OM ;
    - messages d'erreur sur la fiche OM et l'aperçu PDF au lieu d'une page blanche ;
    - compte à rebours sur la page de maintenance ;
    - corrections du design system (`flag-bar` sur la connexion, icônes SVG).
  - **Tests** : la suite Pest s'exécute sans `.env` (`APP_KEY` de test). Le binaire
    Chromium de Playwright peut être fourni par variable d'environnement.

#### Corrigé (troisième passe)

- **Pagination de `/api/missions` et `/api/etats`** : `per_page` est désormais plafonné à 100,
  avec une valeur par défaut de 20.
- **Filtre `operateur` de `/api/ordres`** : il porte sur l'opérateur du versement (celui du
  paiement), et non plus sur l'opérateur actuel de l'agent.
- **Exports de rapports** : un suffixe aléatoire est ajouté au nom du fichier, si bien que deux
  exports lancés dans la même seconde ne s'écrasent plus.
- **Calcul du per diem** : un OM sans jour facturé garde son transport. Seule une prise en
  charge par un tiers ramène tous les montants à 0.

#### Corrigé (scénario de démo)

- **Le badge « complémentaire n° N » ne s'affichait jamais** : `LotPaiementResource` n'exposait pas
  `sequence`, la liste des lots ne pouvait donc pas distinguer un lot complémentaire. Repéré en
  rejouant le scénario de démo sur l'API ; test ajouté (`PaiementTest`).

#### Corrigé (files d'attente)

- **Échec d'une génération asynchrone de lot** : `GenererLotPaiementJob::failed()` passait en
  `ERREUR` tous les lots non confirmés de la clé par une mise à jour directe, sans toucher leurs
  versements. Un lot déjà généré se retrouvait en `ERREUR` avec des versements `GENERE` : OM non
  amendable, coordonnées non rafraîchies, aucune trace d'audit. L'échec passe désormais par
  `LotPaiementService::marquerErreur` (versements `EN_ATTENTE`, coordonnées courantes de l'agent,
  audit `ERROR_LOT`) ; un lot `CONFIRME` n'est jamais touché. Deux tests dans `AsyncGenerationTest`.
- **Tâche Cron `queue:work` du mutualisé** (`DEPLOY-CPANEL.md` §9) : lancée sans `--queue`, elle
  ne traitait que la file `default` ; en `QUEUE_CONNECTION=database`, les gros lots (`paiements`) et
  les exports (`etats`) n'étaient jamais exécutés. La ligne nomme désormais les trois files.
- **`backend/docs/queues.md`** réécrit : trois modes (`sync` par défaut en production, `database`,
  `redis`) et leurs différences de reprise, job `SendNotification`, `GenererEtatJob` distingué du
  circuit des états de paiement, règles de génération identiques au mode direct (lot complémentaire),
  comportement à l'échec, sondage du SPA (2 s, 30 sondages « introuvable »), cache partagé exigé,
  `queue:restart` après déploiement, rejeu des jobs échoués.

#### Documentation — description OpenAPI

- **`backend/docs/openapi.yaml` réécrit** : l'ancienne description datait du modèle initial
  (OM saisis à la main, soumission puis validation N1/DG, paiement direct, catégories A1-D) et ignorait
  les missions, les états de paiement, les lots, les régularisations et l'administration (la moitié
  de ses 15 chemins n'existait plus). Nouvelle spécification OpenAPI 3.0 sur les routes
  réelles : authentification et verrou 423, limites de débit, enveloppes de réponse, schémas des
  ressources (OM, mission, état de paiement, lot, versement, régularisation…), corps de requête
  repris des Form Requests, permission de chaque opération (`x-permission`), règles métier
  rappelées là où l'API les applique (gardes d'amendement, séparation des tâches, lots
  complémentaires, 202 des gros lots). Validée par `openapi-spec-validator`.
- **`tests/Feature/OpenApiTest.php`** : chaque route de l'API est décrite, aucune opération n'est
  décrite sans route, la permission annoncée est celle du middleware, et chaque `$ref` est défini.
- **Corrigé** : la liste des sauvegardes (`GET /api/admin/backups`) triait la date affichée
  `d/m/Y` comme une chaîne (le 22/10 avant le 03/11) ; elle trie désormais sur l'horodatage du
  fichier. Test dans `BackupControllerTest`.

#### Documentation — circuits de validation

- **`docs/circuits-validation.md`** mis à jour :
  - tableau des deux circuits complété : permissions de chaque transition, paraphes purgés au
    rejet, signatures affichées sur le PDF de l'OM ;
  - séparation des tâches expliquée (règle, interrupteur `separation_des_taches`, SUPER_ADMIN
    compris, circuit par état seulement) ;
  - un état **rejeté** garde le circuit par OM fermé ; on reprend par un nouveau récapitulatif,
    qui le ramène en `BROUILLON` (nouveau test dans `CoherenceCircuitsTest`) ;
  - nouvelle section §7 « Les signatures sur le PDF de l'OM » : cas par cas, état des avances
    seul repris, clé de cache ;
  - garde d'amendement « versement `GENERE` » : l'échec définitif d'une génération en file
    libère aussi les versements ;
  - références : `PdfGenerator::etatSignataire()`, `backend/docs/openapi.yaml`,
    `OmPdfSignaturesEtatTest`.

#### Corrigé (PDF de l'OM amendé)

- **PDF de l'OM servi d'avant l'amendement** : l'écran télécharge `/api/ordres/:id/pdf/download`,
  qui sert le PDF en cache, et la clé ne dépendait que des statuts (inchangés par un amendement).
  La clé compte désormais les amendements (`…-AMD{n}.pdf`).
- **Répartition affichée** : un reliquat ajusté ou négatif n'apparaît plus comme « Reliquat (30 %) ».
  Libellés « Avance engagée », « Reliquat (ajusté par amendement) » ou « Trop-perçu (à recouvrer par
  retenue sur salaire) » ; pourcentage tiré des montants de l'OM, pas du paramètre courant ; encadré
  rappelant le dernier amendement ; « Montant total dû » en cas de trop-perçu.
- **Taux journalier affiché** : calculé sur le per diem seul (le transport le gonflait).
- `docs/amendement-om.md` : nouvelle §4.6, tableau §3 complété (état des avances `BROUILLON` /
  `REJETE` / en signature), note de la Phase 2 précisée, échec d'une génération en file, références.
  Tests : `OmPdfAmendementTest` (5 cas).

#### Documentation — fin de la mise à niveau (23/10/2026)

- **`docs/revue-projet-2026-10.md`** : état au 23/10 (colonne de résultats, 570 tests), tests ajoutés
  par défaut, tableau des documents relus en seconde étape (PR #132 à #142), PR fusionnées, nouvelles
  suites à donner (cache des PDF d'OM, ligne Cron, `storage/`), dette restante complétée.
- **Exploitation** (`DEPLOY-CPANEL.md` *Notes de version 1.3*, runbook, checklist) : **vider
  `storage/app/private/ordres/`** après la mise à jour — un OM sans état ni amendement garderait sinon
  son ancien PDF en cache ; vérifier la ligne Cron `queue:work` sur les trois files ; contrôles du PDF
  d'un OM avec transport, du PDF après amendement et de l'ordre des sauvegardes.
- **Guide utilisateur** (régénéré), **`docs/SCENARIO-DEMO.md`** §7, **`docs/workflows-par-profil.md`**
  §5 : PDF de l'OM amendé ; liste des sauvegardes de la plus récente à la plus ancienne.
- **`docs/deploiement-circuit-validation.md`** et **`docs/plan-validation-daf-dg.md`** marqués
  historiques, avec ce qui reste valable et ce qui est obsolète.

#### Corrigé (rejeu de la recette et de la démo, 23/10/2026)

- **Dernier amendement mal identifié** : deux amendements dans la même seconde (`amende_le` à la
  seconde) faisaient rappeler le **premier** sur le PDF de l'OM, les listaient dans le désordre sur la
  fiche OM et pouvaient citer le mauvais motif sur le document de retenue. Tri sur `amende_le` puis
  `id`. Trouvé en rejouant le §7 du scénario de démo ; test dans `OmPdfAmendementTest`. Défaut n° 17.
- **Recette sur l'API réelle rejouée** : 83 / 83 (76 contrôles d'origine + 7 sur les correctifs n° 4,
  11 et 16). **Scénario de démo rejoué**, §7 « avenant » compris : montants et fichiers conformes au
  document (`docs/revue-projet-2026-10.md` §1).

#### Corrigé (part d'avance aux bornes, 23/10/2026)

- **OM jamais soldé avec une part d'avance à 100 % (ou à 0 %)**. La tranche nulle garde un
  versement de montant 0, qui n'entre dans aucun fichier et n'est donc jamais confirmé.
  `LotPaiementService::confirmer` exigeait que **tous** les versements soient `CONFIRME` : l'OM
  payé en entier restait `VALIDE`. Les versements nuls sont désormais ignorés pour décider du
  solde. Test dans `SoldeTest` (Pest : 572 tests). Défaut n° 19 de
  `docs/revue-projet-2026-10.md`.

#### Documentation — revue au 23/10/2026, fin de journée

- **`docs/revue-projet-2026-10.md`** mis à jour après la PR #152 :
  - en-tête : défauts n° 10 à 22 et leur origine ;
  - tableau des résultats : CI 5/5 sur les PR #132 à #152 ;
  - §5 et §6 : PR #143 à #152 ;
  - exploitation : aucune migration de plus pour les correctifs n° 17 à 22 ;
  - dette : tailles de `vendor/` retirées.
- **Rejeu sur l'API réelle** (base SQLite neuve, `QUEUE_CONNECTION=sync`) :
  - recette 83/83 ;
  - 9/9 sondes supplémentaires : OM soldé avec une part d'avance à 100 % (n° 19), amendement
    refusé sur un reliquat payé (n° 22) ;
  - scénario de démo conforme : fichiers MTN/Moov, lot complémentaire `HDR,SARALY,1,199500`,
    avenant 171 000 puis −54 000.
- **`AGENTS.md`** :
  - environnement : PHP 8.4.x 64 bits (borne `<8.5` de PhpSpreadsheet), Node 20.19+ / 22.12+,
    Playwright avec le *headless shell* sans écran ;
  - vérifications : Vitest en mode surveillance par défaut, Pest ~573 tests ;
  - prise d'effet immédiate des droits côté serveur ;
  - file `sync` en production ;
  - pièges des correctifs n° 17, 19, 20, 21 et 22 ;
  - Cron `schedule:run`.
- **`CLAUDE.md`** confronté au code (23/10) :
  - stack : Vue **3.6 bêta** (dist-tag `beta` + `overrides`, figée par `package-lock.json` → `npm ci`),
    PHP 8.4.x 64 bits (8.5 non pris en charge), Node 20.19+/22.12+, Telescope local seulement ;
    la « visionneuse VuePDF » n'existait pas (aperçu HTML + PDF serveur) ;
  - comptes de tests (Pest 573 / 68 fichiers, Vitest 120, puis 121 avec le menu accentué), structure (`utils/exercice.ts`, `services/auth.ts`,
    directive `v-permission`) ;
  - API : `GET /api/agents/stats` ajouté ;
  - règles : solde malgré une tranche nulle (n° 19), lot complémentaire proposé (n° 21), amendement
    refusé sur reliquat payé (n° 22), dernier amendement départagé par `id` (n° 17), exercice recalculé (n° 20) ;
  - commandes : `npm ci`, Playwright sans écran, `make:class` au lieu d'un `make:service` inexistant.
- **`README.md`** confronté au code :
  - PHP 8.4.x 64 bits (8.5 non pris en charge : PhpSpreadsheet `<8.5.0`) ;
  - comptes de démonstration seedés seulement en `APP_ENV=local`/`testing` ;
  - `start-dev.ps1 -NoWorker` (2 fenêtres) et `QUEUE_CONNECTION=database` de `.env.example` ;
  - SOLDE malgré une tranche nulle, lot complémentaire proposé, refus d'amendement (reliquat payé,
    trop-perçu recouvré, fichier en attente de dépôt) ;
  - Cron `schedule:run` (sauvegarde + `sanctum:prune-expired`), file `sync` en production ;
  - Playwright sans écran ; structure et tableau de documentation complétés (`AGENTS.md`,
    documents historiques, maquettes HTML).
- **Menu latéral sans accents** (`components/shared/navigation.ts`) : « Etats de paiement »,
  « Generer MTN / Moov », « Regularisations », « Reconciliation », « Baremes », « Trop-percus »,
  « Systeme », « Parametres » et « Matrice des roles », alors que les écrans et la documentation
  portent les accents. Libellés corrigés ; l'entrée de `/admin/roles` reprend le titre de son écran,
  « Matrice des permissions ». Test dans `navigation.spec.ts` (Vitest : 121 tests). Trouvé en
  mettant à jour `docs/SCENARIO-DEMO.md`.
- **`docs/SCENARIO-DEMO.md`** rejoué une troisième fois (base neuve, API réelle : fichiers et
  montants identiques) :
  - écart du transport d'ATCHA dans l'avance : **+7 000** (70 % de 10 000), et non +6 500 ;
  - chemins de menu exacts (`Personnel › Nouvel agent`, `Ordres de Mission › Retranscrire`,
    `Missions` → écran de l'état, `Paiements › Générer MTN`, `Paramètres › Sauvegardes`…) et
    boutons « Établir le récapitulatif des avances / des reliquats » ;
  - comptes de démo seedés seulement en `APP_ENV=local`, base neuve pour rejouer.
  `docs/checklist-post-deploiement.md` : « Matrice des permissions ».
- **`CHANGELOG.md`** relu contre la revue et le code :
  - en-tête : revue du 22 au 23/10, consignes de mise à jour complètes (une seule migration sur 33,
    `storage/app/private/ordres/` à vider, Form Requests à supprimer) ;
  - nouvel **index des défauts n° 1 à 22** (gravité, rubrique, PR) ; n° 17 et 18 repérés dans
    leurs rubriques ;
  - *Vérification* au 23/10 (Pest 573, Vitest 120, recette 83/83 + 9 sondes) au lieu des
    chiffres du 22/10 ;
  - rubrique « Documentation technique (23/10) » renommée : elle couvre tous les documents
    confrontés au code ce jour-là.

#### Corrigé (comptes du circuit, 25/10/2026)

- **Fonction imprimée sous la signature d'un état** (n° 30). Sans fonction saisie dans
  *Paramètres › Signatures*, le libellé par défaut du poste (« Le Directeur Général »…) passait
  avant la fonction du compte signataire. Le repli prévu sur le compte ne jouait donc jamais, et la
  fonction posée par `ComptesCircuitSeeder` (`DG_FONCTION`…) n'était jamais imprimée. Ordre
  rétabli dans `EtatPaiementService::empreinte` : Paramètres, puis compte, puis libellé par défaut.
  `SignatureParametres::pour()` expose la saisie brute (`fonction_saisie`). Les états déjà signés
  gardent leur instantané.
- **Super administrateur protégé par `ComptesCircuitSeeder`** (n° 31). Le seeder alignait le rôle de
  tout compte existant portant l'e-mail d'un poste. Un `DG_EMAIL` égal à celui du super
  administrateur, souvent le seul, le rétrogradait en `DG`, au mépris de la règle « toujours un
  `SUPER_ADMIN` actif ». Un tel compte est désormais laissé tel quel, et le poste est signalé non
  attribué.
- Tests dans `EtatPaiementTest` (3) et `ComptesCircuitSeederTest` (1), les deux défauts vus en échec
  sans correctif. Pest : 586 tests.

#### Documentation — README du backend et du frontend (26/10/2026)

- **`backend/README.md`** et **`frontend/README.md`** : les gabarits d'origine (Laravel, Vite) sont
  remplacés par des README propres au projet, en français. Ils renvoient au `README.md` racine, à
  `CLAUDE.md` et à `AGENTS.md`, et ne gardent que ce qui est spécifique à chaque paquet.
- Contenu, vérifié dans le code :
  - prérequis : PHP 8.4.x 64 bits ; Node 20.19+ ou 22.12+ ; `npm ci` pour la bêta Vue figée ;
  - démarrage et commandes de vérification : Pest (589 tests), Pint, filets RBAC et OpenAPI ; type-check,
    lint, Vitest (123 tests), build, Playwright avec `PLAYWRIGHT_CHROMIUM_EXECUTABLE` ;
  - repères dans le code ;
  - points d'attention (retranscription et génération seules, erreurs JSON, `runtime-config.js` lu par
    `useApi`, alignement menu ↔ routes ↔ API, `moisLocal`, `FormData`, charte).
- `docs/revue-projet-2026-10.md` §7.3 : les deux README ne sont plus à reprendre.

#### Documentation — maquettes HTML (26/10/2026)

- **Les 42 maquettes HTML** (`docs/00_galerie_index.html` … `41_page_maintenance.html`, août 2026) sont
  confrontées au routeur et aux écrans réels. Elles datent d'avant la retranscription SEMO et le circuit
  par état de paiement, et ne sont **pas redessinées** : l'application fait foi.
- Nouveau script `docs/annoter_maquettes.py`, rejouable et idempotent :
  - chaque maquette reçoit un encart fixe avec son état (fidèle, écarts, obsolète), l'écran réel
    (route et vue) et les écarts connus ;
  - la galerie reçoit un tableau de correspondance et la liste des 14 écrans réels sans maquette.
- Constats sur 41 maquettes :
  - 16 fidèles dans l'esprit ;
  - 19 avec écarts : OM retranscrits et mono-bénéficiaires, pas de brouillon ni de « Soumettre pour
    validation », agents désactivés et non supprimés, import tout ou rien, interrupteur « Double
    validation » retiré… ;
  - 6 obsolètes : modification et validation d'un OM, OM en attente, réconciliation par import, import de
    relevé opérateur, marquage « payé » ;
  - un écart commun : la charte n'est pas appliquée (Plus Jakarta Sans, vert `#009639`, icônes
    Font Awesome).
- `CLAUDE.md`, `AGENTS.md` et `docs/revue-projet-2026-10.md` §7.3 mis à jour.

#### Documentation — guide utilisateur (26/10/2026)

- **`docs/Guide-Utilisateur-ANaTT.docx`** confronté aux libellés réels de l'interface (`navigation.ts`,
  `PaiementGeneration.vue`, écran Régularisations), puis régénéré par `generer_guide_utilisateur.py`.
- Corrigé au chapitre 9 :
  - menus « Générer MTN » / « Générer Moov », au lieu de « Paiements MTN / Moov » ;
  - aucun bouton « Prévisualiser » : l'aperçu s'affiche dès la sélection ;
  - boutons « Générer le fichier.txt / .xlsx », « Régénérer le fichier », « Marquer en erreur » et
    « Confirmer le paiement ».
- Corrigé à l'annexe A : `VERIFIE` / `VISA_DAF` sont des valeurs héritées, et non une option
  activable.
- Ajouté :
  - nouveautés (OM soldé, OM soldé amendable, lot complémentaire depuis l'écran, notifications des
    seuls comptes actifs, fonction imprimée, menu accentué) ;
  - solde au recouvrement et à la validation d'un OM exonéré ;
  - destinataires des notifications ;
  - priorité du nom et de la fonction imprimés (§7.4) ;
  - consigne de rechargement après une erreur de génération en production.
- `docs/revue-projet-2026-10.md` §7.3 : le guide n'est plus à reprendre.

#### Documentation — documentation technique (26/10/2026)

- **`docs/ANaTT_Documentation_Technique_Fonctionnelle.docx`** confrontée au code, puis régénérée par
  `generer_documentation_technique.py`.
- Corrigé dans la pile technique :
  - la ligne « VuePDF » est retirée : aucune visionneuse PDF n'est importée ;
  - Vue 3.6 bêta figée par le lockfile ;
  - PHP 8.4.x 64 bits, ni 8.3 ni 8.5 ;
  - `SANCTUM_STATEFUL_DOMAINS` laissée vide (jetons Bearer).
- Ajouté :
  - évolutions n° 19 à 32 ;
  - solde d'un OM (confirmation, recouvrement, amendement, exonération) ;
  - garde du reliquat payé et OM soldé amendable ;
  - destinataires réels des notifications (comptes actifs, profil attendu) ;
  - gros lot en `sync` ;
  - erreurs JSON et 405 ;
  - priorité du nom et de la fonction imprimés ;
  - `ComptesCircuitSeeder` en production ;
  - variables `FRONTEND_URL`, `MTN_COMPTE`, `WORKFLOW_SEPARATION_TACHES`, `CONTROLEUR_*` / `DAF_*` /
    `DG_*` ;
  - volumes de tests (Pest 589, dont 129 unitaires ; Vitest 123) ;
  - validation des réponses OpenAPI.
- `docs/revue-projet-2026-10.md` §7.3 : la documentation technique n'est plus à reprendre.

#### Documentation — AGENTS.md et point restant (25/10/2026)

- **`AGENTS.md`** relu contre le code. Deux affirmations étaient fausses :
  - « le SPA masque *Amender* sur un OM soldé » : il le propose depuis le n° 28 ;
  - « seul le 401 est en JSON » : toutes les erreurs le sont depuis le n° 29.
- Ajouté à `AGENTS.md` :
  - prédicat `$enJson` de `bootstrap/app.php` ;
  - cast `(object)` des tableaux possiblement vides (n° 32) ;
  - comportement des gros lots en `sync` ; `export-async` réservé à l'API ;
  - nouvelle section *Signatures and circuit accounts* (n° 27, 30, 31 ; `fonction` non assignable
    en masse) ;
  - piège `nullable` d'OpenAPI 3.0 ;
  - limite de 5 connexions par minute lors des vérifications sur l'API réelle ;
  - nettoyage de `settings.json` dans les tests.
- **`docs/revue-projet-2026-10.md`** : en-tête et §6 mis au 25/10 (PR #153 à #162). Nouveau §7
  *Point restant et cartographie du restant* :
  - quatre décisions en attente (destinataires des notifications, fuseau horaire, dépendances
    inutilisées, rafraîchissement après l'échec d'un gros lot) ;
  - dette technique ;
  - cartographie de toute la documentation, dont la documentation technique `.docx` à reprendre
    (n° 23 à 32), les 42 maquettes HTML jamais confrontées et les `README` de gabarit ;
  - exploitation.

#### Corrigé (description OpenAPI confrontée aux réponses, 25/10/2026)

- **`GET`/`PUT /admin/settings` renvoyaient un tableau** (n° 32). Sans réglage enregistré, le tableau PHP
  vide sortait en `"data": []` au lieu de l'objet décrit. Une écriture sans clé autorisée gravait aussi
  `[]` dans `settings.json`. `SettingsController` renvoie et écrit désormais un objet (`{}`). Test dans
  `SettingsControllerTest`, vu en échec sans le correctif. Pest : 589 tests.

#### Documentation — description OpenAPI (25/10/2026)

- **`backend/docs/openapi.yaml`** relu contre le code. Le document est validé OpenAPI 3.0.3
  (`openapi-spec-validator`), et les **réponses réelles** sont contrôlées contre les schémas
  (`openapi-core`) sur une base de démonstration :
  - les 38 routes `GET` sans fichier ;
  - un parcours d'écriture complet : récapitulatif, contrôle, visa, numéro, validation, aperçus,
    génération et confirmation MTN/Moov, amendement, régularisations, recouvrement.
- Corrigé :
  - `MissionLigne.etat_statut` / `etat_reliquat_statut` et `data.avance` / `data.reliquat` de
    `GET /missions/{id}/etat` refusaient `null`. En OpenAPI 3.0, `nullable` est sans effet à côté
    d'un `allOf`/`$ref`. Ils passent par une énumération en ligne et une branche `enum: [null]` ;
  - le schéma `Settings` (n° 32).
- Ajouté :
  - erreurs JSON sur toute route `/api/*` (n° 29) ;
  - en `sync`, `POST /paiements/lots/generer` répond 202 seulement si la génération réussit ;
  - solde à la confirmation d'un lot et au recouvrement (n° 23) ;
  - OM exonéré soldé à la validation par OM (n° 24) ;
  - priorité du nom et de la fonction imprimés (n° 30) et notification des seuls comptes actifs
    (n° 27) ;
  - `export-async` n'est employé par aucun écran.

#### Documentation — files d'attente (25/10/2026)

- **`backend/docs/queues.md`** relu contre le code (jobs, `PaiementController`, `RapportController`,
  `PaiementGeneration.vue`). Corrigé : en `sync`, un gros lot en **échec** ne répond pas `202`.
  L'exception remonte dans la réponse du `POST`, après `failed()`. Seule une réussite répond `202`,
  avec un suivi déjà `TERMINE`. Ce comportement est verrouillé par deux tests de
  `AsyncGenerationTest` (Pest : 588 tests).
- Ajouté :
  - `export-async` et `GenererEtatJob` ne sont appelés par aucun écran (l'export du SPA est
    synchrone) ;
  - `SendNotification` intercepte les erreurs SMTP, et en `sync` l'envoi ralentit l'action qui
    notifie ;
  - les comptes désactivés ne reçoivent pas d'e-mail (n° 27) ;
  - les tables `jobs` et `failed_jobs` existent déjà pour `QUEUE_CONNECTION=database` ;
  - après l'échec d'un gros lot en `sync`, l'écran n'est pas rafraîchi.

#### Documentation — circuit de validation, document historique (25/10/2026)

- **`docs/deploiement-circuit-validation.md`** relu contre le code. Le tableau des statuts est
  complété :
  - `users.role` n'est plus un ENUM, depuis la migration du 02/09 ;
  - la procédure par SSH (§3.1-3.2) est inadaptée à cPanel ;
  - l'interrupteur « Double validation » a disparu ;
  - le §6 est **dangereux** : `migrate:rollback --step=3` annulerait aujourd'hui les trois
    dernières migrations.
- Le §3.3, toujours valable, est mis à jour :
  - variables `*_FONCTION` ;
  - lancement par tâche Cron, précédé d'`optimize:clear`, avec suppression du log qui contient les
    mots de passe temporaires ;
  - priorité du nom et de la fonction imprimés (n° 30) et super administrateur protégé (n° 31).
- Contrôles n° 30 et 31 ajoutés à la checklist.

#### Corrigé (erreurs de l'API en JSON, 25/10/2026)

- **Erreurs de l'API en JSON, même sans en-tête `Accept`** (n° 29). Le correctif n° 4 ne couvrait que
  le 401. Une requête `/api/*` sans `Accept: application/json` recevait encore une page HTML pour une
  méthode refusée (405), une route inconnue (404), un accès refusé (403) ou une erreur interne (500),
  et une redirection pour une validation (422). C'est le cas d'un GET sur `/api/auth/login` ouvert dans
  un navigateur, test que fait faire `DEPLOY-CPANEL.md` §5.3, ou d'un outil tiers. Le SPA n'était pas
  touché : axios envoie l'en-tête. Dans `bootstrap/app.php`, tous les rendus d'exception partagent
  désormais le même critère : `expectsJson()` **ou** chemin `api/*`. Test dans `AuthTest`, vu en échec
  sans le correctif (Pest : 582 tests).

#### Documentation — envoi de `vendor/` (25/10/2026)

- **`UPLOAD-VENDOR.md`** relu contre le code. Les comptes de paquets (95 de production, 51 de
  développement) et les bornes PHP (minimum 8.4 pour Symfony 8 et activitylog, `<8.5.0` pour
  PhpSpreadsheet) sont exacts. Un `vendor/` construit avec `--no-dev` a été démarré en production :
  migration et seeders, `/up`, connexion, exports `.xlsx` et `.pdf` fonctionnent.
- Ajouté :
  - le contrôle du 64 bits en local (`php -r "echo PHP_INT_SIZE;"`) ;
  - la vérification du zip, devenue **obligatoire**. Une archive écrite avec des `\` par une
    ancienne version de `Compress-Archive` s'extrait sous Linux en fichiers nommés `vendor\…` à la
    racine de `anatt-api/`. Elle se recrée avec le `tar` de Windows ;
  - le message 500 « 64-bit build » et le symptôme des fichiers `vendor\…` au §5.

#### Documentation — déploiement cPanel (25/10/2026)

- **`DEPLOY-CPANEL.md`** relu contre le code. Restent exacts : les seeders, les extensions PHP, le
  planificateur, la rétention de 30 jours des sauvegardes, les 33 migrations et les caches de
  `bootstrap/cache/`.
- Corrigé :
  - la commande de dépannage `php artisan config:cache route:cache` échouait (« No arguments
    expected ») : elle est remplacée par `php artisan optimize`, avec l'avertissement sur le `.env` ;
  - le GET sur `/api/auth/login` est décrit tel qu'il répond désormais (405 JSON, n° 29).
- Ajouté :
  - PHP **8.4.x 64 bits** aussi en local (§0), ni 8.3 ni 8.5 pour le binaire Cron ;
  - les comptes nominatifs du circuit (`ComptesCircuitSeeder`, Option A) ;
  - `FRONTEND_URL`, `MTN_COMPTE` et `WORKFLOW_SEPARATION_TACHES` au §5.1 ;
  - la vérification de génération décalée sur une base propre ;
  - une ligne *Correctifs n° 17 à 29* dans les *Notes de version 1.3*.

#### Documentation — runbook de mise à jour par Cron (24/10/2026)

- **`docs/runbook-mise-a-jour-cron.md`** relu contre le code. Restent exacts : les 33 migrations (toutes
  avec `down()`), le planificateur de `routes/console.php` et le 401 JSON de `bootstrap/app.php`.
  Ajouté :
  - PHP **8.4.x 64 bits, ni 8.3 ni 8.5**, avec le message 500 « 64-bit build » de
    `platform_check.php` ;
  - un encadré sur les correctifs n° 17 à 28, qui sont du code seul : aucune migration de plus,
    `vendor/` inchangé, SPA à redéployer ;
  - à l'étape 8, deux contrôles en lecture seule : le menu accentué, et « Amender » sur un OM soldé
    au reliquat impayé.

#### Documentation — checklist post-déploiement (24/10/2026)

- **`docs/checklist-post-deploiement.md`** relu contre le code : route de santé `/up`, 401 JSON, CORS,
  routes du SPA conformes. Ajouté : PHP **8.4.x 64 bits, ni 8.3 ni 8.5** ; libellé exact « Établir le
  récapitulatif des avances » ; section *Correctifs des 23 et 24/10* (menu accentué, OM soldé amendable,
  recouvrements antérieurs non repris) et contrôles de recette des n° 19 à 28 (lot complémentaire depuis
  l'écran, solde, reliquat payé, compte désactivé non notifié). Aucune migration de plus.

#### Documentation — catégories d'indice (24/10/2026)

- **`docs/categories-indice.md`** relu contre le code (seuils serveur et client, appels de `normaliser()`
  et `regleIn()`, migration, tests) : conforme. Ajouté : un tableau *Quand la catégorie est déduite*
  (création, modification — recalcul d'office dès que l'indice ou le salaire est transmis —, import,
  suggestion de l'écran, catégorie de l'OM reprise de SEMO puis figée) et le filtre de `GET /api/agents/stats`.

#### Documentation — année fiscale (24/10/2026)

- **`docs/annee-fiscale.md`** relu contre le code (`Parametres`, `SettingsController`, `SettingsView`,
  `utils/exercice.ts`, routes, tests) : conforme. Précisés : la liste d'années garde une année enregistrée
  hors plage ; nouvelle section *Fuseau horaire* — le serveur détermine l'exercice en UTC (`config/app.php`),
  l'écran à l'heure du Bénin, d'où un décalage d'une heure le premier jour d'un exercice, sans effet tant
  que rien ne consomme l'exercice ; « où l'exercice est utilisé » daté du 24/10.

#### Corrigé (OM soldé de nouveau amendable, 24/10/2026)

- **Régression du correctif n° 25.** Refuser tout amendement d'un OM `SOLDE` rendait l'avenant impossible
  après paiement avec une part d'avance à 100 % (l'OM est soldé dès la confirmation de son avance), alors
  que la même rallonge reste acceptée à 70 %. Le refus général est retiré : les gardes du reliquat payé et
  du trop-perçu recouvré couvrent les cas où un amendement ferait diverger l'OM de ce qui a été versé ou
  retenu. `MissionService::amender` remet l'OM soldé à `VALIDE` puis applique la règle du solde : il
  redevient payable si l'amendement recrée un montant à régler, et reste soldé sinon. L'écran propose
  « Amender » sur un OM soldé dont le reliquat n'est ni payé ni recouvré. Tests dans `AmendementOmTest`
  (2, remplacent le test de refus) et `mission-detail.spec.ts` (2). Pest : 581 tests ; Vitest : 123.
  Défaut n° 28.
- Trouvé en mettant à jour **`docs/parametrage-part-avance.md`** : règle du solde sur le modèle, cas d'une
  part à 0 % (état des avances à 0) et à 100 % (OM soldé puis rouvert par une rallonge), tests. Mis à jour
  aussi : `docs/amendement-om.md`, `docs/circuits-validation.md`, `backend/docs/openapi.yaml`, la revue,
  `CLAUDE.md`, `README.md`, `AGENTS.md` et le guide utilisateur (régénéré).

#### Corrigé (notifications des comptes désactivés, 24/10/2026)

- **Comptes désactivés encore notifiés.** `NotificationService::notifierRole` visait tous les comptes du
  profil : un compte désactivé, qui ne peut plus se connecter, recevait encore la notification et l'e-mail
  (montants, bénéficiaires, missions). Seuls les comptes actifs sont désormais retenus, et `SendNotification`
  n'envoie pas l'e-mail d'un compte désactivé entre la notification et l'envoi (file d'attente). Deux tests
  dans `NotificationTest` (Pest : 580 tests). Défaut n° 27.
- **`docs/workflows-par-profil.md`** relu contre le code : matrice identique à `config/profils.php`, menus
  conformes ; ajout du §8 *Qui est notifié* (événement → profil, comptes actifs seulement), des règles
  d'amendement et de solde corrigées (n° 19, 22 à 27), du lot complémentaire proposé par l'écran, des
  actions de l'ADMIN sur les comptes et de la place des rôles personnalisés dans le circuit.
- Trouvé en mettant à jour **`docs/rbac-flexible.md`**, qui corrige aussi la portée des rôles personnalisés :
  ils peuvent signer un état s'ils portent les clés du circuit (autorisation par permission), mais ne sont
  pas notifiés. Nouvelle section *Limites connues* (notifications par profil, autorisations par permission ;
  comptes désactivés). Mis à jour aussi : la revue (n° 27), `CLAUDE.md`, `README.md`, `AGENTS.md`.

#### Corrigé (amendement d'un OM soldé, 23/10/2026)

- **OM soldé encore amendable.** L'écran masquait « Amender » pour un OM `SOLDE`, pas l'API. Avec une part
  d'avance à 100 %, l'OM est soldé dès le paiement de l'avance ; une rallonge recréait un reliquat positif
  qu'aucun fichier ne pouvait porter (le périmètre payable exige un OM `VALIDE`).
  `MissionService::exigerAmendementPossible` refuse désormais un OM soldé (avenant formel), après les gardes
  plus précises du reliquat payé et du trop-perçu recouvré. Défaut n° 25.
- **OM jamais soldé quand l'amendement ramène le reliquat à 0** (avance payée, total ramené à l'avance) : il
  restait `VALIDE` sans rien à régler. La règle du solde passe sur le modèle (`OrdreMission::solderSiRegle`)
  et s'applique aussi après un amendement. Défaut n° 26. Deux tests dans `AmendementOmTest` (Pest : 578 tests).
- Trouvés en mettant à jour **`docs/amendement-om.md`** (en-tête, tableau §3, garde-fous et solde après
  amendement §4.3, références). Mis à jour aussi : `docs/circuits-validation.md`, `backend/docs/openapi.yaml`
  (gardes de `amender`), la revue (n° 25 et 26), `CLAUDE.md`, `README.md`, `AGENTS.md` et le guide
  utilisateur (régénéré).

#### Corrigé (solde d'un OM, 23/10/2026)

- **OM au trop-perçu recouvré jamais soldé.** Le solde n'était examiné qu'à la confirmation d'un lot,
  et un versement `RECOUVRE` comptait comme restant à régler : un OM dont l'avance était payée et le
  trop-perçu récupéré par retenue restait `VALIDE`. Règle commune `LotPaiementService::solderSiRegle`
  (versements `CONFIRME` ou `RECOUVRE` = réglés, versements nuls ignorés), appelée à la confirmation
  **et** au constat du recouvrement. Deux tests dans `RegularisationTest`. Défaut n° 23.
- **OM exonéré resté `VALIDE` sur le circuit par OM.** La validation de l'état des avances solde aussitôt
  un OM de montant nul ; `MissionService::valider` le laissait `VALIDE`. Il le solde désormais. Test dans
  `CircuitValidationTest` (Pest : 576 tests). Défaut n° 24.
- Trouvés en mettant à jour **`docs/circuits-validation.md`** (§1, §3 « Solde » et « OM exonéré »,
  diagramme du §6, références). Mis à jour aussi : `docs/amendement-om.md`, la revue (n° 23 et 24),
  `CLAUDE.md`, `README.md`, `AGENTS.md` et le guide utilisateur (régénéré, annexe des statuts).

#### Corrigé (amendement d'un OM au reliquat payé, 23/10/2026)

- **Amendement accepté sur un reliquat déjà payé.** Sur une mission **sans état**, un OM dont le
  versement RELIQUAT était `CONFIRME` (OM soldé) restait amendable par l'API : le reliquat de l'OM
  changeait sans versement correspondant. Une rallonge n'était donc jamais payée, et une réduction ne
  produisait pas de trop-perçu. `MissionService::exigerAmendementPossible` refuse désormais ce cas
  (avenant formel), l'équivalent de l'état des reliquats validé sur le circuit par état. L'écran
  masquait déjà le bouton pour un OM soldé. Test dans `AmendementOmTest` (Pest : 573 tests).
  Défaut n° 22 de la revue.
- **`docs/amendement-om.md`** : révision du 23/10 (garde du reliquat payé, départage du dernier
  amendement par `id`, n° 17), ligne ajoutée au tableau §3, garde-fou §4.3, ordre des amendements
  §4.6. Mis à jour aussi : `docs/circuits-validation.md` §6, le guide utilisateur (régénéré),
  les compteurs Pest (573) dans `CLAUDE.md`, `README.md`, la documentation technique (régénérée)
  et la revue.

#### Corrigé (lot complémentaire depuis l'écran, 23/10/2026)

- **Lot complémentaire impossible à générer depuis l'interface.** `PaiementGeneration` masquait le
  bouton de génération dès que le dernier lot de la sélection était `CONFIRME`, même si l'aperçu
  listait des versements devenus payables (agent passé de Moov à MTN, OM validé tard…). Le serveur
  produisait pourtant le lot de séquence suivante. Désormais, le blocage « Lot confirmé —
  réinclusion bloquée » ne s'affiche que si rien n'est payable ; sinon l'écran propose **« Générer
  le lot complémentaire »**. La liste des lots est départagée par `id` (lots créés dans la même
  seconde).
  - Tests dans `paiements-async.spec.ts` (Vitest : 120 tests).
  - Mock Playwright aligné sur l'API : aperçu vide après confirmation.
  - Défaut n° 21 de la revue.
- **`docs/SCENARIO-DEMO.md`** relu contre l'interface. Libellés exacts : « Enregistrer la mission »,
  « Apposer le visa DAF », « Valider définitivement », « Générer le fichier.txt / .xlsx »,
  « Confirmer le paiement », « Enregistrer l'amendement », « Régénérer le fichier.txt »,
  « Générer le lot complémentaire.txt », « Matrice des permissions ». Le §8.3 précise que le DAF
  et le DG peuvent aussi rejeter un OM sans état. Le guide utilisateur (régénéré) cite le bouton
  du lot complémentaire.
- **`docs/circuits-validation.md`** :
  - Qui porte `om.rejeter` sur le circuit par OM (contrôleur, DAF, DG).
  - Solde d'un OM, identique dans les deux circuits, avec le versement nul ignoré (n° 19).
  - Lot complémentaire proposé par l'écran (n° 21).
  - Lien corrigé vers `backend/docs/queues.md`.

#### Corrigé (exercice proposé par l'écran Paramètres, 23/10/2026)

- **Exercice par défaut ignorant le mois de début.** Sans année enregistrée, `SettingsView`
  proposait l'année civile. Avec un début en juillet, en mars 2027, il affichait « 2027-2028 », et le
  premier enregistrement figeait cet exercice alors que le serveur déterminait « 2026-2027 ». Le
  défaut est désormais déterminé comme côté serveur (`utils/exercice.ts` → `exerciceDe`, miroir de
  `Parametres::anneeFiscalePour`). Il suit le mois de début tant que l'année n'est ni enregistrée ni
  choisie. Tests dans `settings-exercice.spec.ts` (Vitest : 118 tests). Sans effet sur les montants,
  car aucun traitement ne consomme encore l'exercice. Défaut n° 20 de la revue.

#### Corrigé (guide utilisateur, 23/10/2026)

- **PDF officiel de l'OM inaccessible depuis l'interface** : la fiche de l'OM ne proposait que
  « Télécharger le brouillon », masqué une fois l'OM validé ; l'écran OM-07 (`/ordres/:id/pdf`) n'était
  lié de nulle part. Nouveau lien **« PDF de l'OM »** dans l'en-tête de la fiche, à côté de
  « Historique ». Test dans `mission-detail.spec.ts`. Défaut n° 18.
- **Guide utilisateur** (régénéré) : accès au PDF de l'OM, journal des amendements (le plus récent en
  tête), document de retenue (motif du dernier amendement), boutons « Document » et « Marquer recouvré »
  (le guide citait « Constater le recouvrement », qui n'existe pas). `docs/SCENARIO-DEMO.md` §7 aligné.

#### Documentation — confrontation au code, document par document (23/10/2026)

- **`docs/ANaTT_Documentation_Technique_Fonctionnelle.docx`** (régénéré par
  `docs/generer_documentation_technique.py`) :
  - **Évolutions 1.3** complétées : échec d'un gros lot traité par `marquerErreur`, tri des
    sauvegardes, `openapi.yaml` verrouillée par `OpenApiTest`, départage des amendements par l'id,
    lien « PDF de l'OM », Form Requests supprimées.
  - §4.3 : échec définitif d'un gros lot. §4.4 : accès au PDF et dernier amendement. §4.7 : tri des
    sauvegardes. §7.1 : référence `openapi.yaml`. §10 : `OpenApiTest` et recette 83/83.
  - §9.5 : consignes d'exploitation (vider `storage/app/private/ordres/`, supprimer les deux Form
    Requests sur le serveur, Cron sur les trois files).
  - Écarts au code corrigés :
    - Docker cité alors que le dépôt n'en contient pas : remplacé par `start-dev.ps1`.
    - « Linux + Nginx » : remplacé par l'hébergement cPanel mutualisé.
    - `QUEUE_CONNECTION` : `sync` en production.
    - Noms réels des stores et composables.
    - `GenererEtatJob` = export de rapports.
- **`DEPLOY-CPANEL.md`** confronté au code :
  - **Node 20.19+ ou 22.12+** au lieu de « Node 18+ », version exigée par Vite 8 (`engines`). Même
    correction dans le `README.md`.
  - Extensions PHP alignées sur `composer.lock` : ajout de `dom`, `simplexml`, `xmlreader`,
    `xmlwriter`, `iconv` et `zlib`, avec leur usage (PDF, `.xlsx`, sauvegardes `.gz`).
  - §1.3 Option A : renseigner `SUPERADMIN_*` avant le seed. Sans cela, le super admin part dans le
    dump avec le mot de passe de développement `password`.
  - §9 : la tâche Cron recommandée est `schedule:run`. Elle couvre la sauvegarde de 02:00 **et** la
    purge des jetons Sanctum, que la seule ligne `db:backup` omettait.
  - Notes de version 1.3 : le lien « PDF de l'OM » figure parmi les changements du SPA.
- **`docs/runbook-mise-a-jour-cron.md`** aligné :
  - Node 20.19+ ou 22.12+ pour un build local.
  - Tâches permanentes : `schedule:run` **ou** `db:backup`. Le remplacement facultatif par
    `schedule:run` est proposé au ménage, sans doubler la sauvegarde.
  - Étape 4 (1.3) : supprimer sur le serveur les deux Form Requests retirées.
  - Recette (1.3) : contrôle en lecture seule du lien « PDF de l'OM ».
- **`docs/checklist-post-deploiement.md`** aligné :
  - Extensions PHP de `composer.lock`.
  - Sauvegarde planifiée par `schedule:run` **ou** `db:backup`, une seule des deux. `backup.log`
    n'est écrit que par `schedule:run`.
  - Renvoi corrigé vers le *Repli B* (et non « voie 3 »).
  - Deux contrôles 1.3 : lien « PDF de l'OM » sur la fiche, et suppression des Form Requests
    retirées.
- **`UPLOAD-VENDOR.md`** confronté à `composer.lock` :
  - PHP **8.4.x en 64 bits**, ni 8.3 ni 8.5. `phpoffice/phpspreadsheet` 1.30.2 déclare
    `php <8.5.0`, et `composer install` refuse le lock sous 8.5. `platform_check.php` ne contrôle
    que le minimum 8.4 et le 64 bits : un site en 8.5 démarrerait hors de la plage supportée.
  - `--no-dev` retire 51 paquets de développement ; le `vendor/` de production en compte 95.
  - Les tailles figées (« ~64 Mo », « ≈14 Mo ») sont retirées, car invérifiables et dépendantes
    du mode d'installation. Le contrôle des dossiers `.git` reste le garde-fou.
  - `DEPLOY-CPANEL.md` §4 : même borne haute.
- **`docs/workflows-par-profil.md`** confronté aux routes, à `config/profils.php` (matrice
  identique) et au menu :
  - Le rejet d'un OM d'une mission sans état relève de `om.rejeter`. Le DAF et le DG peuvent
    donc aussi le prononcer, pas seulement le contrôleur.
  - Le document de retenue d'un trop-perçu se télécharge avec `paiements.consulter`, et seul
    l'agent financier constate le recouvrement.
  - Accès au PDF de l'OM par le lien « PDF de l'OM ».
  - Menus du contrôleur et de l'ADMIN précisés : l'ADMIN n'a pas « Retranscrire ».
- **`docs/rbac-flexible.md`** confronté au code (composants, routes, permissions, audit, regex :
  conformes) :
  - *Prise d'effet* corrigée. Côté **serveur**, une édition de la matrice ou d'un override
    s'applique **dès la requête API suivante**, sans reconnexion (cache purgé, droits résolus à
    chaque requête). Seuls le menu et les boutons attendent le login ou le rafraîchissement de
    `/auth/me/permissions`.
  - Les tests « applique … immédiatement » (`RolePermissionTest`, `UserPermissionTest`)
    vérifient désormais aussi la **route** (403 avant, 200 après), et plus seulement
    `hasPermission()`.
  - `RoleMiddlewareTest` et `OpenApiTest` ajoutés à la table des tests.
- **`docs/parametrage-part-avance.md`** confronté au code :
  - Les valeurs extrêmes décrivent la tranche nulle (versement conservé pour un amendement,
    jamais dans un fichier, ne retient plus l'OM) et le défaut n° 19.
  - Tests complétés : `SoldeTest`, `OmPdfAmendementTest` (pourcentage du PDF tiré des montants
    de l'OM) et la part ignorée une fois l'avance engagée.
  - Nombre de tests (572) mis à jour dans `CLAUDE.md`, `README.md`, la documentation technique
    (régénérée) et la revue.
- **`docs/annee-fiscale.md`** : défaut de l'écran (exercice déterminé), `utils/exercice.ts` et
  `settings-exercice.spec.ts` ajoutés aux fichiers et tests ; consigne d'exploitation précisée
  (défaut n° 20). Vitest 118 dans `README.md`, la documentation technique et la revue.
- **`docs/categories-indice.md`** confronté au code :
  - §2 : méthodes de `CategorieIndice` complétées (`libelle`, `plage` et son repli, `valeursAcceptees`,
    `regleIn`) avec leurs consommateurs (PDF de l'OM et document de retenue, état de paiement,
    validation, import). Les exports de rapports ne portent pas la catégorie.
  - §6 : la commande de recette `npm run test:unit` restait en mode surveillance ; elle devient
    `npm run test:unit -- --run`.

#### Supprimé

- **Form Requests inutilisées** `StoreOrdreMissionRequest` et `UpdateOrdreMissionRequest` : vestiges de la
  saisie manuelle d'un OM, ils n'étaient référencés par aucune route ni aucun test et validaient
  contre une table `taux_per_diems` qui n'existe plus. Les OM sont retranscrits par
  `StoreMissionRequest` et ajustés par `AmenderOrdreMissionRequest`.

#### Vérification

État au 23/10/2026, après les correctifs n° 17 à 22 (fin de revue du 22/10 entre parenthèses) :

- **Pest** : 581 tests, 68 fichiers (552 / 65), suite exécutable sans `.env`.
- **Vitest** : 123 tests, 27 fichiers (113) ; **Playwright** : parcours critique (3 tests, API mockée).
- **Recette sur l'API réelle** : 83 / 83 contrôles (76 / 76) et 9 / 9 sondes sur les correctifs
  n° 19 et 22 ; scénario de démo rejoué, §7 « avenant » et §8.2 « lot complémentaire » compris
  (fichiers MTN/Moov réels).
- Chaque correctif a un test de non-régression vu en échec sans le correctif.
- **CI** (`.github/workflows/ci.yml`) : Lint, Pest, Vitest + type-check, Playwright et build
  `frontend-dist` exécutés sur chaque PR.

#### Documentation — guide utilisateur et documentation technique

- **Documentation technique 1.3** (`docs/ANaTT_Documentation_Technique_Fonctionnelle.docx`, régénérée
  par `docs/generer_documentation_technique.py`) : chapitre « Évolutions de la version 1.3 ».
  - Stack à jour : Laravel 12, PHP 8.4, Vite 8, Pinia 3, Vue Router 5, Vitest 4, Playwright.
  - Entités ajoutées : `om_amendements`, `roles`, `role_permissions`, `user_permissions` et
    `notifications` ; le nom de la table `ordre_missions` est corrigé.
  - Statuts des versements et des lots. Nouveaux workflows : amendement (§4.6) et
    sauvegarde/restauration (§4.7).
  - Montants des états (§5.4) et RBAC flexible (§6.2.1). Audit et API complétés : amendement,
    régularisations, notifications, rôles, sauvegardes, suivi des jobs.
  - Stockage des fichiers par mission. Mise à jour d'instance (§9.5) et nouveau chapitre
    10 « Qualité et tests ».
  - Comptes de démo `daf@` et `dg@` ajoutés.

- **Guide utilisateur 1.4** (`docs/Guide-Utilisateur-ANaTT.docx`, régénéré par
  `docs/generer_guide_utilisateur.py`) : nouveau chapitre « Nouveautés de la version 1.4 » et
  mise à jour des chapitres concernés.
  - Retranscription : un agent par mission ; filtre opérateur ; Valider / Rejeter pour les missions sans état.
  - Amendement : refus tant qu'un lot est en attente de dépôt.
  - État des reliquats : montant payable, trop-perçus mentionnés hors total.
  - Per diem : le transport est conservé sans jour facturé.
  - Paiements : gros lots avec progression, lot en erreur, lots complémentaires.
  - Barème et droits d'accès.
  - Nouvelle section 13.3 « Sauvegardes et restauration ».
  - Annexes : statut `ANNULE`, séquence des lots.
  - Mise en page : les étapes numérotées repartent à 1 dans chaque procédure, et les largeurs
    de colonnes sont respectées aussi sous LibreOffice.

#### Documentation — scénario de démo

- **`docs/SCENARIO-DEMO.md`** rejoué sur l'API le 22/10/2026 : montants et fichiers inchangés pour le
  parcours principal. Ajouts :
  - le refus d'un agent en double dans une mission ;
  - les gros lots générés en arrière-plan ;
  - la garde « lot en attente de dépôt » avant tout amendement ;
  - l'état des reliquats au montant payable, avec le trop-perçu affiché hors total.

  Nouveau **§8** avec des fichiers réels :
  - lot en erreur, puis régénération avec le numéro corrigé ;
  - changement d'opérateur suivi d'un **lot complémentaire** (`…_COMPL2.txt`) ;
  - mission sans état (Valider / Rejeter) ;
  - sauvegarde et restauration.

#### Documentation — déploiement

- **`UPLOAD-VENDOR.md`** :
  - nouvelle étape 0 « Faut-il renvoyer `vendor/` ? » (comparer `composer.lock`) ; rien à renvoyer
    pour la 1.3 ;
  - PHP 8.4 exigé en local aussi (sous 8.3, `composer install` refuse le lock) et sur le site
    (`platform_check.php`) ;
  - `--prefer-dist` et contrôle de l'absence de dossiers `.git` dans `vendor/` (un repli sur des
    clones git le fait passer de 180 Mo à plus de 600 Mo, mesuré) ;
  - extraction sans résidu : ancien `vendor/` renommé en `vendor_old/` (retour arrière), puis
    `bootstrap/cache/packages.php` et `services.php` vidés systématiquement ;
  - dépannage : version PHP du site, retour arrière.
- **`docs/runbook-mise-a-jour-cron.md`** adapté à la mise à jour vers la 1.3 :
  - encadrés « 1.3 » : migration attendue, `vendor/` inchangé, contrôle du 401 JSON, lots
    re-téléchargeables et écran Sauvegardes ;
  - prérequis : aucun lot en attente de dépôt avant l'opération ;
  - tâches Cron permanentes (`db:backup`, `queue:work`) distinguées des tâches temporaires du
    runbook ; elles restent en place et sont contrôlées au ménage ;
  - retour arrière : le `down()` de `add_sequence_to_lots_paiement` échoue dès qu'un lot
    complémentaire existe (revenir par le dump) ; fichiers écrits après la mise à jour à rapprocher
    avant tout redépôt. Compte des migrations corrigé (33).
- **`docs/checklist-post-deploiement.md`** complétée pour la 1.3 :
  - API : cohérence de la file d'attente (`QUEUE_CONNECTION` et tâche `queue:work`) et contrôle
    de la sauvegarde planifiée du lendemain ;
  - parcours de fumée étendu à 11 routes (régularisations, matrice des permissions, sauvegardes,
    maintenance) ;
  - contrôles 1.3 sans création de données : part d'avance lue par un profil non administrateur,
    montant des états de reliquats déjà validés inchangé, rangement des fichiers par mission ;
  - nouveaux contrôles **sur l'instance de recette uniquement** : restauration, lot en erreur,
    lot complémentaire, gros lot, garde d'amendement ;
  - filet de sécurité : comptes de démo absents, mot de passe du super administrateur initial
    changé, dump pris avant la mise à jour.
- **`DEPLOY-CPANEL.md`** :
  - nouveau §9.1 *Restaurer une sauvegarde* : prérequis `exec()`, `mysqldump` et `mysql`, et
    restauration par tâche Cron pour les grosses bases ;
  - variable `DB_RESTORE_BINARY` ;
  - **Notes de version 1.3** : migration `add_sequence_to_lots_paiement`, `vendor/` inchangé, SPA
    à redéployer, et rangement des fichiers de paiement par mission. Les fichiers déjà écrasés par
    l'ancienne collision sont signalés ;
  - trois entrées de dépannage pour la restauration.
- **`docs/checklist-post-deploiement.md`** : contrôles propres à la 1.3 (migration, 401 JSON, lots
  re-téléchargeables, sauvegardes, paraphes, mois par défaut du rapport mensuel).
- **`docs/runbook-mise-a-jour-cron.md`** : renvoi vers ces contrôles.
- **`.env.production.example` / `.env.example`** : variable `DB_RESTORE_BINARY`.

#### Documentation — README

- **`README.md`** :
  - concepts métier complétés : deux états de paiement par mission, état des reliquats au
    montant payable, lot en erreur, lot complémentaire, OM rejeté, amendement et trop-perçus ;
  - séparation ordonnateur/payeur ;
  - nouvelle section **Exploitation** : sauvegardes, restauration et mise à jour d'une instance ;
  - tests : 113 tests Vitest, suite Pest sans `.env`, variable `PLAYWRIGHT_CHROMIUM_EXECUTABLE` ;
  - **index de la documentation**.
- **Chemin de stockage corrigé** dans `DEPLOY-CPANEL.md` et dans la documentation technique : les
  fichiers de paiement sont dans `storage/app/private/paiements/{mission_id}/`, qui est la racine du
  disque `local`. La mention `storage/app/paiements/…` était inexacte.

#### Documentation — CLAUDE.md

- **`CLAUDE.md`** remis au niveau du code :
  - **§2 Stack** : Vite 8, Pinia 3, Vue Router 5, Vitest 4 et Playwright, Sanctum 4, Pest 3.
    Redis est facultatif. Le DevOps réel est WAMP, cPanel et GitHub Actions ; il n'y a pas de
    Docker dans le dépôt.
  - **§3 Structure** réécrite d'après l'arborescence réelle : contrôleurs, services, Support,
    commandes `db:backup` et `db:restore`, tests, e2e, documentation et maquettes HTML. Les
    fichiers inexistants ont été retirés : `docker-compose.yml`, `manuel-utilisateur.docx`,
    `specs-techniques.pdf`.
  - **§7** :
    - 401 JSON sur `/api/*` ;
    - pagination bornée de `/missions` et `/etats` ;
    - filtre opérateur de `/ordres` ;
    - `sequence` des lots.
  - **§8** : calcul avec la part d'avance paramétrable, le transport (y compris sans jour
    facturé) et `avance_engagee`. Nouvelles règles « un agent par mission », « OM rejeté » et
    « lot complémentaire ».
  - **§10** : commandes à jour (lint, e2e, worker, `db:backup`, `db:restore`, `start-dev.ps1`).
  - **§12** : ajout de `DB_DUMP_BINARY`, `DB_RESTORE_BINARY`, `MTN_COMPTE` et
    `PAIEMENT_ASYNC_SEUIL`. Les variables d'API Mobile Money sont retirées : elles ne sont lues
    nulle part et contredisaient la règle d'or.
  - **§1** : séparation ordonnateur/payeur vérifiée pour chaque membre d'un profil.

#### Corrigé — restauration et permissions

- **`db:restore` laissait l'ancienne matrice des permissions en vigueur** : la matrice RBAC est
  en cache sans expiration (`rbac.matrice`) et la restauration remplaçait `role_permissions`
  sans la purger. Les droits d'avant la restauration restaient appliqués jusqu'à une purge
  manuelle du cache. La commande purge désormais la matrice après une restauration réussie
  (`PermissionRepository::vider`). Repéré en mettant à jour `docs/rbac-flexible.md` ; test
  ajouté (`BackupControllerTest`), rouge sans le correctif.

#### Documentation — AGENTS.md (compléments)

- **`AGENTS.md`** : cache `rememberForever` de la matrice (appeler `PermissionRepository::vider()`
  après toute écriture de `role_permissions`) ; signatures du PDF de l'OM reprises de l'état et clé de
  cache à maintenir ; test de `db:restore` avec client `mysql` factice ; contenu hors dump ; ~560 tests.

#### Documentation — CLAUDE.md (compléments)

- **`CLAUDE.md`** : part d'avance paramétrable rappelée dans les règles métier ; séparation des
  tâches telle que codée ; PDF de l'OM signé par l'état (priorité, exclusions, clé de cache) ;
  cache de la matrice sans expiration, purgé par `db:restore` ; exercice non consommé à ce jour ;
  ce qui reste hors sauvegarde de la base.

#### Documentation — déploiement (compléments)

- **`DEPLOY-CPANEL.md`** :
  - §9.1 : la restauration purge la matrice des permissions ; encadré « Ce qu'une restauration ne
    touche pas » (`settings.json`, paraphes, fichiers produits dans `storage/`) ;
  - mise à jour, A.4 : sauvegarder `settings.json` et le dossier des paraphes avant l'opération ;
  - notes de version 1.3 : PDF de l'OM signé par l'état (code seul, aucune migration, cache des PDF
    d'OM régénéré) ; plus de purge manuelle du cache après `db:restore` ;
  - dépannage : droits d'accès anciens après un import phpMyAdmin (purge du cache), réglages
    perdus après écrasement de `storage/` ;
  - récapitulatif des artefacts : contenu de `storage/` détaillé.
- **`docs/runbook-mise-a-jour-cron.md`** : étape 1 alignée (réglages et paraphes).

#### Documentation — README (compléments)

- **`README.md`** : concepts métier — trois signataires distincts, PDF de l'OM signé par l'état ;
  exploitation — matrice des permissions rétablie dès la restauration, et ce qui reste **hors
  sauvegarde de la base** (`settings.json`, paraphes, fichiers produits) à conserver avec `storage/`.

#### Documentation — documentation technique (compléments)

- **Documentation technique** (`docs/ANaTT_Documentation_Technique_Fonctionnelle.docx`, régénérée par
  `docs/generer_documentation_technique.py`) :
  - évolutions 1.3 : PDF de l'OM signé par l'état, purge de la matrice après `db:restore` ;
  - §4.4 : blocs de signature (priorité OM, sinon état des avances), couverture par le tableau
    `lignes`, clé de cache incluant le statut de l'état ;
  - §4.7 : purge de la matrice à la restauration ; `settings.json`, paraphes et fichiers produits
    hors du dump ;
  - §6.2.1 : cache de la matrice sans expiration, purgé à l'écriture et après `db:restore` ;
  - §7 : l'exercice est exposé mais encore consommé par aucun écran ;
  - §10 : 558 tests Pest (66 fichiers).

#### Documentation — guide utilisateur (compléments)

- **Guide utilisateur** (`docs/Guide-Utilisateur-ANaTT.docx`, régénéré par
  `docs/generer_guide_utilisateur.py`) :
  - tableau des nouveautés : PDF de l'OM signé par l'état, restauration des droits d'accès ;
  - §6.2 : le PDF de l'OM reprend les signatures de l'état des avances (même code de
    vérification), jamais pour un OM rejeté ni sur un brouillon ;
  - §7.2 : un OM rejeté reste rejeté à la validation de l'état des avances ; l'avance est figée ;
  - §13 : l'exercice n'a pas encore d'effet sur les montants, rapports ou documents ;
  - §13.3 : droits d'accès rétablis dès la restauration ; encadré « Ce que la sauvegarde ne
    contient pas » (réglages, paraphes, fichiers produits).

#### Modifié — signatures sur le PDF de l'OM

- **Le PDF d'un OM reprend les signatures de l'état des avances.** Sur le circuit par état,
  les trois signatures (contrôle, « Vu » DAF, validation DG) sont portées par l'état de
  paiement, pas par l'OM : le PDF d'un OM d'une mission validée affichait donc ses cases
  « en attente ». `PdfGenerator` reprend désormais, au fur et à mesure du circuit, les
  signatures de l'état des avances qui couvre l'OM : instantané du signataire, paraphe
  archivé, même code de vérification que l'état.
  - Garde-fous : un OM absent du tableau figé de l'état (rejeté, annulé) n'en reçoit aucune ;
    un état rejeté n'en fournit aucune ; un brouillon d'OM n'en montre jamais ; une
    signature portée par l'OM lui-même (circuit par OM, données héritées) reste prioritaire.
  - Cache : la clé du PDF inclut le statut de l'état (`…-ETAT-CONTROLE.pdf`), pour qu'une
    signature apposée sur l'état régénère le PDF de l'OM resté au même statut.
  - Tests : `OmPdfSignaturesEtatTest` (5 cas, 3 rouges sans le correctif). Pest : 558 tests.
  - Documentation : `workflows-par-profil.md` §5, `SCENARIO-DEMO.md`,
    `checklist-post-deploiement.md`, rapport de revue (n° 11), README, CLAUDE.md.

#### Documentation — checklist post-déploiement (compléments)

- **`docs/checklist-post-deploiement.md`** :
  - réglages conservés : `settings.json` présent et valeurs inchangées (hors sauvegardes de
    la base) ;
  - document signé à vérifier = PDF de l'état ; les cases « en attente » du PDF de l'OM sont
    attendues ;
  - recette : la restauration rétablit la matrice des permissions sans purge manuelle du
    cache (correctif `db:restore`), et ne touche pas aux réglages.

#### Documentation — scénario de démo (compléments)

- **`docs/SCENARIO-DEMO.md`** (montants et fichiers inchangés) :
  - séparation des tâches détaillée (le DG ne valide pas un état qu'il a contrôlé ou visé) ;
  - étape C : un OM rejeté reste rejeté, l'avance est figée ; encadré « quel document
    montrer » — le PDF de l'état, pas celui de l'OM, dont les cases de signature restent
    « en attente » sur le circuit par état ;
  - §8.4 : restauration qui purge la matrice des permissions (démonstration proposée),
    réglages `settings.json` hors sauvegarde, prérequis du client `mysql`.

#### Documentation — workflows par profil

- **`docs/workflows-par-profil.md`** réécrit : il décrivait encore le circuit **par OM** en
  trois signatures (statuts OM `VERIFIE` / `VISA_DAF`, drapeau `WORKFLOW_DOUBLE_VALIDATION`),
  retiré depuis.
  - Parcours : récapitulatif par le gestionnaire, puis contrôle → visa DAF → validation DG
    **sur l'état de paiement**, une fois par tranche (avances puis reliquats) ; repli par OM
    en une étape pour les missions sans état.
  - Profils : récapitulatif et amendement (gestionnaire), numérotation (contrôleur, DAF,
    DG), lot en erreur, lot complémentaire et recouvrement (agent financier), séparation des
    tâches telle que codée (DG distinct du contrôleur **et** du DAF).
  - Matrice complétée (`om.numeroter`, `roles.gerer`) et recalée sur `config/profils.php`.
  - Brouillons d'OM et d'état ; document signé = état de paiement (paraphe archivé, état
    général). Constat signalé : le PDF de l'OM d'une mission validée par état garde ses
    blocs de signature « en attente » (les signatures vivent sur l'état).
  - Menu et écran de travail de chaque profil recalés sur `navigation.ts` (file `/etats`
    ouverte sur l'étape du signataire).

#### Documentation — catégories d'indice

- **`docs/categories-indice.md`** :
  - bornes inclusives par le bas précisées (salaire 186 000 à 371 999 pour `INDICE_400_799`) ;
  - champ indice vidé ignoré côté client (défaut n° 7 de la revue), avec son test ;
  - formule de secours `× 12 / 3097` : située (`Agent::indiceDepuisSalaire`) et signalée comme
    non utilisée par la déduction ni par un écran ;
  - points d'entrée de `normaliser()` vérifiés, tableau des tests, vérification manuelle
    complétée.

#### Documentation — année fiscale

- **`docs/annee-fiscale.md`** :
  - nouvelle section **« Où l'exercice est utilisé »** : constat vérifié dans le code — en
    dehors de l'écran Paramètres et de sa route publique, **aucun écran ni traitement ne lit
    l'exercice** (rapports, exports, états et PDF filtrent par dates). Le document annonçait
    une lecture « pour toute l'UI » ;
  - validation située dans `SettingsController`, fichiers et tests listés ;
  - section **Exploitation** : clés dans `settings.json` (hors sauvegardes de la base) ; une
    année enregistrée depuis l'écran devient explicite et ne bascule plus seule.

#### Documentation — part d'avance paramétrable

- **`docs/parametrage-part-avance.md`** :
  - comportement complété : transport compris dans le total, exonération, cache serveur de
    60 s, interaction avec l'amendement (part neutralisée une fois l'avance engagée), valeurs
    extrêmes 0 % et 100 % ;
  - durcissements frontend de la revue d'octobre 2026 (pas de cache sur échec, relecture
    après le changement de mot de passe imposé, lectures réactives) ;
  - validation située dans `SettingsController` (et non `routes/api.php`), lecteurs de la
    valeur, tests backend et frontend complétés ;
  - nouvelle section **Exploitation** : `settings.json` n'est pas dans les sauvegardes de la
    base, à conserver avec `storage/`.

#### Documentation — RBAC flexible

- **`docs/rbac-flexible.md`** :
  - niveau 1 : séparation ordonnateur/payeur vérifiée sur l'effectif de chaque membre, à
    l'édition comme à la réinitialisation (`parDefaut`) ;
  - cache de la matrice : sans expiration, purgé à l'écriture et après `db:restore` ; note
    d'exploitation pour une modification de `role_permissions` hors application ;
  - composants complétés (`parDefaut`, `vider`) et nouvelle section **Tests**.

#### Documentation — amendement d'un OM

- **`docs/amendement-om.md`** aligné sur la 1.3 :
  - encadré « Révisé (revue d'octobre 2026) » : avance engagée à l'amendement sur le circuit
    par OM, garde « fichier en attente de dépôt », verrou `lockForUpdate`, état des reliquats
    au montant payable ;
  - §3 : tableau du moment de l'amendement complété (mission sans état, états des reliquats
    `BROUILLON` / en signature / `VALIDE`) ; « 70/30 » remplacé par la part d'avance ;
  - §4 : textes au présent (colonnes signées et filtre `montant > 0` livrés, colonnes réelles
    de `om_amendements`) ; §4.5 corrigé — un état des reliquats validé **refuse**
    l'amendement, il n'est pas « invalidé » ;
  - §5 : écarts signalés (pas de contrôle dans les générateurs, rapports sans distinction
    initial / ajusté) ; §7 : décisions de cadrage notées comme **tranchées**, avec leur
    réponse ;
  - références et tests complétés.

#### Documentation — circuits de validation

- **`docs/circuits-validation.md`** aligné sur la 1.3 :
  - interface du circuit par OM (boutons Valider / Rejeter) et rejet par OM (versements `ANNULE`) ;
  - un OM rejeté n'est jamais repêché par la validation de l'état (propagation qui exclut `REJETE`) ;
  - porte de paiement : exclusions complètes de `scopeEligiblesLot` (lot `CONFIRME`, lot
    complémentaire) et seconde barrière `FileGenerator::exigerEtatValide` ;
  - gel de l'avance sur le circuit par OM (engagée à l'amendement si elle est déjà payée) ; part
    d'avance paramétrable au lieu de « 70/30 » ;
  - tableau des amendements : garde « fichier en attente de dépôt » ; verrou `lockForUpdate` ;
    état des reliquats au montant **payable** (la mention d'un total négatif était obsolète) ;
  - références et tests complétés.

#### Documentation — rapport de revue

- **`docs/revue-projet-2026-10.md`** mis à jour en fin de revue :
  - résultats « première passe » et « fin de revue » côte à côte (Pest 535 → 552, Vitest 99 → 113,
    scénario de démo, CI) ;
  - couverture par module recomptée (`pest --list-tests`) ; 18 vues sur 50 testées, composant
    `PaiementGeneration` désormais couvert ;
  - défaut n° 9 (`sequence` absent de `LotPaiementResource`) ajouté ;
  - nouvelles sections : documentation remise à niveau (§5), PR fusionnées, actions d'exploitation
    et dette restante (§6).

#### Documentation — AGENTS.md

- **`AGENTS.md`** mis à jour pour les sessions d'agents.
  - **Deux erreurs corrigées** :
    - la route publique de la part d'avance est `/api/admin/parametres/part-avance`, et non
      `/api/parametres/...` ;
    - le partage avance/reliquat est paramétrable, il n'est pas codé en dur à 70/30.
  - **Ajouts** :
    - prérequis PHP 8.4, tests sans `.env`, variable pour Chromium ;
    - `lint:check` plutôt que `lint`, et la règle « prouver qu'un test échoue sans le correctif » ;
    - pièges de l'API : 401 JSON, `FormData` en multipart, `getPerPage`, `moisLocal`, séparation
      des rôles par membre ;
    - paiements : stockage par mission, séquence et lots complémentaires, barrières
      d'éligibilité, lot en erreur, état des reliquats payable, OM rejeté ;
    - amendements, sauvegardes et restauration, comptes de démo ;
    - documents à régénérer après une évolution fonctionnelle.

- **`CHANGELOG.md`** : section « Non publié » réorganisée — la revue d'octobre 2026 est
  regroupée sous « Version 1.3 », avec la vérification et le renvoi vers les notes de mise à jour.

### Antérieur à la version 1.3

#### Corrigé

- **Déploiement : la version de PHP annoncée rendait l'API inutilisable**
  `DEPLOY-CPANEL.md` §4 demandait de sélectionner « PHP 8.2 ou 8.3 » dans cPanel.
  Or `composer.lock` épingle six paquets de production qui exigent **PHP ≥ 8.4** :
  `symfony/clock`, `symfony/css-selector`, `symfony/event-dispatcher`,
  `symfony/string`, `symfony/translation` (Symfony 8) et
  `spatie/laravel-activitylog`. Composer grave cette exigence dans
  `vendor/composer/platform_check.php`, que `autoload_real.php` charge à
  **chaque** requête : sous PHP 8.3 l'API répond **500 sur tous les appels**
  (« Your Composer dependencies require a PHP version ">= 8.4.0" »). Le guide
  aurait donc conduit tout droit à une instance morte.
  `composer.json` annonce encore `"php": "^8.2"` — laissé tel quel puisque c'est
  le **lock** qui détermine le `vendor/` uploadé, mais la contrainte réelle est
  désormais écrite noir sur blanc, avec la sortie de secours (rétrograder vers
  Symfony 7 en local et régénérer le lock) si l'hébergeur ne propose pas 8.4.
  Corrigé dans `DEPLOY-CPANEL.md` (§0, §4, toutes les commandes Cron passent à
  `ea-php84`), `docs/checklist-post-deploiement.md`, `README.md` et `CLAUDE.md`.

- **Deux tâches Cron se disputaient le même fichier de log**
  `migrate:status` écrivait dans `migration.log` avec `>` et `migrate --force`
  y ajoutait avec `>>`. Une tâche de statut restée active — cPanel n'ayant pas
  de planification « une seule fois », elle se rejoue toutes les cinq minutes —
  **tronquait le log de migration** en cours de lecture. Un fichier distinct par
  étape (`migration-status.log` / `migration-run.log`), et la règle « une seule
  tâche active à la fois » est explicite.

- **Faits obsolètes dans `README.md` et `CLAUDE.md`**
  Laravel 11 → **12** (`laravel/framework v12.56.0`), PHP 8.3+ → **8.4+**,
  et le compte de tests (495 / 59 fichiers → **531 / 62**), mesuré en exécutant
  la suite.

#### Ajouté

- **`docs/runbook-mise-a-jour-cron.md` — déroulé du jour J**
  Les tâches Cron étant disponibles sur l'instance ANaTT, la procédure de
  référence de `DEPLOY-CPANEL.md` est désormais celle par Cron (Terminal cPanel
  et migration à blanc en local deviennent des **replis**, pour les hébergeurs
  qui ne l'offrent pas). Le runbook en tire une page opérationnelle : neuf
  étapes cochables, commandes prêtes à coller, de la sauvegarde au ménage des
  tâches temporaires, avec le retour arrière en pied de page.
  Trois pièges propres à Cron y sont traités, qu'aucune documentation du projet
  ne couvrait : cPanel n'a pas de planification « une seule fois » (régler sur
  *Once Per Five Minutes*, laisser passer, **supprimer**) ; un `%` dans la
  commande est interprété comme un saut de ligne et tronque la tâche
  silencieusement ; et l'adresse e-mail du Cron Job donne une seconde fenêtre
  sur la sortie quand le fichier de log n'a pas été écrit du tout — le symptôme
  d'un mauvais chemin PHP.

- **Mise à jour d'une instance en ligne sans accès SSH — procédure complète**
  La section « Mise à jour » de `DEPLOY-CPANEL.md` ne couvrait la migration de la
  base qu'en une ligne (« Terminal cPanel ou tâche Cron »), sans dire quoi faire
  quand l'hébergeur n'offre ni l'un ni l'autre. Elle décrit désormais, dans
  l'ordre d'exécution : sauvegarde préalable (base **avec `DROP TABLE`**, SPA,
  `.env`), **relevé des migrations manquantes** depuis phpMyAdmin
  (`SELECT migration FROM migrations ORDER BY id;` comparé au contenu de
  `database/migrations/`), upload du code **avant** la migration, puis
  **trois voies** : tâche Cron ponctuelle (la plus largement disponible),
  Terminal cPanel, et — si aucun PHP en ligne de commande n'est accessible —
  **migration à blanc d'une copie locale de la base de production, puis
  réimport**. Cette troisième voie laisse Laravel jouer lui-même les migrations
  et tenir la table `migrations` à jour : aucun SQL n'est rédigé à la main, donc
  aucune divergence possible avec ce qu'attend le code.
  Deux pièges d'exploitation sont documentés : le chemin PHP des tâches Cron
  (`/usr/local/bin/php` est un alias pointant souvent vers une **autre** version
  que celle de « Select PHP Version » — d'où `/opt/cpanel/ea-php83/root/usr/bin/php`),
  et la **redirection de sortie** vers `/home/anatt/` (hors racine web), seule
  fenêtre sur le résultat quand on n'a pas de terminal.
  S'y ajoutent la purge des caches **sans artisan** (les trois dossiers à vider,
  dont `storage/framework/cache/data` qui porte la matrice des permissions), une
  procédure de **retour arrière**, un récapitulatif des artefacts propre à la mise
  à jour (avec ce qu'il ne faut **pas** écraser : `.env`, `storage/`,
  `runtime-config.js`) et huit entrées de dépannage spécifiques.

- **`backend/.env.production.example` — le gabarit manquait au dépôt**
  `DEPLOY-CPANEL.md` §5.1 demandait de copier ce fichier en `anatt-api/.env`,
  mais il n'avait jamais été versionné : la règle `.gitignore` `.env.*` l'excluait
  et seul `!.env.example` était ré-autorisé. Un déploiement suivant le guide à la
  lettre butait donc sur un fichier absent. Le gabarit est ajouté (valeurs de
  production commentées, aucune valeur réelle) et l'exception `.gitignore`
  étendue. Les clés qu'il porte ont été vérifiées une à une dans `config/`
  (`CORS_ALLOWED_ORIGINS`, `WORKFLOW_SEPARATION_TACHES`, `PAIEMENT_*`,
  `MTN_COMPTE`, `DB_DUMP_BINARY`, `FRONTEND_URL`, `SUPERADMIN_*`).

- **Garde-fou sur les modèles d'import du personnel**
  Les fichiers distribués aux utilisateurs (`docs/modele-import-agents.csv`, à
  séparateur **point-virgule** comme le produit Excel en configuration française,
  et son jumeau `.xlsx`) sont désormais importés par la suite de tests elle-même.
  Leur lisibilité ne tenait à aucun réglage du projet : c'est le défaut
  `excel.imports.csv.delimiter = null` de maatwebsite/excel qui laisse
  PhpSpreadsheet **inférer** le séparateur. Publier `config/excel.php` et
  « harmoniser » ce délimiteur sur celui des exports (`,`) rendrait le modèle
  illisible — silencieusement : une seule colonne reconnue, un rapport d'erreurs
  incompréhensible pour l'utilisateur. Vérifié en provoquant la régression :
  le modèle n'importe alors plus aucun agent et les tests tombent.
  Test : `backend/tests/Feature/ImportModeleAgentsTest.php` (4 cas : modèle CSV
  avec BOM et colonnes alignées, couverture des 3 catégories et des 2 opérateurs,
  modèle XLSX avec le téléphone préservé en texte, et fichier à séparateur `,`).

#### Corrigé (documentation)

- **`.gitignore` : commentaire contredisant le déploiement**
  Le bloc « Builds » affirmait que `frontend/dist/` était « volontairement
  VERSIONNÉ (déploiement FTP sans build serveur) », alors que `dist/` n'est
  versionné ni en fait (`git ls-files` le confirme) ni en intention —
  `DEPLOY-CPANEL.md` §2 et le job CI `dist` le produisent comme artefact
  `frontend-dist`. Le commentaire est corrigé et `frontend/dist/` explicitement
  ignoré, pour qu'un build local ne se retrouve pas commité par accident.

#### Modifié

- **Catégories d'indice nominatives — `ORDRE_1/2/3` remplacé**
  La catégorie d'un agent ne s'appelle plus « ordre » mais porte l'indice
  qu'elle désigne : `INDICE_SUP_800` (« Indice ≥ 800 »), `INDICE_400_799`
  (« Indice 400-799 ») et `INDICE_INF_400` (« Indice < 400 »). Le vocabulaire
  de l'application rejoint ainsi celui déjà imprimé dans la colonne
  *Grade/Indice* des états de paiement signés.
  Les écrans et les PDF affichent désormais un **libellé** et non plus le code
  brut (personnel, retranscription d'OM, matrice du barème, fiches, impressions).
  Les seuils (800 / 400 / 372 000 / 186 000), jusqu'ici dupliqués en quatre
  endroits côté serveur, sont centralisés.
  **Transition** : les anciens codes `ORDRE_*` ne sont plus produits mais restent
  **acceptés en entrée** — filtres `?categorie=`, fichiers d'import déjà préparés,
  création d'agent, de barème et retranscription — et sont traduits silencieusement.
  Le journal d'audit n'est pas réécrit : ses entrées antérieures conservent
  l'ancien vocabulaire.
  Fichiers : `backend/app/Support/CategorieIndice.php` (nouveau, source de vérité),
  `backend/database/migrations/2026_09_14_090000_renommer_categories_ordre_en_indice.php`
  (bascule des trois colonnes `enum` en trois temps — élargir, traduire, refermer),
  `frontend/src/types/agent.ts` (`CATEGORIE_LABELS`, `categorieLabel()`),
  + requests, services, seeders, factories, 10 écrans et les PDF.
  Tests : `backend/tests/Feature/CategorieIndiceTransitionTest.php` (7 cas :
  vocabulaire produit, anciens codes acceptés à la création, dans les filtres
  agents et barème, à l'import CSV, code inconnu toujours rejeté) et
  `backend/tests/Unit/CategorieTest.php` (normalisation, libellés, plages).
  Documentation : `docs/categories-indice.md`.
  Non impactés (vérifié) : scellé HMAC des états, états déjà signés, formats de
  fichiers MTN/Moov, et tous les montants.

#### Ajouté

- **Auto-synchronisation de l'état de paiement avec ses OM**
  Dès qu'un OM est créé, modifié (montant, statut) ou supprimé, l'état de la
  mission — s'il est en BROUILLON ou REJETE — est silencieusement recalé. Plus
  besoin de cliquer sur *« Rafraîchir »* : le brouillon reflète toujours les
  OM. Un état signé (CONTROLE / VISA_DAF / VALIDE) est **protégé** : le scellé
  financier apposé lors de la signature n'est jamais altéré par une mutation
  d'OM survenue après coup.
  Fichiers : `backend/app/Observers/OrdreMissionObserver.php`,
  `backend/app/Services/EtatPaiementService.php` (méthode `synchroniser`),
  `backend/app/Models/OrdreMission.php` (attribut `#[ObservedBy]`).
  Test : `backend/tests/Feature/EtatAutoSyncTest.php` (4 cas : rejet d'OM,
  état signé intact, ajout d'OM sur état REJETE, non-création d'état
  fantôme quand aucun état n'existe).

- **CI GitHub Actions** — un workflow `.github/workflows/ci.yml` exécute Pest
  (backend) et Vitest + `type-check` (frontend) sur chaque PR et push sur
  `main`. Objectif : empêcher qu'un merge silencieux ne supprime à nouveau des
  fonctions référencées par les tests (mécanisme qui a laissé passer l'incident
  du 30/07).

#### Corrigé

- **API — clé `data` toujours exposée dans les réponses `success()`**
  `App\Http\Traits\ApiResponse::success()` retournait `{}` (objet vide) lorsque
  `$data === null`, au lieu de `{data: null}`. Côté frontend, `unwrap({})`
  retournait alors un objet vide truthy, ce qui poussait les écrans à afficher
  une ressource fantôme (le cas typique : une mission sans état de paiement
  ouvrait un écran BROUILLON à 0 FCFA). La clé `data` est désormais toujours
  présente, même à `null`.
  Fichier : `backend/app/Http/Traits/ApiResponse.php`.

- **Frontend — garde-fou anti-état-fantôme** *(restauration d'un fix perdu au
  merge du 30/07 — commit d'origine `2e8a186`)*
  `etatsService.parMission()` considère désormais qu'un objet sans `statut`
  n'est PAS un état. La vue « État de paiement » retombe alors sur l'écran
  « Établir le récapitulatif » au lieu d'afficher un faux BROUILLON à 0 FCFA.
  Ce garde-fou complète le fix backend ci-dessus : il protège les postes qui
  n'auraient pas encore le back à jour.
  Fichier : `frontend/src/services/etats.ts`.

- **Frontend — vraie cause d'un échec de téléchargement rendue visible**
  *(restauration d'un fix perdu au même merge — commit d'origine `13cc8f8`)*
  Sur une requête `responseType: 'blob'` qui échoue, Axios livre le corps
  d'erreur en Blob : `error.response.data.message` était alors `undefined` et
  l'utilisateur ne voyait qu'un « impossible de télécharger » générique.
  L'intercepteur `useApi` relit désormais les Blobs JSON d'erreur et les
  reconvertit en `{ message, errors }` : le vrai motif (barème introuvable,
  stockage non inscriptible, etc.) remonte jusqu'au toast.
  Fichier : `frontend/src/composables/useApi.ts`.
  Tests associés : `frontend/src/__tests__/useApi.spec.ts` (référençaient déjà
  `normaliserErreurBlob` mais échouaient silencieusement sans CI — voir plus
  bas).

#### Notes de maintenance

- **Branches distantes obsolètes** (état au 31/07) — toutes à 0 commit d'avance
  sur `main`, aucun apport unique. Suppression recommandée après validation :

  ```bash
  git push origin \
    --delete arena/019fae24-anatt-ordre-mission \
             claude/list-root-directories-cUxVz \
             claude/workflow-adjustments-e1vmy9 \
             docs/maj-documentation-technique
  git fetch --all --prune
  ```

- **Recommandation GitHub** — activer *Branch protection* sur `main` avec
  « Require status checks to pass before merging » cochant les 2 jobs `Backend
  · Pest` et `Frontend · Vitest + type-check`. C'est ce qui rend la CI
  réellement bloquante pour les PR.
