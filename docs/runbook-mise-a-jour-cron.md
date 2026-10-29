# Runbook — mettre à jour l'instance en ligne (cPanel, tâches Cron)

> Déroulé du **jour J**, à suivre dans l'ordre, cases à cocher. Chaque commande est prête à coller
> dans **cPanel > Cron Jobs**. Pour le *pourquoi* de chaque étape, voir
> [`DEPLOY-CPANEL.md`](../DEPLOY-CPANEL.md) § *Mise à jour d'une version déjà en ligne*.
>
> **Contexte visé** : compte cPanel `anatt`, **pas d'accès SSH**, **tâches Cron disponibles**.
> SPA sur `https://omsemo.anatt.bj` (`public_html/`), API sur `https://api-omsemo.anatt.bj`
> (`/home/anatt/anatt-api/`, racine `anatt-api/public`).
>
> **Mise à jour vers la 1.3** (revue d'octobre 2026) : une seule migration
> (`2026_10_22_090000_add_sequence_to_lots_paiement`), `vendor/` **inchangé**, SPA **à redéployer**.
> Les encadrés « 1.3 » ci-dessous donnent ce qui est propre à cette version ; le détail est dans les
> *Notes de version 1.3* de `DEPLOY-CPANEL.md`.
>
> **Correctifs des 23 au 25/10/2026 (n° 17 à 32)** : ils font partie de la 1.3 et ne touchent que le
> code (`app/`, `bootstrap/app.php`, SPA). Ils n'ajoutent **aucune migration** et ne changent pas `vendor/`. Le SPA reste à
> redéployer : le menu latéral et la fiche OM ont changé. Leurs contrôles sont listés à l'étape 8.
>
> **Mise à jour vers la 1.4** (décisions D1 à D4, 26/10/2026) : une seule migration
> (`2026_10_26_090000_passer_horodatages_au_fuseau_applicatif`, qui décale d'une heure les horodatages
> existants : passage à l'heure du Bénin), **mise en maintenance obligatoire** (étape 3 bis), `vendor/`
> **inchangé**, cache des PDF d'OM à vider (étape 6), SPA **à redéployer**. Encadrés « 1.4 » ci-dessous ;
> détail dans les *Notes de version 1.4* de `DEPLOY-CPANEL.md`.
>
> **Réglages appliqués (28/10/2026)** : code seul (`app/`, `bootstrap/app.php`, `routes/`, SPA), **aucune
> migration**, `vendor/` inchangé, SPA **à redéployer**. Les réglages de *Paramètres* et de *Messagerie*,
> jusque-là sans effet, sont appliqués **dès l'envoi du code** — y compris ceux déjà enregistrés dans
> `settings.json` quand l'écran était « indicatif » (HTTPS forcé, mode maintenance, serveur SMTP, opérateur
> fermé, durée de session). À relire avant l'étape 4 (étape 1) ; contrôles à l'étape 8. Une erreur passagère
> de l'API au rechargement ne déconnecte plus (SPA). Un e-mail qui ne part pas (serveur SMTP faux ou
> injoignable) n'interrompt plus l'action qui notifie — en `QUEUE_CONNECTION=sync`, une signature répondait
> jusque-là en erreur 500 — : l'échec est seulement journalisé (`storage/logs/laravel-AAAA-MM-JJ.log`, journal quotidien de production) et la cloche reçue.
> Un réglage refusé par *Paramètres* affiche son motif (et non « Donnees invalides. »). *Complété le 29/10* :
> tout refus affiche son motif, une fois et en français — nouveau fichier `resources/lang/fr/validation.php`,
> envoyé avec `resources/` à l'étape 4 (rien à ajouter) ; contrôle à l'étape 8.
>
> **Dette technique soldée (29/10/2026, n° 50 à 55)** : code seul, **aucune migration**, rien à changer en
> base ni dans le `.env`, `vendor/` inchangé. Trois fichiers de l'API changent (liste à l'étape 4) ; le SPA est
> **à redéployer** (reconstruit avec Vue 3.5.44 stable, motif du serveur sur les refus du barème et des
> exports). Pas de maintenance obligatoire, pas de cache de PDF à vider. Contrôles à l'étape 8.
>
> *Relu le 24/10/2026 contre le code* : 34 migrations, `routes/console.php`, `bootstrap/app.php`,
> `vendor/composer/platform_check.php`. *Relu le 27/10/2026 (version 1.4)* : fuseau dans le `.env`,
> SPA servi pendant la maintenance, sortie de maintenance vérifiée, recette et retour arrière 1.4.

