# Zipper & uploader `vendor/` sur cPanel (rapide)

Le dossier `backend/vendor/` (~64 Mo, ~8900 fichiers) est **trop lent à envoyer fichier
par fichier** en FTP. On l'envoie donc **zippé** (≈14 Mo, 1 seul fichier), puis on
l'extrait directement dans cPanel.

> `vendor/` n'est pas versionné (régénérable). À refaire après chaque changement de
> dépendances backend (`composer.json` / `composer.lock`).

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

> **Version 1.3** (revue d'octobre 2026) : `composer.lock` n'a pas changé depuis la 1.2 →
> **pas de ré-upload de `vendor/`** (cf. *Notes de version 1.3* de `DEPLOY-CPANEL.md`).

---

## 1. (Re)générer le `vendor/` de production

À faire **uniquement** si les dépendances ont changé (sinon passez à l'étape 2).

> **PHP 8.4 obligatoire, en local comme sur le serveur.** `composer.lock` épingle des paquets
> qui exigent PHP ≥ 8.4 (Symfony 8, spatie/laravel-activitylog) :
> - en local, sous PHP 8.3, `composer install` refuse avec *« Your lock file does not contain a
>   compatible set of packages »* → sélectionnez PHP 8.4 dans WAMP (ou appelez son `php.exe`) ;
> - Composer écrit cette exigence dans `vendor/composer/platform_check.php`, chargé à **chaque**
>   requête : si le **site** cPanel tourne en 8.3, l'API répond 500 sur tous les appels
>   (`DEPLOY-CPANEL.md` §4).

```powershell
cd C:\wamp64\www\anatt-ordre-mission\backend
php -v   # doit afficher PHP 8.4.x
php C:\composer\composer.phar install --no-dev --optimize-autoloader --prefer-dist
```

> `--no-dev` retire Pest, Pint, Telescope, Faker, etc. (indispensable en prod). L'application le
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

Résultat : `backend\vendor.zip` (~14 Mo). L'archive contient `vendor/` à sa racine,
donc l'extraction recrée correctement `anatt-api/vendor/`.

Vérifier rapidement le contenu (facultatif) :
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
([System.IO.Compression.ZipFile]::OpenRead("$PWD\vendor.zip")).Entries |
  Where-Object FullName -eq 'vendor/autoload.php' | Select-Object FullName
```
Doit afficher `vendor/autoload.php`.

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
