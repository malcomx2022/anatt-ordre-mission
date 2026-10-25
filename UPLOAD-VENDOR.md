# Zipper & uploader `vendor/` sur cPanel (rapide)

Le dossier `backend/vendor/` de production (95 paquets, plusieurs milliers de fichiers) est
**trop lent à envoyer fichier par fichier** en FTP. On l'envoie donc **zippé** (un seul fichier),
puis on l'extrait directement dans cPanel.

> `vendor/` n'est pas versionné (régénérable). À refaire après chaque changement de
> dépendances backend (`composer.json` / `composer.lock`).
>
> *Relu le 25/10/2026 contre le code.* Les chiffres viennent de `composer.lock` : 95 paquets de
> production, 51 de développement, et les bornes PHP. Un `vendor/` construit par la commande du §1
> a été démarré avec `APP_ENV=production`. La migration et les seeders de production passent ; la
> santé `/up`, la connexion et les exports de rapports en `.xlsx` (PhpSpreadsheet) et `.pdf`
> (DomPDF) répondent.

---

## 0. Faut-il renvoyer `vendor/` ?

**Seulement si `backend/composer.lock` a changé** depuis la version en ligne. Pour le savoir,
comparez le lock entre le commit déployé et le nouveau :

```powershell
cd C:\wamp64\www\anatt-ordre-mission
git diff --stat <commit-deploye> HEAD -- backend/composer.lock
```

Aucune ligne affichée → `vendor/` est inchangé : **ne rien envoyer**, passez directement au
déploiement du code (`DEPLOY-CPANEL.md` § *Mise à jour*).