---

## Avant de commencer

- [ ] **PHP 8.4.x, 64 bits** sélectionné dans *Select PHP Version*. Ce n'est pas une préférence,
      c'est imposé : sous PHP 8.3 ou en 32 bits, l'API renvoie 500 sur tous les appels. **Ne prenez pas
      non plus 8.5** : `phpoffice/phpspreadsheet` (fichiers Moov, imports, exports) déclare
      `php <8.5.0`. Le site démarrerait quand même, mais hors de la plage supportée
      (`DEPLOY-CPANEL.md` §4).
- [ ] Nouveau `frontend/dist/` récupéré : artefact CI **`frontend-dist`** du run `main`, ou build local
      (`npm ci && npm run build`, **Node 20.19+ ou 22.12+** exigé par Vite 8).
- [ ] Nouveau code backend prêt à envoyer.
- [ ] `vendor/` régénéré **seulement si `composer.lock` a changé** (`composer install --no-dev
      --optimize-autoloader`, puis envoi zippé — cf. [`UPLOAD-VENDOR.md`](../UPLOAD-VENDOR.md)).
- [ ] Heure creuse choisie ; personne ne saisit d'OM pendant l'opération.
- [ ] **Aucun fichier de paiement en attente de dépôt** : sur `/paiements`, tous les lots `GENERE`
      sont soit déposés et confirmés, soit passés en erreur. Un lot généré pendant l'opération
      serait perdu en cas de retour arrière (la base revient à l'étape 1, pas le fichier).

> **1.3 et 1.4** : `composer.lock` n'a pas changé depuis la 1.2 → **ne pas** réenvoyer `vendor/`.
> Il n'a pas changé non plus avec les correctifs du 29/10 (dette technique).
>
> **29/10 (dette technique)** : `frontend/package.json` épingle désormais Vue **3.5.44** (au lieu de la
> 3.6 bêta). Pour un build local, installez avec `npm ci`, qui respecte le `package-lock.json`, et non
> `npm install`. L'artefact CI `frontend-dist` est construit de la même façon.
>
> **1.4** : prévenez les utilisateurs de l'heure de l'opération. Pendant la maintenance, c'est encore
> le SPA 1.3 qui est servi : il ne connaît pas l'écran *Maintenance* (apparu en 1.4) et ses écrans
> échouent sans message jusqu'à l'étape 7.

---

## 1 · Sauvegarder (non négociable)

- [ ] **Base** — phpMyAdmin > `anatt_missions` > **Exporter** > **Personnalisée** >
      cocher **« Ajouter DROP TABLE / VIEW »** > compression **gzip** > Exécuter.
      C'est cette case qui rend le dump réimportable tel quel en cas de retour arrière.
- [ ] **SPA** — Gestionnaire de fichiers > `public_html` > tout sélectionner > **Compresser**
      en `public_html_AAAA-MM-JJ.zip` > télécharger.
- [ ] **`.env`** — télécharger `anatt-api/.env` (il ne sera pas écrasé, mais il est irremplaçable).
      **1.4** : y chercher `APP_TIMEZONE`. Absente (cas normal, les gabarits antérieurs ne l'ont
      pas) → l'application prend l'heure du Bénin. Présente, elle doit valoir `Africa/Porto-Novo` ;
      `APP_TIMEZONE=UTC` n'est à garder que pour rester **volontairement** en UTC (la migration ne
      décale alors rien). Toute correction du `.env` se fait **avant** l'étape 5b.
