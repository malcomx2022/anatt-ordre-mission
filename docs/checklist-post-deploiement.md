# Checklist de vérification post-déploiement — ANaTT Ordres de mission

> À dérouler **après le déploiement** sur cPanel. Chaque bloc confirme un maillon : d'abord l'API
> Laravel (`anatt-api/`), puis les fichiers du SPA, la connexion à l'API, le cache, les parcours et
> les correctifs livrés.
>
> - **SPA** : `https://omsemo.anatt.bj` (contenu de `frontend/dist/` dans `public_html/`)
> - **API** : `https://api-omsemo.anatt.bj` (application Laravel dans `anatt-api/`, racine `anatt-api/public`)
>
> Référence détaillée : [`DEPLOY-CPANEL.md`](../DEPLOY-CPANEL.md) — pour une **mise à jour**, lire d'abord
> ses *Notes de version* : 1.3 (migration, `vendor/` inchangé, SPA à redéployer) et 1.4 (maintenance,
> migration du fuseau, SPA à redéployer).
>
> Sauf mention contraire, tout se vérifie **sans créer de données** en production : les contrôles
> qui génèrent un lot ou restaurent une base se font sur une instance de **recette**.
>
> *Relu le 24/10/2026 contre le code* : contrôles des correctifs n° 17 à 32 ajoutés (§06, *Correctifs
> des 23 au 25/10* ; n° 29 à 32 le 25/10) — code seul, **aucune migration de plus** que celle de la 1.3.
> *Complété le 28/10/2026* : réglages de *Paramètres* appliqués et part de la mission (§06, dernière sous-section) ;
> puis messagerie appliquée (e-mail de test, motif des refus), session conservée sur erreur passagère et écran 500 (§05, §06).
> puis motif d'un refus affiché par *Paramètres* et e-mail en échec sans effet sur l'action qui notifie (§06).
> *Complété le 29/10/2026* : motif de **tout** refus affiché une fois, en français (§06, *Refus expliqués*).
> *Relu le 27/10/2026 (version 1.4)* : sortie de maintenance (§01), cache des PDF d'OM à vider aussi en 1.4
> (§04), comportement réel de l'écran Maintenance (§05), contrôles de la 1.4 précisés (§06).

---

## 01 · API Laravel

Le back-end doit répondre **avant** tout test du SPA.

