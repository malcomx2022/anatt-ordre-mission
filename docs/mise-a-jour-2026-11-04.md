# Guide de mise à jour de l'instance en ligne — 4 novembre 2026

> Mise à jour de l'instance de production (`https://omsemo.anatt.bj`, API `https://api-omsemo.anatt.bj`)
> vers l'état de `main` au 04/11/2026 : **correctifs de la revue fonctionnelle** (PR #206) et **correctifs de
> sécurité des dépendances** (PR #211, dont un contournement critique d'authentification dans Laravel).
> À suivre dans l'ordre, cases à cocher. Il complète le runbook générique
> [`runbook-mise-a-jour-cron.md`](runbook-mise-a-jour-cron.md), auquel il renvoie pour les gestes communs.
>
> **Contexte** : compte cPanel `anatt`, **pas d'accès SSH**, tâches Cron disponibles, API dans
> `/home/anatt/anatt-api/`, SPA dans `public_html/`.

---

## Ce que cette mise à jour change

| Élément | Change ? | Détail |
|---|---|---|
| Code de l'API | **Oui, 4 fichiers** | `app/Http/Controllers/Api/AuthController.php`, `app/Jobs/GenererLotPaiementJob.php`, `app/Services/EtatPaiementService.php`, `app/Services/LotPaiementService.php` |
| `vendor/` (dépendances PHP) | **Oui, à renvoyer** | `composer.lock` a changé : Laravel 12.71, Guzzle 7.16, PhpSpreadsheet 1.30.7, Symfony, DomPDF, Laravel Excel… (même nombre de paquets : 95) |
| Base de données | Non | Aucune migration |
| `.env`, `config/`, `routes/`, `bootstrap/` | Non | Rien à modifier |
| `settings.json`, paraphes | Non | Rien à modifier |
| SPA (`public_html/`) | **Non** | Le build est identique octet pour octet à celui du 30/10 (vérifié) : ne rien envoyer |
| Cache des PDF d'OM | Non | Les PDF ne changent pas |

**Durée** : environ 30 minutes, dont 5 à 10 minutes de maintenance (échange de `vendor/`).

**Ce que les utilisateurs remarqueront** : presque rien. « Rafraîchir » un état de paiement déjà à jour
n'envoie plus de nouvelle notification au contrôleur. Un gros lot de paiement dont la génération en file
échoue ne touche plus un lot complémentaire généré entre-temps. Les failles corrigées ne se voient pas.

> **Prérequis : l'instance est au niveau du 29/10/2026** (dette technique soldée, défauts n° 50 à 58).
> Si elle est plus ancienne (1.2, 1.3 ou 1.4), la mise à jour doit d'abord appliquer les encadrés du runbook
> pour les versions manquantes : migrations, SPA à redéployer, cache des PDF, etc. Dans ce cas, déroulez le
> runbook complet et ajoutez-y les gestes de ce guide (4 fichiers et `vendor/`). Pour connaître la version en
> ligne : *Paramètres* affiche le fuseau `Africa/Porto-Novo` depuis la 1.4 ; le fichier
> `anatt-api/app/Console/Commands/DatabaseBackup.php` gère le suffixe `-2` depuis le 29/10.

---

## 0 · Préparer (la veille ou avant l'opération)

### 0.1 Sur le poste de déploiement (Windows, WAMP)

- [ ] Récupérer `main` à jour (`git pull`), commit `27c5da5` ou plus récent.
- [ ] **PHP 8.4 en 64 bits** dans le terminal : `php -v` affiche `PHP 8.4.x`, `php -r "echo PHP_INT_SIZE;"`
      affiche `8`. Sous 8.3 ou 8.5, `composer install` refuse le lock (`UPLOAD-VENDOR.md` §1).
- [ ] Construire le `vendor/` de production :

      ```powershell
      cd C:\wamp64\www\anatt-ordre-mission\backend
      php C:\composer\composer.phar install --no-dev --optimize-autoloader --prefer-dist
      php C:\composer\composer.phar audit
      ```

      `composer audit` doit afficher **« No security vulnerability advisories found. »**
- [ ] Aucun dossier `.git` dans `vendor/` :
      `(Get-ChildItem vendor -Recurse -Directory -Force -Filter .git).Count` affiche `0`.
- [ ] Contrôle de version : `vendor\laravel\framework\src\Illuminate\Foundation\Application.php` contient
      `const VERSION = '12.71.0';`.
- [ ] Zipper et vérifier l'archive (`UPLOAD-VENDOR.md` §2) : `vendor/autoload.php` doit apparaître avec une
      barre **oblique** `/`. Sinon, recréer l'archive avec `tar -a -c -f vendor.zip vendor`.
- [ ] Préparer les **4 fichiers** de l'API listés plus haut, avec leur arborescence (`app/…`).

> Après le zip, pour retravailler en local : `php C:\composer\composer.phar install` (réinstalle les
> paquets de développement).

### 0.2 Sur l'instance

- [ ] **PHP 8.4.x, 64 bits** dans *Select PHP Version* (ni 8.3, ni 8.5).
- [ ] **Aucun lot `GENERE` en attente de dépôt** sur `/paiements` : chaque lot généré est soit déposé et
      confirmé, soit passé en erreur.
- [ ] **Aucune génération de gros lot en cours** : la file est vide. En `QUEUE_CONNECTION=sync` (défaut
      cPanel), rien à vérifier. En `database`, la table `jobs` est vide (phpMyAdmin :
      `SELECT COUNT(*) FROM jobs;` → `0`). *Pourquoi* : le nouveau `GenererLotPaiementJob` retrouve le lot
      visé dans une clé de cache posée au début du traitement. Un job mis en file avant la mise à jour et
      qui échouerait après ne repasserait pas son lot en erreur : il faudrait le faire à la main
      (« Marquer en erreur »).
- [ ] Heure creuse choisie, utilisateurs prévenus (5 à 10 minutes d'indisponibilité).

---

## 1 · Sauvegarder

Comme au runbook, étape 1 :

- [ ] **Base** : phpMyAdmin, export personnalisé, **« Ajouter DROP TABLE / VIEW »**, gzip. Aucune migration
      n'est jouée : ce dump ne sert qu'en cas d'incident imprévu.
- [ ] **`.env`** téléchargé.
- [ ] **Les 4 fichiers actuels** téléchargés depuis `anatt-api/app/…` : ce sont eux qu'on remettrait en cas
      de retour arrière.
- [ ] `vendor/` actuel : **pas besoin de le télécharger**, il sera conservé sur le serveur en `vendor_old/`
      (étape 4).

---

## 2 · Vérifier le binaire PHP du Cron

Runbook, étape 3 : tâche ponctuelle
`/opt/cpanel/ea-php84/root/usr/bin/php -v > /home/anatt/php-version.log 2>&1`.

- [ ] `php-version.log` affiche **`PHP 8.4.x`**.
- [ ] Tâche supprimée.

---

## 3 · Mettre en maintenance (obligatoire : `vendor/` change)

Tâche Cron ponctuelle, **avec `--render`** (sans lui, l'API répondrait 500 pendant l'échange de `vendor/`) :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan down --render="errors::503" > /home/anatt/maintenance.log 2>&1
```

- [ ] `maintenance.log` affiche `Application is now in maintenance mode.`
- [ ] `https://api-omsemo.anatt.bj/api/auth/me` répond **503**, et le SPA affiche son écran *Maintenance*.
- [ ] Tâche supprimée.

---

## 4 · Envoyer le code et échanger `vendor/`

- [ ] FTP : envoyer les **4 fichiers** dans `/home/anatt/anatt-api/`, en écrasant les anciens (mêmes
      chemins). Ne rien toucher d'autre : `.env`, `storage/`, `bootstrap/cache/` (vidé plus bas), `public_html/`.
- [ ] FTP : déposer `vendor.zip` dans `/home/anatt/anatt-api/`.
- [ ] Gestionnaire de fichiers, dans `anatt-api/` (`UPLOAD-VENDOR.md` §4, étapes 2 à 6) :
  - [ ] renommer `vendor/` en **`vendor_old/`** ;
  - [ ] extraire `vendor.zip` dans `anatt-api/` ;
  - [ ] vérifier que `anatt-api/vendor/autoload.php` existe ;
  - [ ] ouvrir `anatt-api/vendor/laravel/framework/src/Illuminate/Foundation/Application.php` et
        vérifier `const VERSION = '12.71.0';` ;
  - [ ] supprimer `anatt-api/bootstrap/cache/packages.php` et `services.php` (garder le dossier) ;
  - [ ] supprimer `vendor.zip`.

---

## 5 · Purger les caches et sortir de maintenance

Tâche Cron ponctuelle (même commande que l'étape 5b du runbook, qui se termine par la sortie de
maintenance) :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear > /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate --force >> /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear >> /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan up >> /home/anatt/migration-run.log 2>&1
```

- [ ] `migration-run.log` affiche **`Nothing to migrate`**. Une migration listée à la place = l'instance
      n'était pas au niveau du 29/10 : voir le *Prérequis*.
- [ ] Le log se termine par **`Application is now live.`** S'il s'arrête avant, l'application reste en
      maintenance : lire l'erreur. Une `Class … not found` vient d'un `vendor/` incomplet : refaire
      l'étape 4. Sinon, voir le *Retour arrière*.
- [ ] **Tâche supprimée** (sinon elle se rejoue toutes les cinq minutes).

---

## 6 · Vérifier l'API

- [ ] `https://api-omsemo.anatt.bj/up` → **200**.
- [ ] `https://api-omsemo.anatt.bj/api/auth/me` → **401 en JSON** (`{"message":"Non authentifie."}`).
      Un 503 = toujours en maintenance (étape 5) ; un 500 = voir `UPLOAD-VENDOR.md` §5 (message
      « PHP version » : PHP du **site** trop bas ; « 64-bit » : PHP en 32 bits).
- [ ] Le SPA quitte son écran *Maintenance* après rechargement (<kbd>Ctrl</kbd>+<kbd>F5</kbd>).

---

## 7 · Recette (en lecture seule sur la production)

- [ ] Connexion en super administrateur, tableau de bord chargé.
- [ ] Parcours de fumée de la checklist, §05 : `/ordres`, `/missions`, `/etats`, `/paiements` sans page blanche.
- [ ] **PDF** (DomPDF mis à jour) : télécharger le PDF d'un OM existant et le PDF d'un état de paiement :
      ils s'ouvrent, avec la même mise en page qu'avant.
- [ ] **Excel** (PhpSpreadsheet mis à jour) : *Rapports › Export* au format `.xlsx` sur une courte période :
      le fichier s'ouvre. Re-télécharger un lot Moov existant depuis `/paiements` : le `.xlsx` s'ouvre.
      Ne générez aucun nouveau lot pour tester.
- [ ] **E-mail** (Symfony Mailer mis à jour) : *Messagerie › Envoyer un e-mail de test* → reçu.
- [ ] **Sessions** : se déconnecter puis se reconnecter. Un jeton ouvert avant la mise à jour reste valable.
- [ ] Console du navigateur sans erreur rouge.

**Sur l'instance de recette uniquement** (ces contrôles créent ou modifient des données) :

- [ ] **Changement forcé de mot de passe** : créer un compte de test (*Utilisateurs › Nouvel utilisateur*),
      se connecter avec le mot de passe provisoire, le changer à l'écran demandé, puis se reconnecter :
      l'ancien mot de passe est refusé, le nouveau accepté. Même chose avec « Mot de passe oublié ».
- [ ] **États de paiement** : sur une mission en brouillon, « Rafraîchir » sans rien modifier n'ajoute
      aucune notification au contrôleur. Après la retranscription d'un nouvel OM dans la mission,
      « Rafraîchir » le notifie de nouveau.
- [ ] **Lots de paiement** (correctif de concurrence) : générer un lot, puis ouvrir sa page de génération
      dans **deux onglets**. Confirmer le paiement dans le premier ; dans le second, resté sur l'ancien
      affichage, cliquer « Marquer en erreur » : refusé avec « Un lot confirmé ne peut pas être marqué en
      erreur. », et le lot reste `CONFIRME` après rechargement.
- [ ] *(Facultatif)* Recette automatisée : `scripts/recette/recette-api.cjs`, jamais contre la production.
      Elle exige les 8 comptes de démonstration et une API servie en HTTP : en pratique une copie de la base
      de recette sur un poste (prérequis détaillés dans la checklist, *06 › Dette technique soldée*). La CI
      la joue déjà à chaque PR.

Checklist complète : [`checklist-post-deploiement.md`](checklist-post-deploiement.md), section
*06 › Maintenance du 04/11/2026*.

---

## 8 · Ménage

- [ ] Tout est bon → **supprimer `anatt-api/vendor_old/`**.
- [ ] Toutes les tâches Cron temporaires supprimées (étapes 2, 3, 5) ; les tâches permanentes
      (`schedule:run` ou `db:backup`, `queue:work` le cas échéant) toujours présentes.
- [ ] `/home/anatt/php-version.log`, `maintenance.log`, `migration-run.log` supprimés.
- [ ] Sauvegardes de l'étape 1 archivées hors du serveur.
- [ ] Noter la date de mise à jour et le commit déployé (`27c5da5` ou plus récent), qui servira à la
      prochaine comparaison du `composer.lock` (`UPLOAD-VENDOR.md` §0).

---

## Retour arrière

La base n'est pas modifiée par cette mise à jour : le retour arrière ne touche que des fichiers.

1. **Maintenance** : tâche ponctuelle `artisan down --render="errors::503"` (étape 3).
2. **`vendor/`** : supprimer `vendor/`, renommer `vendor_old/` en `vendor/`, supprimer
   `bootstrap/cache/packages.php` et `services.php`.
3. **Code** : renvoyer les 4 fichiers sauvegardés à l'étape 1.
4. **Caches et sortie de maintenance** : tâche ponctuelle
   `artisan optimize:clear && artisan up` (chemins complets, comme à l'étape 5).

> Revenir en arrière **rouvre les failles corrigées**, dont le contournement d'authentification de Laravel.
> À ne faire que pour rétablir le service, en replanifiant la mise à jour au plus vite. Le dump de la base
> n'est à réimporter que si un incident sans rapport a abîmé les données pendant l'opération.

---

## Pour les prochaines mises à jour

Avant chaque mise en production : `composer audit` (dans `backend/`) et `npm audit` (dans `frontend/`). De
nouvelles failles sont publiées régulièrement ; les corriger dans les versions majeures en place, comme le
04/11/2026, puis renvoyer `vendor/` si `composer.lock` a changé.