- [ ] **Réglages et paraphes** — télécharger `anatt-api/storage/app/settings.json` et
      `anatt-api/storage/app/private/signatures/` : ils ne sont pas dans le dump de la base
      (`DEPLOY-CPANEL.md` § *Mise à jour*, A.4).
- [ ] **Relire `settings.json`** (réglages appliqués, 28/10) — ouvrir la copie téléchargée : chaque valeur
      qui s'y trouve **prendra effet** dès l'étape 4. Points à vérifier :
      - `"forceHttps": true` seulement si `https://api-omsemo.anatt.bj` répond déjà (sinon toutes les
        écritures de l'API seraient refusées en 403) ;
      - `"maintenanceMode": true` → l'application resterait fermée à tous sauf au super administrateur ;
      - `"smtpHost"` renseigné → ce serveur **remplacera** le `MAIL_*` du `.env` pour tous les e-mails ;
        le garder seulement s'il est juste (sinon le vider à l'étape 8, écran *Messagerie*). Faux, il ne
        bloque plus les signatures, mais **aucun** e-mail ne partirait — seul l'e-mail de test le révèle ;
      - `"mtnEnabled"` / `"moovEnabled"` à `false` → génération refusée pour cet opérateur ;
      - `"sessionDuration"`, `"maxLoginAttempts"` : valeurs voulues (5-1440 min, 3-20 tentatives).

      Une valeur à corriger avant la mise à jour : modifier `settings.json` dans le Gestionnaire de
      fichiers (clé à `false` ou chaîne vide), effet sous une minute.
- [ ] **Relever les tâches Cron permanentes** (cPanel > Cron Jobs) : planificateur `schedule:run`
      chaque minute (sauvegarde de 02:00 + purge des jetons) **ou** l'ancienne ligne `db:backup` à 02:00,
      et, si `QUEUE_CONNECTION=database`, `queue:work` chaque minute. Elles
      **restent en place** pendant toute l'opération ; seules les tâches temporaires de ce runbook
      seront créées puis supprimées.
      **1.3** : si la tâche `queue:work` existe, vérifiez qu'elle nomme les trois files
      (`queue:work --queue=paiements,etats,default --tries=3 --stop-when-empty`). Sans `--queue`, elle
      ne traite que `default` : gros lots et exports en file ne s'exécutent jamais. Corrigez-la
      (cPanel > Cron Jobs > *Edit*) ; ce n'est pas une tâche temporaire.

> Le bouton « Nouvelle sauvegarde » de l'écran *Sauvegardes* (`db:backup`) complète le dump
> phpMyAdmin mais ne le remplace pas : il dépend de `exec()` et de `mysqldump`, et son fichier reste
> sur le serveur. Le dump phpMyAdmin téléchargé est la seule sauvegarde garantie hors serveur.

---

## 2 · Relever ce qui manque à la base

phpMyAdmin > `anatt_missions` > onglet **SQL** :

```sql
SELECT migration, batch FROM migrations ORDER BY id;
```

- [ ] Comparer avec les noms de fichiers de `backend/database/migrations/` (sans `.php`).
- [ ] Noter les manquants — c'est exactement ce que l'étape 5 doit jouer.

> **1.3** : depuis une instance 1.2 à jour, seul `2026_10_22_090000_add_sequence_to_lots_paiement`
> manque. Une base antérieure au 14/09 manque aussi `2026_09_14_090000_renommer_categories_ordre_en_indice`.
>
> **1.4** : depuis une instance 1.3 à jour, seul `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif`
> manque (34 migrations au total). **Notez aussi** un horodatage témoin, pour l'étape 6 :
> `SELECT id, date_paiement FROM versements WHERE date_paiement IS NOT NULL ORDER BY id DESC LIMIT 1;`