- [ ] **Santé** : `https://api-omsemo.anatt.bj/up` renvoie la page de santé Laravel en **200**.
- [ ] **Route API** : un POST sur `/api/auth/login` répond en JSON ; un GET renvoie une erreur JSON (route vivante, pas une page cPanel).
- [ ] **`.env` serveur correct** : `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` renseignée, `APP_URL=https://api-omsemo.anatt.bj`, `CORS_ALLOWED_ORIGINS=https://omsemo.anatt.bj`.
- [ ] **Base de données** : `DB_DATABASE / DB_USERNAME / DB_PASSWORD` valides et **schéma à jour**.
- [ ] **Sortie de maintenance** : `https://api-omsemo.anatt.bj/api/auth/me` répond **401 en JSON**, pas **503**. `/up` ne le prouve pas : il répond 200 même en maintenance. Un 503 = `artisan up` non joué, ou la commande de migration s'est arrêtée avant lui (relire son log, `DEPLOY-CPANEL.md` § *Mise à jour*, B bis et D).
- [ ] **Aucune migration en attente** : dans phpMyAdmin, `SELECT migration FROM migrations ORDER BY id;` contient bien **tous** les noms de fichiers de `backend/database/migrations/` (sans `.php` ; **34** en version 1.4). Un nom manquant = migration non jouée. Voir `DEPLOY-CPANEL.md` § *Mise à jour*, B et D.
- [ ] **PHP 8.4.x 64 bits** sélectionné — ni 8.3 (minimum imposé par `composer.lock` : l'API renvoie 500 sur tous les appels), ni 8.5 (`phpoffice/phpspreadsheet` déclare `<8.5.0`, l'API démarrerait hors plage supportée ; cf. `DEPLOY-CPANEL.md` §4), extensions actives : `mbstring, openssl, pdo_mysql, tokenizer, xml, dom, simplexml, xmlreader, xmlwriter, ctype, json, iconv, fileinfo, gd, zip, zlib, bcmath, curl`. Cette liste est celle qu'exige `composer.lock` : `dom`, `gd` et `iconv` pour les PDF, `zip` et `xml*` pour les fichiers `.xlsx`, `zlib` pour les sauvegardes `.gz` (`DEPLOY-CPANEL.md` §4).
- [ ] **Permissions** : `storage/` (récursif) et `bootstrap/cache/` en écriture (755/775) — sinon erreur 500 au premier appel.
- [ ] **Caches applicatifs vidés** après changement de code ou de `.env` : `php artisan optimize:clear` (ou `config:clear` + `view:clear`).
- [ ] **HTTPS (AutoSSL)** actif sur `api-omsemo.anatt.bj`.
- [ ] **File d'attente cohérente** : avec `QUEUE_CONNECTION=sync` (défaut du gabarit de production), rien à faire. Avec `database`, la tâche Cron `queue:work --queue=paiements,etats,default --stop-when-empty` (chaque minute) est **active** et nomme bien les **trois files** — sinon les gros lots restent « en file d'attente » et l'écran finit par afficher « la file de traitement ne répond pas » (`DEPLOY-CPANEL.md` §9, `backend/docs/queues.md`).
- [ ] **Sauvegarde planifiée** : une tâche Cron la déclenche. La ligne recommandée est `schedule:run` chaque minute : elle lance la sauvegarde de 02:00 et la purge quotidienne des jetons Sanctum expirés. L'ancienne ligne `db:backup --compress` à 02:00 suffit pour la sauvegarde seule. **Une seule des deux**, sinon la sauvegarde est prise deux fois (`DEPLOY-CPANEL.md` §9). Le lendemain, un fichier daté apparaît dans `anatt-api/storage/app/backups/`. Avec `schedule:run`, la sortie de la sauvegarde va dans `storage/logs/backup.log`, qui ne doit contenir aucune erreur `mysqldump`/`exec`. La ligne `db:backup` seule n'écrit pas ce fichier : renseignez l'adresse e-mail du Cron Job pour recevoir la sortie.

> Sur mutualisé sans SSH, les commandes `artisan` passent par une **tâche Cron ponctuelle** (voie la plus
> largement disponible) ou le **Terminal cPanel** s'il est activé, avec le chemin **explicite** de la
> version PHP sélectionnée — `/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan …`
> et non l'alias `/usr/local/bin/php`, qui pointe souvent vers une autre version. Si l'hébergeur n'offre
> ni l'un ni l'autre, la base se met à jour par **migration à blanc en local puis réimport**
> (`DEPLOY-CPANEL.md` § *Mise à jour*, D, *Repli B*).

---

## 02 · Fichiers du SPA en place

Le bon build est réellement servi.

- [ ] `index.html` et un dossier `assets/` frais sont présents dans `public_html/` (anciens `assets/*.js` supprimés avant l'upload — pas de fichiers orphelins).
- [ ] Favicons et `site.webmanifest` uploadés (l'onglet affiche l'icône ANaTT).
- [ ] `.htaccess` de réécriture SPA présent : un rechargement direct sur `/ordres` ou `/missions/1/etat` renvoie l'app, pas une 404 Apache.

> ⚠️ **Variante « sans runtime-config.js » :** ne **pas** écraser le `runtime-config.js` du serveur — il porte l'URL de l'API en production.

---

## 03 · Configuration de l'API (côté SPA)

Le SPA parle au bon back-end.

- [ ] `public_html/runtime-config.js` pointe l'API de prod : `window.__ANATT_CONFIG__.apiUrl = "https://api-omsemo.anatt.bj"` (sans slash final).
- [ ] L'API répond depuis le SPA (dashboard qui charge ses KPIs = preuve que le SPA atteint l'API en HTTPS).
- [ ] Aucune erreur **CORS** ni **Mixed-Content** en console (SPA et API en HTTPS ; origine `omsemo.anatt.bj` autorisée).

---

## 04 · Purge du cache

Le symptôme « page blanche » venait d'un bundle mis en cache.

