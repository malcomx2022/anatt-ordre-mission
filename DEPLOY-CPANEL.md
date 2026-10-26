# Déploiement ANaTT sur cPanel (hébergement mutualisé)

Guide pas-à-pas pour déployer **sans accès SSH** (FTP + phpMyAdmin), avec :

- **SPA Vue** sur le domaine principal — `https://omsemo.anatt.bj`
- **API Laravel** sur un sous-domaine — `https://api-omsemo.anatt.bj`

> Compte cPanel : utilisateur système **`anatt`** (préfixe BDD `anatt_`, dossier `/home/anatt/`).
>
> *Relu le 25/10/2026 contre le code* : seeders, `.env.production.example`, `bootstrap/app.php`,
> `routes/console.php`, `vendor/composer/platform_check.php`, 33 migrations. La version **1.3**
> comprend les correctifs n° 1 à 29 (*Notes de version 1.3*).

```
public_html/                 -> SPA Vue (contenu de frontend/dist/)
anatt-api/                   -> application Laravel (hors web)
anatt-api/public/            -> racine du sous-domaine api-omsemo.anatt.bj
```

---

## 0. Prérequis (sur votre PC)

Il faut, installés en local pour préparer les artefacts :

- **PHP 8.4.x, 64 bits**. Pas 8.5 : `composer install` y refuse le `composer.lock`, cf. §4 ;
- Composer ;
- **Node 20.19+ ou 22.12+**, exigé par Vite 8. La CI utilise Node 22.

Le serveur mutualisé n'aura besoin **ni de Composer ni de Node** : on y envoie le code déjà prêt.
Il devra lui aussi tourner en **PHP 8.4.x, 64 bits** (cf. §4).

---

## 1. Préparer le BACKEND en local

### 1.1 Dépendances de production (vendor/)

```powershell
cd backend
composer install --no-dev --optimize-autoloader
```

> `vendor/` sera **uploadé tel quel** (le serveur n'exécute pas composer).

### 1.2 Générer une clé d'application

```powershell
php artisan key:generate --show
```
Copiez la valeur `base64:...` : elle ira dans `APP_KEY` du `.env` serveur.

### 1.3 Préparer la base de données à importer

Choisissez **une** option, puis exportez en SQL.

**Option A — base propre (recommandé en prod)** : schéma + référentiels + 1 super admin, sans les données de démonstration.

Renseignez d'abord dans le `backend/.env` **local** le super admin définitif : `SUPERADMIN_EMAIL`,
`SUPERADMIN_NAME` et surtout `SUPERADMIN_PASSWORD`. En local, `UserSeeder` retombe sinon sur le mot
de passe de développement `password`, qui partirait tel quel dans le dump. Comme la base est seedée
en environnement local, `UserSeeder` crée aussi les sept comptes de démo (`admin@`, `gestion@`…).
Ils sont à supprimer une fois en ligne. Les données de démonstration (agents, missions, paiements)
ne viennent pas : elles ne sont produites que par `db:seed` sans `--class`.
```powershell
# Sur une base locale dédiée (ex. anatt_export)
php artisan migrate:fresh
php artisan db:seed --class=ZoneSeeder
php artisan db:seed --class=BaremeSeeder
php artisan db:seed --class=UserSeeder      # SUPER_ADMIN (SUPERADMIN_*) + comptes démo à supprimer ensuite
```

**Option B — base de démonstration complète** (missions, paiements, audit) :
```powershell
php artisan migrate:fresh --seed
```

Export SQL (adaptez le chemin de mysqldump si besoin) :
```powershell
& "C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqldump.exe" -u root anatt_missions > anatt_prod.sql
```

> En Option A, pensez à supprimer les comptes de démo superflus une fois en ligne
> (ne gardez que votre SUPER_ADMIN). Changez **tous** les mots de passe par défaut.

**Comptes nominatifs du circuit (facultatif, recommandé)** : renseignez dans le `backend/.env` local
les variables `CONTROLEUR_*`, `DAF_*` et `DG_*`, sur le modèle de `.env.production.example`. Lancez
ensuite, sur la même base locale et avant l'export :

```powershell
php artisan db:seed --class=ComptesCircuitSeeder
```

Ce seeder ne se joue jamais seul : il faut l'appeler par `--class`. Il crée les trois signataires
avec un mot de passe temporaire **affiché une seule fois** : notez-le. Le changement de mot de passe
est imposé à la première connexion. Un poste non renseigné est ignoré, et relancer le seeder ne
réinitialise aucun mot de passe. Détail : `docs/deploiement-circuit-validation.md` §3.3.

---

## 2. Préparer le FRONTEND en local

L'URL de l'API est **modifiable après déploiement** via `runtime-config.js` (pas besoin de recompiler à chaque changement de domaine). On peut donc builder dès maintenant.

> `dist/` n'est **pas** versionné dans le dépôt (il est reconstruit à la demande,
> comme `vendor/` côté backend). Deux façons de l'obtenir :

**Option 1 — build local** (nécessite Node 20.19+ ou 22.12+) :
```powershell
cd frontend
npm ci
npm run build      # produit frontend/dist/
```

**Option 2 — artefact CI (aucun build local)** : sur GitHub, ouvrez le dernier run
CI de la branche `main` (onglet **Actions**) et téléchargez l'artefact **`frontend-dist`**.
Dézippez-le : son contenu est identique à `frontend/dist/`.

Dans les deux cas, `dist/` contient : `index.html`, `assets/`, les favicons,
`site.webmanifest`, `.htaccess` (routing SPA) et `runtime-config.js`.