---

## 3 · Vérifier le binaire PHP du Cron

`/usr/local/bin/php` est un **alias** qui pointe souvent vers une autre version que celle de
*Select PHP Version*. On ne le devine pas, on le vérifie.

**cPanel > Cron Jobs** — *Common Settings* : **Once Per Five Minutes** :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -v > /home/anatt/php-version.log 2>&1
```

- [ ] Attendre un passage, ouvrir `/home/anatt/php-version.log` : il doit afficher **`PHP 8.4.x`**
      (ni 8.3, ni 8.5).
- [ ] **Supprimer cette tâche** avant de passer à la suite.

> Log vide ou absent → le chemin PHP est faux. Renseignez l'adresse e-mail du Cron Job : cPanel
> vous enverra la sortie (et l'erreur) par courriel.

---

## 3 bis · Mettre en maintenance (version 1.4, ou si les notes de version l'exigent)

Tâche Cron ponctuelle, **avant** l'envoi du code :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan down > /home/anatt/maintenance.log 2>&1
```

- [ ] `/home/anatt/maintenance.log` affiche `Application is now in maintenance mode.`
- [ ] `https://api-omsemo.anatt.bj/api/auth/me` répond **503** (`{"message":"Service Unavailable"}`).
      Le SPA en place est encore l'ancien : un SPA **1.4 ou plus récent** affiche « Maintenance en
      cours » ; le SPA 1.3, servi pendant la mise à jour vers la 1.4, échoue sans message.
- [ ] Tâche supprimée.

> **`vendor/` réenvoyé dans la même opération** (jamais en 1.3 ni en 1.4) : ajoutez
> `--render="errors::503"` à la commande. Un `down` simple a besoin de `vendor/` pour répondre 503 :
> pendant l'échange, l'API répondrait 500 (`UPLOAD-VENDOR.md` §4).

> La sortie de maintenance est la fin de la commande de l'étape 5b (`artisan up`) : elle n'est jouée que
> si la migration réussit. Détail : `DEPLOY-CPANEL.md`, § Mise à jour, B bis.

## 4 · Envoyer le nouveau code de l'API

FTP vers `/home/anatt/anatt-api/` — écraser :

`app/` · `config/` · `database/` · `public/` · `resources/` · `routes/` ·
`bootstrap/app.php` · `bootstrap/providers.php`

**Ne pas toucher** :

| Chemin | Pourquoi |
|---|---|
| `anatt-api/.env` | Identifiants de base + `APP_KEY` |
| `anatt-api/storage/` | PDF générés, fichiers de paiement (`storage/app/private/paiements/`), sauvegardes (`storage/app/backups/`), logs |
| `anatt-api/bootstrap/cache/` | À **vider** (étape 6), jamais à écraser |
| `public_html/runtime-config.js` | Porte l'`apiUrl` de production |

- [ ] Code envoyé.
- [ ] `vendor/` réenvoyé **uniquement** si `composer.lock` a changé.
- [ ] **1.3** : supprimer par le Gestionnaire de fichiers
      `anatt-api/app/Http/Requests/StoreOrdreMissionRequest.php` et `UpdateOrdreMissionRequest.php`.
      Ces fichiers ont été retirés du dépôt, mais un envoi FTP écrase sans jamais rien effacer.
      Laissés en place, ils restent inertes : aucune route ne les charge.