- [ ] Rechargement forcé <kbd>Ctrl</kbd>+<kbd>F5</kbd> (et test en navigation privée).
- [ ] Cache CDN / proxy purgé le cas échéant (Cloudflare, LiteSpeed cPanel…).
- [ ] Onglet Réseau : les `assets/*.js` chargés portent les nouveaux hachages (ex. `EtatDetailView-*.js` en **200**, pas un ancien nom en `304`).
- [ ] **Cache des PDF d'OM** vidé quand la version change ce que le PDF de l'OM imprime — c'est le cas de la 1.3 (gabarit) **et de la 1.4** (heure du Bénin) : `anatt-api/storage/app/private/ordres/` est vide juste après la mise à jour, puis se remplit au fil des téléchargements. En 1.4, un PDF d'OM resté en cache imprime ses heures de signature en UTC, une heure plus tôt que le PDF de l'état, toujours régénéré : les deux documents ne concorderaient plus.

---

## 05 · Parcours de fumée

Connecté en Super Administrateur. Chaque route rend son écran, **sans page blanche**.

| Route | Attendu |
| --- | --- |
| `/login` | Formulaire + logo ANaTT centré ; connexion OK |
| `/` | Tableau de bord : KPIs et graphiques chargés |
| `/missions` | Liste des missions, deux badges d'état par ligne (Av. / Rel.) |
| `/missions/1/etat` | Carte « Aucun état des avances pour cette mission » (plus de page blanche) |
| `/ordres` | Table des OM, filtres statut / zone / opérateur |
| `/paiements` | Liste des lots ; génération MTN/Moov accessible |
| `/etats` | File de travail des états de paiement |
| `/paiements/regularisations` | Liste des trop-perçus (vide sur une instance neuve), total à recouvrer |
| `/admin/roles` | Matrice des permissions (8 profils système) |
| `/admin/sauvegardes` | Liste des sauvegardes ; boutons Télécharger / Restaurer |
| `/admin/email` | Configuration e-mail : bandeau « Serveur renseigné… Serveur vide… », boutons « Envoyer un e-mail de test » et « Enregistrer », 7 notifications |
| `/500?redirect=/ordres` | Écran « Erreur serveur » (accents corrects) ; « Réessayer » ouvre `/ordres`, toujours connecté |
| `/maintenance` | Écran Maintenance : nouvelle vérification de l'API dans 30 s, puis retour seul à l'accueil (l'API répond) ; heure de fin affichée seulement avec `?fin=` |

> `/missions/1/etat` suppose qu'une mission n° 1 existe ; sur une instance neuve, prendre l'identifiant
> d'une mission réelle ou passer cette ligne.

- [ ] Les 13 routes ci-dessus rendent leur écran, zéro page blanche.
- [ ] Console navigateur sans erreur rouge sur ces pages.

---

## 06 · Correctifs livrés (main)

Vérifier ce qui a changé dans ce build.

- [ ] **Fiche d'état** : une mission sans récapitulatif affiche la carte d'état vide + bouton « Établir le récapitulatif des avances » (c'était le cas de la page blanche ; titre de l'écran au pluriel « États de paiement »).
- [ ] **Filtre missions** : une mission dont le reliquat est « Visé DAF » (avance déjà validée) remonte bien sous le filtre **Visé DAF** (le filtre couvre désormais les deux tranches, cohérent avec les deux badges affichés).
- [ ] **File de travail** : un Super Administrateur ouvre `/etats` sur « Tous », pas piégé sur une file vide (un acteur dédié — contrôleur / DAF / DG — atterrit toujours sur sa propre file).

### Version 1.3 (revue d'octobre 2026)

- [ ] **Migration** : `2026_10_22_090000_add_sequence_to_lots_paiement` figure dans la table `migrations`.
- [ ] **API sans en-tête Accept** : ouvrir `https://api-omsemo.anatt.bj/api/auth/me` dans un navigateur répond **401 en JSON** (`{"message":"Non authentifie."}`), plus une page 500.
- [ ] **Liste des lots** (`/paiements`) : les lots existants s'affichent et restent **re-téléchargeables** (fichiers antérieurs à la mise à jour inclus).
- [ ] **Sauvegardes** (`/admin/sauvegardes`, SUPER_ADMIN) : « Nouvelle sauvegarde » réussit, puis l'icône **Télécharger** récupère le fichier. *(La restauration se teste sur une base de recette, jamais en production.)*
- [ ] **Signatures** (`/admin/signatures`) : téléverser un paraphe réussit (il était envoyé vide avant la 1.3).
- [ ] **Rapport mensuel** (`/rapports/mensuel`) : le mois proposé par défaut est bien le mois **en cours**.
- [ ] **Part d'avance** : connecté avec un profil **non administrateur** (gestionnaire OM), `GET /api/admin/parametres/part-avance` répond `200` avec la valeur configurée (70 par défaut), et l'écran de retranscription affiche la répartition avec cette part — pas un 70 % figé.
- [ ] **États de reliquats déjà validés** : leur montant scellé est **inchangé** après la mise à jour (la nouvelle règle « montant payable » ne s'applique qu'aux récapitulatifs établis ou rafraîchis ensuite).
- [ ] **Fichiers de paiement** : à la **première génération réelle** après la mise à jour, le fichier apparaît dans `anatt-api/storage/app/private/paiements/{mission_id}/` (Gestionnaire de fichiers cPanel). Ne déplacer aucun ancien fichier.
- [ ] **Réglages conservés** : `anatt-api/storage/app/settings.json` est toujours présent et l'écran **Paramètres** affiche les mêmes valeurs qu'avant (part d'avance, exercice, SMTP). Ce fichier n'est **pas** dans les sauvegardes de la base : s'il a disparu (dossier `storage/` écrasé), le restaurer depuis la copie de `storage/` faite avant la mise à jour.
- [ ] **PDF signés** : sur une mission validée par le circuit, le PDF de l'**état** (« Télécharger l'état ») porte les trois signatures, le numéro et les paraphes ; le PDF d'un **OM** de cette mission reprend les mêmes signatures (même code de vérification). Un OM rejeté n'en porte aucune (cf. `docs/workflows-par-profil.md` §5).
- [ ] **PDF d'un OM avec transport** (sans état ni amendement) : la ligne « jour(s) × taux » sous le total affiche le taux du barème, **hors** transport. Un taux gonflé = le cache des PDF d'OM n'a pas été vidé (section 04).
- [ ] **Liste des sauvegardes** (`/admin/sauvegardes`) : la plus récente est en tête, y compris d'un mois sur l'autre.
- [ ] **Accès au PDF de l'OM** : la fiche d'un OM (`/ordres/:id`) propose le lien **« PDF de l'OM »** à côté de « Historique », y compris pour un OM validé. Il ouvre l'aperçu (`/ordres/:id/pdf`), et **Télécharger PDF** produit le document. « Télécharger le brouillon » n'apparaît plus une fois l'OM validé ou soldé.
- [ ] **Fichiers retirés** : `anatt-api/app/Http/Requests/StoreOrdreMissionRequest.php` et `UpdateOrdreMissionRequest.php` ont été effacés du serveur, car l'envoi FTP ne les supprime pas (`docs/runbook-mise-a-jour-cron.md`, étape 4). S'ils restent, rien ne casse : ils ne sont jamais chargés.

### Correctifs des 23 au 25/10/2026 (n° 17 à 32, code seul)

Sans création de données :

- [ ] **Menu latéral accentué** : « États de paiement », « Générer MTN », « Régularisations », « Barèmes », « Paramètres », « Système », et « Matrice des permissions » sous *Utilisateurs*. Des libellés sans accents = ancien bundle servi (section 04).
- [ ] **Liste des OM, filtre « Soldé »** : un OM dont le trop-perçu a été recouvré **avant** la mise à jour reste `VALIDE` (le correctif s'applique aux recouvrements constatés ensuite) ; c'est attendu, il n'y a pas de reprise.
- [ ] **Fiche d'un OM soldé** (`/ordres/:id`) : « Amender » est proposé si le reliquat n'est ni payé ni recouvré (part d'avance à 100 %), masqué sinon.
- [ ] **Erreurs de l'API en JSON** (n° 29) : `https://api-omsemo.anatt.bj/api/auth/login` ouvert dans le navigateur répond **405 en JSON** (`{"message":"The GET method is not supported…"}`), et une route inconnue comme `/api/inexistante` répond `{"message":"Route introuvable."}`. Une page HTML = ancien `bootstrap/app.php` encore en place.

#### Sur l'instance de recette uniquement

- [ ] **Restauration** : créer une sauvegarde, modifier une donnée, puis **Restaurer** depuis `/admin/sauvegardes` (recopie du nom du fichier) → la donnée revient, une sauvegarde de sécurité supplémentaire (l'état d'avant la restauration) apparaît dans la liste, et l'audit trace `BACKUP_RESTORE`. En cas d'échec : `DB_RESTORE_BINARY`, `exec()` et `DEPLOY-CPANEL.md` §9.1.
- [ ] **Restauration et permissions** : après la sauvegarde, retirer une permission à un profil (**Matrice des permissions**, menu *Utilisateurs*), puis restaurer → la permission est de retour **dès la requête suivante**, sans purge manuelle du cache (la restauration purge la matrice mise en cache). Les réglages de **Paramètres**, eux, ne changent pas (hors sauvegarde).
- [ ] **Lot en erreur** : générer un lot, le passer en **Erreur** → ses versements reviennent « en attente » ; après correction du numéro de l'agent, la régénération reprend le **nouveau** numéro.
- [ ] **Lot complémentaire** : après confirmation d'un lot, un versement devenu payable ensuite (par exemple un agent passé de Moov à MTN avant le paiement Moov, cf. `docs/SCENARIO-DEMO.md` §8) part dans un lot badgé **« complémentaire n° 2 »** (fichier `…_COMPL2`) ; le lot confirmé n'est pas réécrit.
- [ ] **Gros lot** (plus de `PAIEMENT_ASYNC_SEUIL` bénéficiaires, 50 par défaut) : la génération affiche son suivi en cours, puis le fichier se télécharge automatiquement.
- [ ] **Amendement** : refusé tant qu'un lot contenant l'OM est **généré** (en attente de dépôt) ; accepté une fois le lot confirmé ou passé en erreur.
- [ ] **Lot complémentaire depuis l'écran** : après confirmation d'un lot, si l'aperçu liste encore un bénéficiaire, le bouton s'intitule **« Générer le lot complémentaire.txt »** (ou `.xlsx`) ; s'il ne reste personne, l'écran affiche « Lot confirmé — réinclusion bloquée » (n° 21).
- [ ] **Solde** : avec une part d'avance à 100 %, l'OM passe `SOLDE` à la confirmation de son avance (n° 19) ; une rallonge le rouvre (`VALIDE`, reliquat payable après l'état des reliquats, n° 28) ; un trop-perçu marqué **recouvré** solde l'OM si l'avance est payée (n° 23) ; un OM exonéré validé par OM passe directement `SOLDE` (n° 24).
- [ ] **Amendement d'un reliquat payé** (mission sans état) : refusé (n° 22) ; un amendement qui ramène le reliquat à 0, avance payée, solde l'OM (n° 26).
- [ ] **Fonction imprimée** (n° 30) : *Paramètres › Signatures* sans fonction pour le DG, compte du DG portant une fonction (par `DG_FONCTION` et `ComptesCircuitSeeder`) → l'état validé imprime cette fonction, et non « Le Directeur Général ». Une fonction saisie dans les Paramètres reste prioritaire.
- [ ] **Super administrateur protégé** (n° 31) : `DG_EMAIL` égal à l'e-mail d'un super administrateur → après `ComptesCircuitSeeder`, ce compte reste `SUPER_ADMIN` et le seeder signale le poste non attribué.
- [ ] **Compte désactivé** : désactiver un compte d'agent financier, valider un état des avances → aucune notification ni e-mail pour ce compte (n° 27).
- [ ] **PDF après amendement** : sur un OM dont l'avance est validée, télécharger le PDF, amender à la baisse jusqu'au trop-perçu, puis retélécharger → le nouveau PDF affiche « Avance engagée », « Trop-perçu (à recouvrer par retenue sur salaire) » avec un montant négatif, « Montant total dû », et l'encadré de l'amendement (date, jours avant → après, motif). Le premier PDF n'est plus servi.

### Version 1.4 (décisions D1 à D4, 26/10/2026)

- [ ] **Migration** : `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif` figure dans la table `migrations`.
- [ ] **Fuseau** : dans phpMyAdmin, un `date_paiement` relevé avant la mise à jour a pris **une heure** de plus ; à l'écran (Historique des paiements), il affiche la **même** heure qu'avant. Une heure de décalage à l'écran = migration jouée avec la configuration en cache (rollback, `optimize:clear`, puis migrer de nouveau).
- [ ] **Rapport mensuel** : un paiement confirmé le 1er du mois entre 0 h et 1 h (heure du Bénin) compte dans ce mois, plus dans le précédent.
- [ ] **Notifications** : un compte à qui l'on a accordé `paiements.generer` à titre individuel reçoit « OM validé » / « État validé » ; un agent financier à qui on l'a retiré ne le reçoit plus.
- [ ] **Écran Signatures** : le bandeau d'information se lit d'un seul tenant (plus de texte en colonnes).
- [ ] **OM amendé en trop-perçu** : la fiche et l'aperçu du PDF affichent « Avance engagée » et « Trop-perçu (à recouvrer par retenue sur salaire) », comme le PDF officiel.
- [ ] **Configuration e-mail** (`/admin/email`) : la liste présente les **7** notifications du circuit actuel (« État à signer », « État validé »…), chacune avec ses destinataires (permission) ; l'ancien modèle « Mission soumise » n'y figure plus.
- [ ] **Maintenance** : pendant une mise à jour (`artisan down`), toute action dans le SPA ouvre l'écran « Maintenance en cours » ; après `artisan up`, il revient seul à la page demandée (au plus 30 s). Ce comportement est celui du SPA **1.4** : lors de la mise à jour 1.3 → 1.4, c'est encore le SPA 1.3 qui est servi pendant la maintenance (il n'est remplacé qu'après), et ses écrans échouent sans message. Constat possible dès la mise à jour **suivante**, ou sur la recette (`artisan down`, puis une action dans le SPA 1.4) ; en production, seul le contrôle de sortie de maintenance (§01) se vérifie après coup.
- [ ] **Cache des PDF d'OM** : sur une mission validée par le circuit, l'heure de signature du DG est la **même** sur le PDF de l'état et sur celui d'un de ses OM. Une heure d'écart = `storage/app/private/ordres/` non vidé (§04).
- [ ] **Échec de génération** : après l'échec d'une génération sur un lot déjà généré, l'écran affiche le lot en erreur, sans bouton « Confirmer le paiement », sans recharger la page.

### Réglages de Paramètres et part de la mission (27-28/10/2026, code seul)

Sans création de données :

- [ ] **Réglages publics** : connecté avec n'importe quel profil, `GET /api/admin/parametres/application` répond `200` avec `nom` et `operateurs` (`MTN`, `MOOV` à `true` tant qu'ils n'ont pas été désactivés). L'onglet du navigateur porte le nom de l'application.
- [ ] **Écran Paramètres** (`/admin/parametres`) : plus aucune mention « indicatif » ; l'onglet *Notifications* liste les **7** notifications réelles (« État à signer »…). Relire chaque onglet **avant** le premier « Enregistrer tout » : les valeurs affichées seront appliquées (« Forcer HTTPS » seulement si le site répond déjà en `https://`).
- [ ] **Écran Messagerie** (`/admin/email`) : la configuration SMTP n'est plus « indicative ». Si un serveur y figure (saisi avant la mise à jour), il **remplace** désormais le `.env` : le vérifier, ou vider le champ pour revenir au `.env`, puis « Envoyer un e-mail de test » → le message arrive dans la boîte du super administrateur.
- [ ] **Refus expliqués (Messagerie)** : saisir le port `70000` puis « Enregistrer » → le message « Le port SMTP doit être un entier entre 1 et 65535. » s'affiche (et non « Donnees invalides. ») ; rien n'est enregistré. Remettre le port.
- [ ] **Refus expliqués (Paramètres)** : onglet *Général*, part de l'avance `120` puis « Enregistrer tout » → « Le pourcentage d'avance doit être un entier entre 0 et 100. » (et non « Donnees invalides. ») ; rien n'est enregistré, **aucun** onglet. Recharger la page : la part d'avant est affichée.
- [ ] **Refus expliqués (tous les écrans, messages en français)** : *Utilisateurs › Nouvel utilisateur*, saisir l'adresse d'un compte existant (ex. celle du super administrateur) puis « Créer » → « Cette adresse e-mail est déjà utilisée. » sous le champ et dans l'avertissement en haut à droite, et non « Donnees invalides. » ni une clé du type `validation.unique` (fichier `resources/lang/fr/validation.php` absent ou non envoyé). Rien n'est créé.
- [ ] **Session conservée au rechargement** : recharger (F5) une page quelconque → on reste connecté, sur la même page. Seule une session expirée (durée de session dépassée, compte désactivé) renvoie à la connexion.
- [ ] **Part de la mission** : l'écran d'un état (`/missions/:id/etat`) titre ses onglets « Avances (… %) » avec la part de **ses** OM ; l'écran de génération libelle la tranche de même, une fois la mission choisie.

#### Sur l'instance de recette uniquement

- [ ] **Opérateur fermé** : désactiver MTN dans *Paramètres › Paiements* → l'écran *Générer MTN* affiche le bandeau « désactivés dans les Paramètres » et grise le bouton ; Moov reste ouvert. Réactiver.
- [ ] **Mode maintenance** : l'activer → un compte gestionnaire voit l'écran *Maintenance* ; le super administrateur continue de travailler et le désactive ; le gestionnaire revient seul à sa page.
- [ ] **Notification coupée** : désactiver « Lot confirmé », confirmer un lot → aucune cloche ni e-mail de ce type ; les autres notifications partent. Réactiver.
- [ ] **Journal des connexions** : l'activer, échouer une connexion → l'audit (`/audit`) trace `LOGIN_ECHEC` avec l'e-mail saisi.
- [ ] **Tentatives et session** : régler 3 tentatives → la 4e connexion manquée de la minute répond « trop de tentatives » ; une durée de session de 5 min déconnecte au-delà. Remettre les valeurs voulues (défaut : 5 tentatives, 480 min).
- [ ] **Échec d'envoi expliqué** : dans *Messagerie*, serveur `127.0.0.1`, port `2`, chiffrement *Aucun* → « Envoyer un e-mail de test » affiche « Échec de l'envoi : Connection could not be established… ». **Vider le serveur** ensuite (retour au `.env`) et renvoyer un test, reçu.
- [ ] **E-mail en échec sans effet sur l'action** : même serveur injoignable, valider un OM d'une mission sans état (ou signer un état) → l'action aboutit, sans erreur ; la cloche de l'étape suivante (profil financier, ou signataire) porte la notification ; l'échec figure dans le journal du jour (`storage/logs/laravel-AAAA-MM-JJ.log` en production, `LOG_STACK=daily` ; `laravel.log` sinon) (« SendNotification: echec envoi email »). **Vider le serveur** ensuite.
- [ ] **Erreur passagère** : outils de développement du navigateur › *Réseau* › bloquer l'URL `*/api/auth/me`, recharger une page (`/ordres`) → écran « Erreur serveur », pas l'écran de connexion. Débloquer, cliquer « Réessayer » → retour sur `/ordres`, toujours connecté.
- [ ] **Saisie d'une mission** : ajouter deux fois le même agent dans l'assistant → « Agent déjà présent dans cette mission », étape bloquée avant l'enregistrement.

---

## 07 · Filet de sécurité

- [ ] Sauvegarde de l'ancien `public_html/` conservée avant l'upload (rollback possible).
- [ ] **Aucun compte de démonstration** en production (`admin@`, `gestion@`, `finance@`… `@anatt.bj`) : le seeder ne les crée qu'en `local`/`testing` ; s'il en existe (base importée depuis un poste de dev), les supprimer.
- [ ] **Super administrateur initial** : son mot de passe (`SUPERADMIN_PASSWORD`) a été changé après la première connexion.
- [ ] **Sauvegarde de sécurité** prise juste avant la mise à jour (dump phpMyAdmin avec `DROP TABLE`, `DEPLOY-CPANEL.md` § *Mise à jour*, A.1) et conservée hors du serveur.

> Si la page blanche persiste après upload **et** purge de cache : relever la **console navigateur**
> (erreurs rouges) sur `/missions/1/etat` et la réponse de `GET /api/missions/1/etat` — ce serait alors
> un cas propre à l'environnement.