---

## 3. cPanel — Base de données

1. **cPanel > MySQL® Databases**
   - Créez une base : nom `missions` → cPanel donne **`anatt_missions`**
   - Créez un utilisateur : nom `oms` → **`anatt_oms`** + mot de passe fort
   - Ajoutez l'utilisateur à la base avec **ALL PRIVILEGES**
2. **cPanel > phpMyAdmin** : sélectionnez la base, onglet **Import**, envoyez `anatt_prod.sql`.

---

## 4. cPanel — Sous-domaine de l'API

1. **cPanel > Domains (ou Subdomains)** : créez `api-omsemo.anatt.bj`.
2. Réglez sa **Document Root** sur `anatt-api/public`
   (créez le dossier `anatt-api` à la racine du compte, **au même niveau** que `public_html`, pas dedans).
3. **cPanel > Select PHP Version** : choisissez **PHP 8.4** et activez les extensions :
   `mbstring, openssl, pdo_mysql, tokenizer, xml, dom, simplexml, xmlreader, xmlwriter, ctype, json,
   iconv, fileinfo, gd, zip, zlib, bcmath, curl`.
   `dom`, `gd` et `iconv` servent aux PDF (DomPDF). `zip`, `xmlreader`, `xmlwriter` et `simplexml`
   servent aux fichiers Moov `.xlsx`, à l'import Excel des agents et aux exports de rapports. `zlib`
   sert aux sauvegardes `.sql.gz` et à leur restauration. Ces extensions sont exigées par
   `composer.lock`. La plupart sont actives par défaut : vérifiez seulement qu'aucune n'est décochée.

> ⚠️ **PHP 8.4 est un minimum, pas une préférence.** `composer.lock` épingle six paquets de
> production qui exigent PHP ≥ 8.4 (`symfony/clock`, `css-selector`, `event-dispatcher`, `string`,
> `translation` — Symfony 8 — et `spatie/laravel-activitylog`). Composer grave cette exigence dans
> `vendor/composer/platform_check.php`, exécuté à **chaque** requête : sous PHP 8.3, l'API renvoie
> un **500 sur tous les appels**, avec le message « Your Composer dependencies require a PHP
> version ">= 8.4.0" ». Ce n'est pas un avertissement que l'on peut ignorer.
>
> **Ni 8.3 ni 8.5.** `phpoffice/phpspreadsheet` 1.30.2 (fichiers Moov `.xlsx`, imports,
> exports) déclare `php <8.5.0`. Sous PHP 8.5, `composer install` refuse le lock ; un site passé
> en 8.5 démarrerait quand même, car `platform_check.php` ne contrôle que le minimum 8.4 et le
> 64 bits, mais il sortirait de la plage supportée. Gardez **PHP 8.4.x, 64 bits**
> (`UPLOAD-VENDOR.md` §1).
>
> `composer.json` annonce `"php": "^8.2"`, mais c'est le **lock** qui fait foi pour le `vendor/`
> que vous uploadez. Si votre hébergeur ne propose pas encore PHP 8.4, il faut rétrograder les
> dépendances en local (Symfony 7 au lieu de 8) et régénérer `composer.lock` **avant** de
> déployer — ce n'est pas un réglage serveur.

---

## 5. Upload du BACKEND (FTP)

Envoyez **tout le dossier `backend/`** dans `anatt-api/` (y compris `vendor/`), SAUF :
`node_modules/` (inexistant), `tests/`, `.git`, et votre `.env` local.

Arborescence cible :
```
anatt-api/
├── app/  bootstrap/  config/  database/  public/  routes/  storage/  vendor/  ...
└── .env            (créé à l'étape suivante)
```

### 5.1 Le fichier .env du serveur