- [ ] **29/10 (dette technique)** : l'envoi des dossiers ci-dessus comprend les trois fichiers modifiés :
      `app/Console/Commands/DatabaseBackup.php` (n° 53 : deux sauvegardes de la même seconde ne
      s'écrasent plus, la seconde prend le suffixe `-2`), `app/Http/Requests/StoreAgentRequest.php`
      (n° 54 : motif d'un agent sans indice ni salaire brut) et `resources/lang/fr/validation.php`
      (n° 55 : motifs des dates inversées). Sur une instance déjà à jour du code précédent, envoyer ces
      trois fichiers seuls suffit ; en cas de doute, envoyer les dossiers entiers. Rien d'autre côté API.

> Le code doit être en place **avant** de migrer : les fichiers de migration que vous venez
> d'envoyer sont précisément ce que l'étape 5 exécutera.

---

## 5 · Migrer la base

> **Une seule tâche Cron temporaire active à la fois.** Créez la suivante après avoir supprimé la
> précédente, sinon deux tâches se disputent les fichiers de log. Les tâches permanentes relevées à
> l'étape 1 (`db:backup`, `queue:work`) écrivent ailleurs et restent en place.

### 5a · Lister (ne modifie rien)

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate:status > /home/anatt/migration-status.log 2>&1
```

- [ ] `/home/anatt/migration-status.log` listé : les `Pending` correspondent à l'étape 2.
- [ ] Tâche supprimée.

### 5b · Migrer et purger les caches

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear > /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate --force >> /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear >> /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan up >> /home/anatt/migration-run.log 2>&1
```

- [ ] `/home/anatt/migration-run.log` : chaque migration attendue affiche **`DONE`**
      (**1.3** : `2026_10_22_090000_add_sequence_to_lots_paiement … DONE`, quelques secondes, aucune
      donnée transformée — les lots existants prennent `sequence = 1` ; **1.4** :
      `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif … DONE`, qui décale d'une heure
      les horodatages existants — application **en maintenance** pendant l'envoi du code et la
      migration, cf. `DEPLOY-CPANEL.md`, *Notes de version 1.4*).
- [ ] Le log se termine par `Application is now live.` (sortie de maintenance) ou `Application is already up.`
      S'il s'arrête avant (migration en erreur), l'application **reste en maintenance**, c'est voulu :
      lire l'erreur, corriger ou revenir en arrière, puis jouer `artisan up` par une tâche ponctuelle
      (`DEPLOY-CPANEL.md`, § Mise à jour, B bis).
- [ ] **Tâche supprimée** — sinon elle se rejoue toutes les cinq minutes et **écrase le log**.

> `--force` remplace la confirmation interactive, impossible en Cron. La purge **avant** `migrate`
> fait lire la configuration à jour (fuseau de la 1.4 compris) même si elle était en cache.
> **Aucun `%` dans la commande** : Cron l'interprète comme un saut de ligne et tronque la tâche.
>
> **29/10 (dette technique)** : aucune migration attendue. Jouez la commande quand même : le log affiche
> `Nothing to migrate` puis `Application is already up.`, et les caches sont purgés sur le nouveau code.

---

## 6 · Vérifier l'API avant de toucher au SPA

- [ ] `https://api-omsemo.anatt.bj/up` → page de santé Laravel en **200**. Il répond 200 même en
      maintenance : il ne prouve pas la sortie de maintenance.
- [ ] Un POST sur `/api/auth/login` répond en JSON.
- [ ] `https://api-omsemo.anatt.bj/api/auth/me` ouvert dans le navigateur répond **401 en JSON**
      (`{"message":"Non authentifie."}`). Un **503** = toujours en maintenance (`artisan up` non joué,
      étape 5b) ; **1.3** : une page 500 = l'ancien `bootstrap/app.php` est encore en place (étape 4).
- [ ] **1.4** : dans phpMyAdmin, l'horodatage témoin de l'étape 2 a pris **une heure** de plus
      (même instant, désormais exprimé à l'heure du Bénin). Inchangé = migration jouée avec une
      configuration en UTC : `APP_TIMEZONE` du `.env` ou cache non purgé (`DEPLOY-CPANEL.md`,
      *Dépannage*).

> **500 partout** avec « Your Composer dependencies require a PHP version ">= 8.4.0" » → la
> version PHP du **site** (pas celle du Cron) est trop basse : *Select PHP Version* → 8.4.
> Avec « … require a 64-bit build of PHP » → le PHP choisi est en 32 bits : choisissez un PHP 8.4
> en 64 bits.
>
> **500** sans ce message → caches encore compilés sur l'ancien code. Si `optimize:clear` n'a pas
> tourné, videz à la main (en gardant les dossiers et leur `.gitignore`) :
> `anatt-api/bootstrap/cache/*.php`, `anatt-api/storage/framework/views/*.php`,
> `anatt-api/storage/framework/cache/data/`.

- [ ] **1.3 et 1.4** : vider le cache des PDF d'OM — Gestionnaire de fichiers >
      `anatt-api/storage/app/private/ordres/` > sélectionner tout le contenu > *Delete* (garder le
      dossier). En 1.3, le PDF de l'OM a changé (signatures de l'état, répartition après amendement,
      taux hors transport) et un OM sans état ni amendement garderait sinon son ancien PDF. En 1.4, un
      PDF resté en cache imprimerait ses heures de signature en UTC, une heure plus tôt que l'état.
      Les PDF se régénèrent au premier téléchargement ; ne touchez pas à `paiements/` ni à `signatures/`.

---

## 7 · Mettre à jour le SPA

- [ ] **Supprimer** l'ancien `public_html/assets/` et `public_html/index.html`
      (les noms portent un hachage : rien ne les écrase, ils s'accumuleraient).
- [ ] Envoyer le contenu du nouveau `dist/` dans `public_html/`.
- [ ] **Ne pas écraser `public_html/runtime-config.js`** — celui de `dist/` contient `apiUrl: ""`
      et couperait le SPA de l'API.
- [ ] <kbd>Ctrl</kbd>+<kbd>F5</kbd> + navigation privée ; purger le cache LiteSpeed/Cloudflare
      le cas échéant.
- [ ] Onglet Réseau : les `assets/*.js` chargés portent les **nouveaux** hachages.

---

## 8 · Recette

- [ ] Connexion en Super Administrateur.
- [ ] Tableau de bord : KPIs et graphiques chargés (preuve que le SPA atteint l'API en HTTPS).
- [ ] `/ordres`, `/missions`, `/etats`, `/paiements` rendent leur écran, sans page blanche.
- [ ] **1.3** : `/paiements` affiche les lots existants, toujours re-téléchargeables ;
      `/admin/sauvegardes` propose **Télécharger** et **Restaurer**. Ne générez **aucun** fichier de
      paiement et ne restaurez **rien** pour tester : ces contrôles-là se font en recette (section
      *06 › Sur l'instance de recette uniquement* de la checklist).
- [ ] **1.3** : ouvrir la fiche d'un OM existant. Le lien **« PDF de l'OM »** apparaît dans l'en-tête,
      à côté de « Historique », et ouvre l'aperçu. **Télécharger PDF** produit le document, régénéré
      puisque le cache a été vidé à l'étape 6. Ce contrôle ne modifie aucune donnée.
- [ ] **Correctifs n° 17 à 32** (contrôles en lecture seule) :
      - le menu latéral affiche ses libellés **accentués** (« États de paiement », « Générer MTN »,
        « Régularisations », « Barèmes », « Paramètres »). Sans accents, c'est l'ancien bundle qui
        est servi : reprendre l'étape 7 ;
      - sur la fiche d'un OM **soldé** dont le reliquat n'est ni payé ni recouvré (part d'avance à
        100 %), **« Amender »** est proposé. Il est masqué sinon.

      N'amendez rien pour tester. Le détail des contrôles se trouve dans la section *06 › Correctifs
      des 23 au 25/10/2026* de la checklist, et ceux qui créent des données dans sa sous-section
      *Sur l'instance de recette uniquement*.
- [ ] **1.4** (contrôles en lecture seule) :
      - *Paramètres* affiche le fuseau `Africa/Porto-Novo`, en lecture seule ;
      - *Historique des paiements* (`/paiements/historique`) : le paiement témoin de l'étape 2 affiche
        la **même** heure qu'avant la mise à jour. Une heure de moins à l'écran = migration jouée
        avec une configuration en UTC (contrôle de l'étape 6 ; `DEPLOY-CPANEL.md`, *Dépannage*) ;
      - sur une mission validée par le circuit, l'heure de signature du DG est la même sur le PDF de
        l'état et sur celui d'un de ses OM (cache vidé à l'étape 6) ;
      - *Configuration e-mail* (`/admin/email`) liste les 7 notifications et leurs destinataires.

      Les autres contrôles 1.4 sont dans la section *06 › Version 1.4* de la checklist.
- [ ] **Réglages appliqués (28/10)** :
      - *Messagerie* (`/admin/email`) : plus de bandeau « indicatif ». **Envoyer un e-mail de test** →
        reçu dans la boîte du super administrateur (serveur de l'écran, ou `.env` si le champ est vide).
        Un échec affiche le message du serveur de messagerie : corriger, ou vider le serveur ;
      - recharger une page (<kbd>F5</kbd>) : on reste connecté, sur la même page ;
      - *Paramètres* : plus aucune mention « indicatif » ; ne rien enregistrer sans avoir relu chaque onglet —
        « Enregistrer tout » enregistre **et applique** tous les onglets d'un coup. Un refus affiche son
        motif (ex. « Le pourcentage d'avance doit être un entier entre 0 et 100. ») et n'enregistre rien ;
      - après un échec d'envoi, le journal du jour (`storage/logs/laravel-AAAA-MM-JJ.log`) porte « SendNotification: echec envoi email » :
        l'action a abouti, seul l'e-mail manque — corriger *Messagerie* puis renvoyer un e-mail de test ;
      - *Utilisateurs › Nouvel utilisateur* avec l'adresse d'un compte existant → « Cette adresse e-mail est
        déjà utilisée. » (une clé `validation.unique` à l'écran : `resources/lang/` n'a pas été envoyé).

      Les autres contrôles sont dans la section *06 › Réglages de Paramètres et part de la mission* de la checklist.
- [ ] **Dette technique (29/10, contrôles en lecture seule)** :
      - *Personnel › Nouvel agent* sans indice ni salaire brut → « L'indice ou le salaire brut est requis pour
        déterminer la catégorie. » ; rien n'est créé ;
      - *Rapports › Export* avec « Du » postérieur à « Au » → « La date « Au » doit être postérieure à « Du ». »
        et bouton grisé ;
      - le parcours de fumée de la checklist (§05) passe sans page blanche (SPA reconstruit en Vue 3.5.44).

      Ne testez ni le barème, ni la retranscription, ni les sauvegardes en production : ces contrôles (taux
      négatif, mission aux dates inversées, deux sauvegardes rapprochées, restauration avec les binaires de
      l'hébergeur, recette automatisée `scripts/recette/recette-api.cjs`) se font sur l'instance de recette,
      section *06 › Dette technique soldée* de la checklist.
- [ ] Console navigateur sans erreur rouge, ni CORS ni Mixed-Content.
- [ ] La fonctionnalité livrée par cette version se comporte comme attendu
      (voir la section *06* de la checklist et les *Notes de version* 1.3 / 1.4 de `DEPLOY-CPANEL.md`).
- [ ] Checklist complète : [`checklist-post-deploiement.md`](checklist-post-deploiement.md).

---

## 9 · Ménage

- [ ] Toutes les tâches Cron temporaires supprimées (étapes 3, 3 bis, 5a, 5b).
- [ ] Tâches Cron **permanentes** toujours présentes (`schedule:run` chaque minute ou `db:backup` 02:00,
      `queue:work --queue=paiements,etats,default` le cas échéant). Le lendemain, un nouveau fichier
      daté apparaît dans `anatt-api/storage/app/backups/`.
- [ ] *(Facultatif)* Si seule la ligne `db:backup` existe, la remplacer par
      `* * * * *  /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan schedule:run >> /dev/null 2>&1`.
      Elle ajoute la purge quotidienne des jetons Sanctum expirés (`DEPLOY-CPANEL.md` §9). **Ne gardez
      pas les deux**, sinon la sauvegarde est prise deux fois.
- [ ] `/home/anatt/php-version.log`, `maintenance.log`, `migration-status.log`, `migration-run.log` supprimés.
- [ ] Sauvegardes de l'étape 1 archivées hors du serveur.

---

## Retour arrière

Si la recette échoue et que le correctif n'est pas immédiat :

1. **SPA** — vider `public_html/`, réextraire le zip de l'étape 1.
2. **Base** — phpMyAdmin > **Importer** le dump de l'étape 1 (ses `DROP TABLE` remplacent l'état
   actuel sans intervention manuelle).
3. **Code API** — réenvoyer le `anatt-api/` de la version précédente.
4. **Caches et maintenance** — tâche Cron ponctuelle `artisan optimize:clear && artisan up` (chemins
   complets, comme à l'étape 5b) : la configuration en cache serait celle de la version abandonnée,
   et l'application peut être restée en maintenance.

> `migrate:rollback --step=1` existe (les 34 migrations implémentent toutes `down()`), mais
> restaurer le dump reste plus sûr : un rollback ne rend pas les données qu'une migration a
> transformées.
>
> **1.3** : le `down()` de `add_sequence_to_lots_paiement` recrée l'ancienne clé unique
> (mission × tranche × opérateur). Il **échoue** dès qu'un lot complémentaire (`sequence` ≥ 2) existe
> — raison de plus pour revenir par le dump. Les fichiers de paiement écrits après la mise à jour
> (`storage/app/private/paiements/{mission_id}/`) restent sur le disque mais ne sont plus référencés
> par la base restaurée : ne les redéposez pas sans les avoir rapprochés des lots.
>
> **1.4** : le dump de l'étape 1 est en UTC ; il ne va qu'avec le code **1.3** (`config/app.php` en UTC).
> Ne réimportez jamais ce dump sous le code 1.4 sans rejouer la migration du fuseau, et ne gardez pas
> une base migrée sous le code 1.3 : les heures seraient décalées d'une heure. Deux voies cohérentes :
> le dump **et** le code 1.3 (les écritures faites depuis la mise à jour sont perdues), ou
> `migrate:rollback --step=1` (−1 h sur toute la base, écritures récentes comprises, qui repasse en
> UTC) **puis** le code 1.3 — dans cet ordre : le rollback a besoin du fichier de migration et calcule
> son décalage sur la configuration 1.4 (heure du Bénin).
> Videz aussi `storage/app/private/ordres/` : les PDF régénérés sous la 1.4 imprimeraient l'heure du Bénin.
>
> **Réglages appliqués (28/10)** : aucune migration à défaire. Revenir au code précédent rend les réglages
> de *Paramètres* et de *Messagerie* de nouveau sans effet ; `settings.json` n'est pas modifié (le restaurer
> depuis la copie de l'étape 1 seulement si une valeur a été changée pendant la recette).
>
> **Dette technique (29/10)** : aucune migration à défaire, base inchangée. Réenvoyer les trois fichiers de
> l'étape 4 dans leur version précédente et réextraire le SPA précédent suffit. L'ancien `db:backup` reprend
> alors le défaut n° 53 (une sauvegarde prise dans la même seconde qu'une autre l'écrase) : évitez d'enchaîner
> sauvegardes et restaurations tant que le correctif n'est pas revenu. Les sauvegardes suffixées `-2` créées
> entre-temps restent restaurables : `db:restore` accepte tout fichier `.sql` ou `.sql.gz` du dossier.