> **Version 1.3** (revue d'octobre 2026, correctifs n° 1 à 29 compris) : `composer.lock` n'a pas
> changé depuis la 1.2 → **pas de ré-upload de `vendor/`** (cf. *Notes de version 1.3* de
> `DEPLOY-CPANEL.md`).

---

## 1. (Re)générer le `vendor/` de production

À faire **uniquement** si les dépendances ont changé (sinon passez à l'étape 2).

> **PHP 8.4 (8.4.x) obligatoire, en local comme sur le serveur, en 64 bits.** `composer.lock`
> l'encadre des deux côtés :
> - **minimum 8.4** : Symfony 8 (`clock`, `css-selector`, `event-dispatcher`, `string`,
>   `translation`) et `spatie/laravel-activitylog` exigent PHP ≥ 8.4 ;
> - **maximum 8.4.x** : `phpoffice/phpspreadsheet` 1.30.2 (fichiers Moov `.xlsx`, import Excel des
>   agents, exports) déclare `php <8.5.0`.
>
> Ce que cela provoque :
> - en local, sous PHP 8.3 **ou 8.5**, `composer install` refuse avec *« Your lock file does not
>   contain a compatible set of packages »*. Sélectionnez PHP 8.4 dans WAMP (ou appelez son
>   `php.exe`) ;
> - Composer écrit dans `vendor/composer/platform_check.php`, chargé à **chaque** requête, le
>   minimum 8.4 et l'exigence d'un PHP **64 bits**. Si le **site** cPanel tourne en 8.3 ou en
>   32 bits, l'API répond 500 sur tous les appels (`DEPLOY-CPANEL.md` §4) ;
> - ce contrôle ne vérifie **pas** le maximum : un site en PHP 8.5 démarrerait, mais hors de la
>   plage que déclare PhpSpreadsheet. Restez en 8.4.

```powershell
cd C:\wamp64\www\anatt-ordre-mission\backend
php -v                          # doit afficher PHP 8.4.x
php -r "echo PHP_INT_SIZE;"     # doit afficher 8 (PHP 64 bits)
php C:\composer\composer.phar install --no-dev --optimize-autoloader --prefer-dist
```

> `--no-dev` retire les 51 paquets de développement (Pest, PHPUnit, Pint, Telescope, Faker, Sail,
> Pail, Collision, Mockery…), ce qui est indispensable en prod. L'application le
> supporte : Telescope n'est enregistré qu'en environnement `local` **et** s'il est installé
> (`AppServiceProvider`), et le seeder de production n'utilise pas Faker.
>
> `--prefer-dist` récupère les archives des paquets. Si Composer retombe sur des **clones git**
> (réseau filtré, quota GitHub), chaque paquet embarque son dossier `.git` et `vendor/` gonfle
> de plusieurs centaines de Mo. Vérifiez qu'il n'y en a aucun avant de zipper :
>
> ```powershell
> (Get-ChildItem vendor -Recurse -Directory -Force -Filter .git).Count   # doit afficher 0
> ```
>
> Pour retravailler en local ensuite (tests, lint) : `php C:\composer\composer.phar install`.

---

## 2. Zipper `vendor/`

`zip` n'étant pas disponible, on utilise PowerShell (`Compress-Archive`) :

```powershell
cd C:\wamp64\www\anatt-ordre-mission\backend
if (Test-Path vendor.zip) { Remove-Item vendor.zip -Force }
Compress-Archive -Path vendor -DestinationPath vendor.zip -CompressionLevel Optimal -Force
```

Résultat : `backend\vendor.zip`. L'archive contient `vendor/` à sa racine,
donc l'extraction recrée correctement `anatt-api/vendor/`.

**Vérifiez le contenu avant l'envoi** (obligatoire) :
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
([System.IO.Compression.ZipFile]::OpenRead("$PWD\vendor.zip")).Entries |
  Where-Object FullName -eq 'vendor/autoload.php' | Select-Object FullName
```
Doit afficher `vendor/autoload.php`, avec une barre **oblique** (`/`).

Si rien ne s'affiche, l'archive est inutilisable sur le serveur. Les anciennes versions du module
d'archive de Windows PowerShell 5.1 écrivent les chemins avec des barres obliques inverses
(`vendor\autoload.php`). L'extraction cPanel, sous Linux, produit alors des fichiers nommés
littéralement `vendor\autoload.php` à la racine de `anatt-api/`, et l'API casse. Recréez l'archive
avec le `tar` intégré à Windows 10 et 11, qui écrit des `/`, puis refaites la vérification :

```powershell
Remove-Item vendor.zip -Force
tar -a -c -f vendor.zip vendor
```

---

## 3. Uploader en FTP

Déposez **`vendor.zip`** dans `/home/anatt/anatt-api/`
(via FileZilla : glissez le fichier dans le dossier `anatt-api`).

---

## 4. Extraire dans cPanel

1. **cPanel → Gestionnaire de fichiers** → dossier `anatt-api/`.
2. **Renommez** l'ancien `vendor/` en `vendor_old/` (clic droit → *Rename*). Extraire par-dessus
   laisserait en place les fichiers des paquets retirés ; `vendor_old/` sert aussi de retour arrière.
3. Clic droit sur `vendor.zip` → **Extract** (Extraire) → confirmez la destination `anatt-api/`.
4. Vérifiez que `anatt-api/vendor/autoload.php` existe.
5. **Videz** `anatt-api/bootstrap/cache/packages.php` et `services.php` (supprimez les deux
   fichiers, gardez le dossier) : ils listent les paquets découverts avec l'ancien `vendor/`, et
   Laravel les régénère seul à la requête suivante.
6. **Supprimez** `vendor.zip` du serveur (plus utile).

---

## 5. Vérifier

Ouvrez `https://api-omsemo.anatt.bj/up` → page de santé (200), puis connectez-vous au SPA.

- Tout est bon → **supprimez `vendor_old/`**.
- `Class ... not found` → `vendor/` incomplet : refaites l'extraction (ou re-zippez/ré-uploadez),
  et vérifiez que l'étape 4.5 a bien été faite.
- `Your Composer dependencies require a PHP version ">= 8.4.0"` → la version PHP du **site** est
  trop basse : *Select PHP Version* → 8.4.
- `Your Composer dependencies require a 64-bit build of PHP` → le PHP du site est en 32 bits :
  choisissez un PHP 8.4 en 64 bits.
- Des fichiers nommés `vendor\…` apparaissent à la racine de `anatt-api/` → l'archive a été écrite
  avec des `\` (§2) : supprimez-les, recréez l'archive avec `tar`, puis réextrayez.
- Impossible de corriger tout de suite → **retour arrière** : supprimez `vendor/`, renommez
  `vendor_old/` en `vendor/`, videz de nouveau `bootstrap/cache/packages.php` et `services.php`.

---

## Mémo des chemins

| Local | Serveur cPanel |
|---|---|
| `backend\vendor.zip` | `/home/anatt/anatt-api/vendor.zip` → extraire → `vendor/` |
| — | `/home/anatt/anatt-api/vendor_old/` (ancien `vendor/`, à supprimer après vérification) |

Voir `DEPLOY-CPANEL.md` pour le déploiement complet et `docs/runbook-mise-a-jour-cron.md` pour le
déroulé d'une mise à jour.