Copiez `backend/.env.production.example` en `anatt-api/.env` (via l'éditeur de fichiers cPanel) et renseignez :

- `APP_KEY=` la clé générée en 1.2
- `APP_URL=https://api-omsemo.anatt.bj`
- `FRONTEND_URL=https://omsemo.anatt.bj` — racine du SPA. Elle sert aux liens des e-mails de
  réinitialisation de mot de passe. Sans elle, ces liens pointent vers `APP_URL`, c'est-à-dire vers l'API.
- `DB_DATABASE / DB_USERNAME / DB_PASSWORD` de l'étape 3
- `CORS_ALLOWED_ORIGINS=https://omsemo.anatt.bj`
- `DB_DUMP_BINARY=` chemin mysqldump de l'hébergeur si vous utilisez les sauvegardes (sinon laissez `mysqldump`)
- `DB_RESTORE_BINARY=` chemin du client `mysql` de l'hébergeur, utilisé par la **restauration** d'une
  sauvegarde (écran *Sauvegardes* ou `artisan db:restore`) ; sinon laissez `mysql`
- Bloc `MAIL_*` (SMTP de l'hébergeur)
- `MTN_COMPTE` — compte payeur de l'en-tête `HDR` du fichier MTN. Le gabarit propose `SARALY` :
  faites-le confirmer par la DAF.
- `WORKFLOW_SEPARATION_TACHES=true` (valeur du gabarit) : le DAF ne vise pas un état qu'il a
  contrôlé, et le DG ne valide pas un état qu'il a contrôlé ou visé. Passer à `false` est une
  décision d'exploitation explicite.

Rappels déjà positionnés pour le mutualisé : `APP_DEBUG=false`, `CACHE_STORE=file`,
`SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync` (aucun worker/Redis nécessaire).

### 5.2 Permissions

Donnez **755** (ou 775) en écriture à :
```
anatt-api/storage/            (et tous ses sous-dossiers)
anatt-api/bootstrap/cache/
```
Via FileZilla : clic droit > *Attributs de fichier* > 755, « Récursif dans les sous-dossiers ».

### 5.3 Vérifier l'API

Ouvrez `https://api-omsemo.anatt.bj/up` → doit afficher la page de santé Laravel (200).
Ouvrez ensuite `https://api-omsemo.anatt.bj/api/auth/login` dans le navigateur. Ce GET répond
**405 en JSON** (`{"message":"The GET method is not supported…"}`) : c'est normal, la connexion se
fait en POST. Ouvrez aussi `https://api-omsemo.anatt.bj/api/auth/me` : il répond **401 en JSON**
(`{"message":"Non authentifie."}`). Toute route `/api/*` répond en JSON, même sans en-tête `Accept`.
Une page HTML à cet endroit signale un `bootstrap/app.php` antérieur à la 1.3.

---

## 6. Upload du FRONTEND (FTP)

Envoyez le **contenu** de `frontend/dist/` dans `public_html/`
(donc `public_html/index.html`, `public_html/assets/…`, `public_html/.htaccess`, `public_html/runtime-config.js`, favicons…).

### 6.1 Pointer le SPA vers l'API

Éditez `public_html/runtime-config.js` :
```js
window.__ANATT_CONFIG__ = {
  apiUrl: "https://api-omsemo.anatt.bj"
};
```
> C'est l'avantage du runtime-config : **aucune recompilation** pour changer l'URL de l'API.

---

## 7. HTTPS

**cPanel > SSL/TLS Status** : lancez **AutoSSL** (Let's Encrypt) sur `omsemo.anatt.bj`
**et** `api-omsemo.anatt.bj`. Vérifiez que les deux sont en HTTPS (sinon le navigateur
bloquera les appels mixtes).

---

## 8. Vérification finale

1. `https://omsemo.anatt.bj` → écran de connexion (logo, favicon).
2. Connexion avec le SUPER_ADMIN.
3. Le tableau de bord charge les KPIs (preuve que le SPA atteint l'API en HTTPS).
4. Testez une génération de fichier MTN/Moov et un export PDF. Avec une base propre (Option A),
   ce test attend la première mission retranscrite et son état des avances validé : aucun lot n'est
   générable avant.

---

## 9. Tâches récurrentes (facultatif) — Cron cPanel

Le projet tourne en `QUEUE_CONNECTION=sync` : **aucun cron requis** pour fonctionner.
Si vous activez la file `database` (gros volumes) ou voulez des sauvegardes planifiées :

```bash
# cPanel > Cron Jobs. Utilisez le chemin EXPLICITE de la version PHP sélectionnée
# (/usr/local/bin/php est un alias qui pointe souvent vers une autre version).

# Planificateur Laravel, chaque minute (recommandé) : il déclenche ce que routes/console.php
# planifie — db:backup --compress à 02:00 (rétention 30 jours, log storage/logs/backup.log)
# et la purge quotidienne des jetons Sanctum expirés (sanctum:prune-expired).
* * * * *  /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan schedule:run >> /dev/null 2>&1

# … OU, si vous ne voulez que la sauvegarde : une seule ligne à 02h00 (pas de purge des jetons).
# N'activez pas les deux : la sauvegarde serait prise deux fois.
# 0 2 * * *  /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan db:backup --compress

# (si QUEUE_CONNECTION=database) traiter les TROIS files chaque minute — sans --queue,
# seule « default » est traitée et les gros lots ne sont jamais générés
* * * * *  /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan queue:work --queue=paiements,etats,default --tries=3 --stop-when-empty
```

> Files d'attente, modes `sync`/`database`/`redis` et comportement en cas d'échec : `backend/docs/queues.md`.
> Les jetons expirés sont refusés même sans la purge (`SANCTUM_EXPIRATION`). La purge ne fait que
> vider la table `personal_access_tokens`.

> `db:backup` appelle `mysqldump` via `exec()`. Certains mutualisés désactivent `exec` ou
> n'exposent pas `mysqldump` : renseignez alors `DB_DUMP_BINARY` dans le `.env`, et si la
> sauvegarde échoue malgré tout, restez sur l'export **phpMyAdmin** (§ Mise à jour, A.1).

### 9.1 Restaurer une sauvegarde

Depuis la version 1.3, l'écran **Administration › Sauvegardes** (SUPER_ADMIN) sait aussi
**télécharger** et **restaurer** une sauvegarde de `anatt-api/storage/app/backups/`. La
restauration passe par `artisan db:restore`, qui :

1. prend d'abord une **sauvegarde de sécurité** de l'état courant (`db:backup` — si elle échoue,
   la restauration est annulée et la base n'est pas touchée) ;
2. décompresse le `.sql.gz` en PHP, puis rejoue le dump avec le client `mysql`
   (`DB_RESTORE_BINARY`, via `exec()`) ;
3. purge la matrice des permissions mise en cache (sans expiration) : les droits d'accès de la
   sauvegarde s'appliquent dès la requête suivante, sans vider le cache à la main ;
4. trace l'opération au journal d'audit (`BACKUP_RESTORE`).

> **Ce qu'une restauration ne touche pas** : le dump ne contient que la base. Les réglages de
> l'écran *Paramètres* (`storage/app/settings.json` : part d'avance, exercice, SMTP…), les paraphes
> de l'écran *Signatures* et les fichiers produits (PDF, fichiers de paiement) restent dans
> `anatt-api/storage/`. Sauvegardez ce dossier à part (Gestionnaire de fichiers → *Compresser*), et
> restaurez-le en même temps que la base si vous revenez à un état antérieur.

Elle a donc les **mêmes prérequis** que la sauvegarde (`exec()` autorisé, `mysqldump` **et**
`mysql` accessibles). Sur un mutualisé, une restauration lancée depuis l'écran tourne dans la
requête HTTP : pour une base volumineuse, elle peut dépasser le `max_execution_time` de PHP.
Préférez alors une **tâche Cron ponctuelle** (même méthode qu'au § Mise à jour, D) :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan db:restore backup_2026-10-22_020000.sql.gz --force > /home/anatt/restore.log 2>&1
```

> Une restauration **remplace toute la base** : les saisies postérieures à la sauvegarde sont
> perdues. Prévenez les utilisateurs, choisissez une heure creuse, et **testez-la une fois sur une
> base de recette** avant d'en avoir besoin en production. Le repli reste l'import phpMyAdmin
> d'un dump (§ Mise à jour, H).

---

## Mise à jour d'une version déjà en ligne (redéploiement)

Les sections 1 à 8 décrivent la **première** installation. Pour livrer une **nouvelle version**
sur une instance déjà en place, on ne réimporte pas la base : on met à jour le code, on applique
les **nouvelles migrations**, puis on rafraîchit le SPA.

Ordre imposé : **BDD d'abord, SPA en dernier**. Un SPA neuf devant une base ancienne provoque des
500 en série ; l'inverse (base à jour, SPA ancien) est inoffensif — l'ancien SPA n'appelle
simplement pas les nouveaux champs.

### A. Sauvegarde préalable (non négociable)

1. **Base** : phpMyAdmin > base `anatt_missions` > onglet **Exporter** > méthode **Personnalisée** >
   cochez **« Ajouter DROP TABLE / VIEW »** > compression **gzip** > Exécuter.
   Gardez ce fichier : c'est votre seul point de retour si une migration se passe mal.
2. **SPA** : Gestionnaire de fichiers cPanel > `public_html` > tout sélectionner > **Compresser**
   en `public_html_AAAA-MM-JJ.zip`, puis téléchargez-le.
3. **`.env`** : téléchargez `anatt-api/.env` (il ne sera pas écrasé, mais il est irremplaçable).
4. **Réglages et paraphes** : téléchargez `anatt-api/storage/app/settings.json` et le dossier des
   paraphes (`anatt-api/storage/app/private/signatures/`). Ils ne sont **pas** dans le dump de la base ;
   s'ils venaient à disparaître (dossier `storage/` écrasé par erreur), ce sont eux qui restaurent la
   part d'avance, l'exercice, la messagerie et les signatures.

### B. Savoir CE QUI manque à la base en ligne (préalable à tout)

Laravel tient la liste des migrations déjà jouées dans la table **`migrations`**. Sans SSH, on la
lit depuis phpMyAdmin — onglet **SQL** de la base `anatt_missions` :

```sql
SELECT migration, batch FROM migrations ORDER BY id;
```

Comparez le résultat avec le contenu de `backend/database/migrations/` de la nouvelle version
(noms de fichiers sans `.php`). **Tout nom présent dans le dossier et absent de la table est une
migration à appliquer.** Notez-les : c'est exactement ce que la suite doit produire.

> Exemples : une base installée avant le 14/09 n'aura pas
> `2026_09_14_090000_renommer_categories_ordre_en_indice` — la bascule du vocabulaire
> `ORDRE_1/2/3` → `INDICE_*` reste donc à jouer. Une base antérieure à la **version 1.3**
> (octobre 2026) n'aura pas `2026_10_22_090000_add_sequence_to_lots_paiement` (cf. *Notes de
> version 1.3* ci-dessous).

### C. Uploader le nouveau code de l'API

Dans `anatt-api/` (FTP), écrasez `app/`, `config/`, `database/`, `public/`, `resources/`,
`routes/`, ainsi que `bootstrap/app.php` et `bootstrap/providers.php`.

**Ne touchez pas** à :

- `anatt-api/.env` — il porte vos identifiants de base et votre `APP_KEY` ;
- `anatt-api/storage/` — il porte les PDF générés, les sauvegardes et les logs ;
- `anatt-api/bootstrap/cache/` — à **vider**, jamais à écraser par le dossier local (§ E).

Re-uploadez `vendor/` **uniquement** si `composer.lock` a changé (`composer install --no-dev
--optimize-autoloader` en local d'abord — cf. `UPLOAD-VENDOR.md` pour l'envoi zippé).

> Le code doit être en place **avant** de migrer : les fichiers de migration que vous venez
> d'uploader sont précisément ce que la commande va exécuter.

### D. Appliquer les migrations — par tâche Cron (procédure de référence)

**cPanel > Cron Jobs** exécute du PHP en ligne de commande sans qu'aucun accès SSH soit ouvert.
C'est la voie normale de ce projet ; deux replis sont décrits plus bas pour les hébergeurs qui
ne l'offrent pas.

> Pour le déroulé pas-à-pas du jour J, avec les commandes prêtes à coller et les cases à cocher,
> voir **[`docs/runbook-mise-a-jour-cron.md`](docs/runbook-mise-a-jour-cron.md)**. La présente
> section explique *pourquoi* chaque étape est là.

**1. Trouvez le vrai binaire PHP.** `/usr/local/bin/php` est un alias qui pointe souvent vers une
version **différente** de celle choisie dans « Select PHP Version ». Une migration lancée avec
PHP 8.3 échouera sur le contrôle de plate-forme de Composer ; une migration lancée avec PHP 8.5
sortira de la plage supportée (cf. §4). Utilisez le chemin explicite de la version sélectionnée :

```
/opt/cpanel/ea-php84/root/usr/bin/php     (PHP 8.4 — la version exigée, cf. §4)
```

Vérifiez-le d'abord avec une tâche de contrôle (cPanel > **Cron Jobs**, planification « Once per
five minutes » le temps du test) :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -v > /home/anatt/migration.log 2>&1
```

Ouvrez `/home/anatt/migration.log` dans le Gestionnaire de fichiers : il doit afficher `PHP 8.4.x`
(ni 8.3, ni 8.5).

**2. Listez d'abord, migrez ensuite.** Jamais l'inverse — `migrate:status` ne modifie rien et
confirme la liste établie en **B** :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate:status > /home/anatt/migration-status.log 2>&1
```

Le rapport distingue `Ran` (déjà jouée) de `Pending` (à jouer).

**3. Migrez** — une seule tâche, qui purge les caches, migre, puis purge de nouveau :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear > /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate --force >> /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear >> /home/anatt/migration-run.log 2>&1
```

`--force` est obligatoire en production (il remplace la confirmation interactive, impossible en
Cron). La première purge fait lire aux migrations la configuration **à jour** : avec une
configuration en cache (`php artisan optimize`), elles liraient l'ancienne — en 1.4, la migration
du fuseau ne décalerait alors rien. La redirection `> ... 2>&1` est **essentielle** : sans SSH, ce fichier est votre seule
fenêtre sur ce qui s'est passé.

**4. Relisez le log**, confirmez que chaque migration attendue affiche `DONE`, puis
**supprimez les tâches Cron** — sinon elles se rejouent toutes les cinq minutes : `migrate`
serait sans effet, mais chaque passage **écrase le log** que vous étiez en train de lire.

Trois règles qui évitent les mauvaises surprises :

- **Une seule tâche active à la fois.** Créez la suivante après avoir supprimé la précédente.
  Deux tâches concurrentes se disputent les fichiers de log (d'où un fichier distinct par étape).
- **Le log va dans `/home/anatt/`**, jamais dans `public_html/` ni `anatt-api/public/` : il y
  serait téléchargeable par n'importe qui.
- **Pas de `%` dans la commande Cron.** Cron l'interprète comme un saut de ligne et tronque la
  commande ; un nom de fichier horodaté façon `$(date +%Y)` casse silencieusement la tâche.
  Gardez des noms de log fixes, ou échappez en `\%`.

> cPanel n'a pas de planification « une seule fois ». Réglez la tâche sur **« Once Per Five
> Minutes »**, laissez-la passer une fois, puis supprimez-la. Renseigner l'adresse e-mail du
> Cron Job vous donne en prime une copie de la sortie par courriel — une seconde fenêtre utile
> si le fichier de log n'a pas été écrit du tout (typiquement : mauvais chemin PHP).

#### Replis — si les tâches Cron ne sont pas disponibles

Les deux voies suivantes produisent le même résultat que la procédure ci-dessus. Elles ne servent
que si **cPanel > Cron Jobs** est absent ou désactivé chez votre hébergeur.

##### Repli A — Terminal cPanel *(si l'hébergeur l'a activé)*

cPanel > **Terminal**. Mêmes commandes, en interactif, avec la sortie à l'écran :

```bash
cd ~/anatt-api
/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate:status
/opt/cpanel/ea-php84/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php84/root/usr/bin/php artisan optimize:clear
```

##### Repli B — Migration à blanc en local, puis réimport *(aucun PHP en ligne de commande côté serveur)*

Si l'hébergeur n'offre **ni Cron ni Terminal**, on ne rédige pas le SQL à la main : on laisse
Laravel migrer une **copie** de la base de production sur votre poste, et on renvoie le résultat.
Aucune requête n'est écrite manuellement, donc aucune divergence possible avec ce qu'attend le code.

> Cette voie impose une **fenêtre de maintenance** : toute saisie faite en ligne entre l'export
> (étape 1) et le réimport (étape 6) serait perdue. Prévenez les utilisateurs et choisissez une
> heure creuse.

1. **Exporter la production** : phpMyAdmin > `anatt_missions` > **Exporter** > **Personnalisée** >
   cochez **« Ajouter DROP TABLE / VIEW »** > **Exécuter**. Vous obtenez `anatt_missions.sql`.
2. **Recréer la base en local** (phpMyAdmin local ou ligne de commande WAMP) : créez
   `anatt_prod_copie`, puis importez-y `anatt_missions.sql`.
3. **Pointer l'application locale sur cette copie** — dans `backend/.env`, le temps de l'opération :
   ```env
   DB_DATABASE=anatt_prod_copie
   ```
4. **Migrer la copie** depuis `backend/` :
   ```powershell
   php artisan migrate --force
   php artisan migrate:status     # tout doit afficher "Ran"
   ```
   Laravel joue **exactement** les migrations manquantes et met lui-même la table `migrations`
   à jour. Si une migration échoue, elle échoue **ici**, sur une copie, pas en production.
5. **Vérifier en local** : démarrez l'application (`php artisan serve` dans `backend/` **et**
   `npm run dev` dans `frontend/`, ou `start-dev.ps1`), connectez-vous, ouvrez la liste des OM
   et un état de paiement. C'est le test qui vaut la peine — il porte sur vos vraies données,
   pas sur un jeu de démonstration.
6. **Réexporter et réimporter** : exportez `anatt_prod_copie` (toujours avec **« Ajouter DROP
   TABLE »**, compression **gzip** si le fichier dépasse la limite d'upload de phpMyAdmin, souvent
   50 Mo), puis, dans le phpMyAdmin **de production**, sélectionnez `anatt_missions` > **Importer**
   > ce fichier. Les `DROP TABLE` en tête remplacent proprement l'ancien schéma.
7. **Remettre votre `.env` local** sur `DB_DATABASE=anatt_missions` pour ne pas continuer à
   travailler sur la copie.

> Si l'import phpMyAdmin s'interrompt (timeout sur un gros dump), déposez le `.sql` dans
> `/home/anatt/` par FTP et utilisez **phpMyAdmin > Importer > « sélectionner depuis le serveur »**,
> ou découpez l'export table par table.

### E. Après la migration : purger les caches de l'API

Laravel met en cache configuration, routes et vues. Après tout changement de code **ou** de `.env`,
l'ancienne version reste servie tant que ces caches ne sont pas vidés.

- Cron et Terminal : `php artisan optimize:clear` (déjà enchaîné dans la commande Cron ci-dessus).
- Repli B (aucun PHP CLI) : par **FTP ou Gestionnaire de fichiers**, videz ces trois dossiers
  (**gardez les dossiers eux-mêmes et leur `.gitignore`** — Laravel régénère le contenu à la
  requête suivante) :

  | Dossier | Contenu à supprimer | Ce que cela débloque |
  |---|---|---|
  | `anatt-api/bootstrap/cache/` | `config.php`, `routes-v7.php`, `packages.php`, `services.php` | Config et routes compilées (un `.env` modifié reste sinon sans effet) |
  | `anatt-api/storage/framework/views/` | les `*.php` | Vues Blade compilées (gabarits PDF) |
  | `anatt-api/storage/framework/cache/data/` | tout le contenu | Cache applicatif (`CACHE_STORE=file`) : matrice des permissions et paramètres système, sinon servis dans leur ancienne version |

### F. Frontend (SPA)

1. Récupérez le nouveau `frontend/dist/` (build local **ou** artefact CI `frontend-dist` du run `main`).
2. Dans `public_html/`, **supprimez l'ancien dossier `assets/` et `index.html`**, puis uploadez le
   nouveau contenu de `dist/`. Supprimer l'ancien `assets/` évite d'accumuler les bundles des
   versions précédentes (les noms portent un hachage : rien ne les écrase).
3. **Conservez `public_html/runtime-config.js`** — il porte l'`apiUrl` de production. Le fichier
   livré dans `dist/` contient `apiUrl: ""` et casserait la connexion à l'API s'il l'écrasait.
4. **Purgez le cache** : <kbd>Ctrl</kbd>+<kbd>F5</kbd> (et navigation privée), plus le cache
   CDN/LiteSpeed le cas échéant. Vérifiez dans l'onglet Réseau que les `assets/*.js` chargés
   portent les nouveaux hachages.

### Notes de version 1.3 (octobre 2026)

Ce qui change pour la mise à jour d'une instance en 1.2 (revue d'octobre 2026) :

| Élément | À faire |
|---|---|
| **Migration** `2026_10_22_090000_add_sequence_to_lots_paiement` | À jouer (§ D). Ajoute `lots_paiement.sequence` (défaut 1 : les lots existants deviennent des lots initiaux) et remplace la clé unique du lot ; la nouvelle clé est créée **avant** la suppression de l'ancienne, ce qui reste compatible avec la clé étrangère `mission_id` sous MySQL. Rapide, sans transformation de données. |
| **`vendor/`** | **Inchangé** (`composer.lock` non modifié) : pas de ré-upload. |
| **`.env`** | Facultatif : `DB_RESTORE_BINARY` si le client `mysql` n'est pas dans le PATH (§ 5.1, § 9.1). |
| **Fichiers de paiement** | Les **nouveaux** fichiers sont rangés dans `storage/app/private/paiements/{mission_id}/`. Les anciens lots gardent leur chemin enregistré et restent re-téléchargeables : **ne rien déplacer**. ⚠️ Avant la 1.3, deux missions de **même objet** partageaient le même nom de fichier : le fichier de la plus ancienne a pu être écrasé par celui de l'autre. Contrôlez le contenu d'un tel fichier avant de le redéposer. |
| **SPA** | À redéployer (§ F) : suivi des gros lots, lots complémentaires, boutons Valider / Rejeter du circuit par OM, page de maintenance, restauration des sauvegardes, lien « PDF de l'OM » sur la fiche de l'OM (seul accès au PDF officiel). |
| **Comportement** | L'état des reliquats est désormais signé au montant **payable** : un état des reliquats déjà **validé** garde son montant scellé ; seuls les récapitulatifs établis ou rafraîchis après la mise à jour suivent la nouvelle règle. |
| **PDF de l'OM** | Il reprend les signatures de l'état des avances qui couvre l'OM (même code de vérification) et, après un amendement, affiche la répartition réelle (« Avance engagée », « Reliquat (ajusté par amendement) » ou « Trop-perçu ») avec le rappel de l'amendement ; le taux journalier affiché exclut le transport. Code et gabarit seuls (`app/`, `resources/views/`), **aucune migration**. ➜ **Videz `storage/app/private/ordres/`** après la mise à jour (Gestionnaire de fichiers : sélectionner le contenu du dossier, puis *Delete*) : un OM sans état ni amendement garde le même nom de fichier en cache, et son ancien PDF serait encore servi. Ce dossier n'est qu'un cache : les PDF se régénèrent au premier téléchargement. |
| **File d'attente** | Rien à faire en `QUEUE_CONNECTION=sync` (défaut). En `database`, la tâche Cron `queue:work` doit nommer les trois files (`--queue=paiements,etats,default`, §9) : l'ancienne ligne ne traitait que `default`. |
| **Code seul, sans action** | Un échec définitif de génération en file remet les versements `EN_ATTENTE` ; la liste des sauvegardes est triée de la plus récente à la plus ancienne ; `backend/docs/openapi.yaml` décrit l'API réelle. |
| **Fichiers supprimés** | `app/Http/Requests/StoreOrdreMissionRequest.php` et `UpdateOrdreMissionRequest.php` (inutilisés). Un envoi FTP ne supprime rien sur le serveur : effacez-les de `anatt-api/` par le Gestionnaire de fichiers. Laissés en place, ils restent inertes (jamais chargés). |
| **Restauration** | `db:restore` purge la matrice des permissions : plus besoin de vider `storage/framework/cache/data/` après une restauration. |
| **Correctifs des 23 au 25/10 (n° 17 à 32)** | Code seul (`app/`, `bootstrap/app.php`, seeders, SPA) : **aucune migration** de plus, `vendor/` inchangé. Le SPA est à redéployer : menu latéral accentué, « Amender » sur un OM soldé dont le reliquat n'est pas payé. Ils ne reprennent pas les données : un OM dont le trop-perçu a été recouvré **avant** la mise à jour reste `VALIDE`. Les comptes désactivés ne reçoivent plus de notification ni d'e-mail. Les erreurs de l'API (405, 404, 403, 422, 500) répondent en JSON même sans en-tête `Accept` (n° 29). Les états signés ensuite impriment la fonction du compte signataire quand *Paramètres › Signatures* ne la renseigne pas (n° 30) ; `ComptesCircuitSeeder` ne rétrograde plus un super administrateur (n° 31) ; `GET /admin/settings` renvoie `{}`, et non `[]`, sans réglage (n° 32). Contrôles : `docs/checklist-post-deploiement.md` §06. |

### Notes de version 1.4 (décisions D1 à D4, 26/10/2026)

Ce qui change pour la mise à jour d'une instance en 1.3 :

| Élément | À faire |
|---|---|
| **Fuseau horaire (D2)** | L'application passe à l'heure du Bénin (`config/app.php` : `APP_TIMEZONE`, défaut `Africa/Porto-Novo`). La migration `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif` décale d'**une heure** toutes les colonnes date-heure déjà enregistrées (UTC → heure du Bénin) : chaque horodatage garde le même instant, les dates seules (départ, retour) ne bougent pas. ⚠️ **Mettez l'application en maintenance avant d'envoyer le code** et migrez dans la foulée (§ D, commande qui purge les caches d'abord) : une écriture faite entre l'envoi de `config/app.php` et la migration serait déjà à l'heure du Bénin, puis décalée une seconde fois. La migration ne se joue qu'une fois ; elle s'annule par `migrate:rollback --step=1` (−1 h). Pour rester en UTC : `APP_TIMEZONE=UTC` dans le `.env` **avant** de migrer (la migration ne fait alors rien). |
| **Notifications (D1)** | Code seul. Elles visent les comptes actifs qui détiennent la permission de l'étape (droits effectifs), plus seulement les profils système : rôles personnalisés et droits accordés individuellement sont notifiés, un droit retiré ne l'est plus, le super administrateur ne l'est pas. Avec la matrice par défaut, les destinataires ne changent pas. |
| **SPA (D3, D4)** | À redéployer (§ F) : dépendances inutilisées retirées (bundle inchangé à l'usage), et l'écran de génération recharge le lot après un échec (« Confirmer » ne reste plus affiché sur un lot passé en erreur). |
| **`vendor/`** | **Inchangé** (`composer.lock` non modifié). |

### G. Vérification

Déroulez la **checklist post-déploiement** (`docs/checklist-post-deploiement.md`) : santé `/up`,
connexion, puis la fonctionnalité livrée.

### H. Retour arrière

Si la vérification échoue :

1. **SPA** : videz `public_html/` et réextrayez le zip de l'étape A.2.
2. **Base** : phpMyAdmin > **Importer** le dump de l'étape A.1 (il contient les `DROP TABLE`,
   il remplace donc l'état actuel sans intervention manuelle).
3. **Code API** : re-uploadez le `anatt-api/` de la version précédente.

En sens inverse, une migration s'annule aussi par `php artisan migrate:rollback --step=1` (Cron
ou Terminal) : toutes les migrations du projet implémentent `down()`. Restaurer le dump reste malgré tout
plus sûr — un `rollback` ne rend pas les données qu'une migration a transformées.

---

## 10. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| **500** sur l'API, page blanche | `storage/` non inscriptible ou `APP_KEY` vide | Permissions 755 sur `storage/` + `bootstrap/cache/` ; remplir `APP_KEY` |
| Erreur **CORS** dans la console | origine du SPA non autorisée | `CORS_ALLOWED_ORIGINS=https://omsemo.anatt.bj` dans le `.env` API |
| Le SPA affiche **404** sur une route (ex. /agents) au rafraîchissement | `.htaccess` SPA absent | Vérifier `public_html/.htaccess` (fourni dans dist) |
| Appels API en **http** bloqués (mixed content) | API pas en HTTPS | AutoSSL sur le sous-domaine + `apiUrl` en `https://` |
| Connexion impossible / « Network error » | mauvaise `apiUrl` | Corriger `public_html/runtime-config.js` |
| `Class ... not found` au chargement | `vendor/` incomplet | Re-uploader `vendor/` (composer install --no-dev en local) |
| Sauvegarde « Échec » | mysqldump introuvable | Renseigner `DB_DUMP_BINARY` (chemin fourni par le support de l'hébergeur) |
| Restauration « Échec » … *client mysql introuvable* | `mysql` hors du PATH | Renseigner `DB_RESTORE_BINARY` (ex. `/usr/bin/mysql`) |
| Restauration « Sauvegarde de sécurité impossible » | `mysqldump` ou `exec()` indisponible | Corriger `DB_DUMP_BINARY` ; à défaut, restaurer par import phpMyAdmin (§ Mise à jour, H) |
| Restauration interrompue (timeout, page d'erreur) | base volumineuse, `max_execution_time` | Relancer par tâche Cron `artisan db:restore <fichier> --force` (§ 9.1) |
| Lenteur 1er chargement | config non cachée | (Optionnel) `php artisan optimize`, par Terminal ou tâche Cron ponctuelle : il met en cache la configuration, les routes, les événements et les vues. Une fois la configuration en cache, une modification du `.env` reste **sans effet** jusqu'au prochain `optimize:clear` (§ Mise à jour, E). |

### Spécifique à la mise à jour (migrations sans SSH)

| Symptôme | Cause probable | Solution |
|---|---|---|
| La tâche Cron ne produit **aucun log** | mauvais chemin PHP, ou log écrit dans un dossier non inscriptible | Testez d'abord `php -v` (§ Mise à jour, D, étape 1) ; écrivez le log dans `/home/anatt/` |
| Cron : `Could not open input file: artisan` | chemin de `artisan` erroné | Chemin **absolu** complet : `/home/anatt/anatt-api/artisan` |
| Cron : `PHP Parse error` ou `syntax error, unexpected ...` | Cron exécuté avec une **ancienne version de PHP** (alias `/usr/local/bin/php`) | Chemin explicite `/opt/cpanel/ea-php84/root/usr/bin/php` |
| `migrate` répond `Nothing to migrate` alors que des migrations manquent | le nouveau code n'a pas été uploadé **avant** de migrer | Uploadez `database/migrations/` (§ Mise à jour, C) puis relancez |
| `SQLSTATE[42S01] Table ... already exists` | la table a été créée à la main, mais sa ligne manque dans `migrations` | Insérez la ligne manquante : `INSERT INTO migrations (migration, batch) VALUES ('<nom_du_fichier_sans_.php>', (SELECT COALESCE(MAX(batch),0)+1 FROM migrations m));` puis relancez |
| 500 sur l'API **après** une migration réussie | caches applicatifs encore compilés sur l'ancien code | Purgez (§ Mise à jour, E) |
| Le SPA affiche encore l'ancienne version | bundle en cache navigateur/CDN | <kbd>Ctrl</kbd>+<kbd>F5</kbd>, navigation privée, purge LiteSpeed/Cloudflare ; vérifiez les hachages dans l'onglet Réseau |
| Import phpMyAdmin interrompu (« Maximum execution time ») | dump trop volumineux | Export **gzip**, ou dépôt FTP dans `/home/anatt/` + « Importer depuis le serveur », ou découpage table par table |
| Droits d'accès anciens **après un import phpMyAdmin** | la matrice des permissions est en cache sans expiration ; un import hors application ne la purge pas (seul `db:restore` le fait) | Videz `anatt-api/storage/framework/cache/data/` (§ Mise à jour, E) |
| Réglages perdus (part d'avance revenue à 70 %, SMTP vide) après une mise à jour | `storage/` écrasé : `settings.json` n'est pas dans la base | Restaurez `storage/app/settings.json` depuis la copie faite au § Mise à jour, A.4 |

---

## Récapitulatif des artefacts à uploader

### Première installation

| Source (local) | Destination (cPanel) |
|---|---|
| `backend/` (avec `vendor/`) | `anatt-api/` |
| `backend/.env.production.example` → rempli | `anatt-api/.env` |
| `frontend/dist/` (build local **ou** artefact CI `frontend-dist`) | `public_html/` |
| `anatt_prod.sql` | importé via phpMyAdmin |

### Mise à jour d'une instance en ligne

| Source (local) | Destination (cPanel) | Remarque |
|---|---|---|
| `backend/app/ bootstrap/ config/ database/ public/ resources/ routes/` | `anatt-api/` (écrasement) | **Avant** de migrer |
| `backend/vendor/` (zippé) | `anatt-api/` | **Seulement** si `composer.lock` a changé |
| `frontend/dist/` (sans `runtime-config.js`) | `public_html/` | **Après** la migration ; supprimer l'ancien `assets/` d'abord |
| — | `anatt-api/.env` | **Ne pas toucher** |
| — | `anatt-api/storage/` | **Ne pas toucher** (PDF, fichiers de paiement, sauvegardes, `settings.json`, paraphes) |
| — | `public_html/runtime-config.js` | **Ne pas toucher** (porte l'`apiUrl` de prod) |
