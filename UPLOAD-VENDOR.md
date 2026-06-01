# Zipper & uploader `vendor/` sur cPanel (rapide)

Le dossier `backend/vendor/` (~64 Mo, ~8900 fichiers) est **trop lent à envoyer fichier
par fichier** en FTP. On l'envoie donc **zippé** (≈14 Mo, 1 seul fichier), puis on
l'extrait directement dans cPanel.

> `vendor/` n'est pas versionné (régénérable). À refaire après chaque changement de
> dépendances backend (`composer.json` / `composer.lock`).

---

## 1. (Re)générer le `vendor/` de production

À faire **uniquement** si les dépendances ont changé (sinon passez à l'étape 2).

```powershell
cd C:\wamp64\www\anatt-ordre-mission\backend
php C:\composer\composer.phar install --no-dev --optimize-autoloader
```

> `--no-dev` retire Pest/Telescope/etc. (indispensable en prod).
> Pour retravailler en local ensuite : `php C:\composer\composer.phar install`.

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
2. Clic droit sur `vendor.zip` → **Extract** (Extraire) → confirmez la destination `anatt-api/`.
3. Vérifiez que `anatt-api/vendor/autoload.php` existe.
4. **Supprimez** `vendor.zip` du serveur (plus utile).

---

## 5. Vérifier

Ouvrez `https://api-omsemo.anatt.bj/up` → page de santé (200).
Une erreur `Class ... not found` signifie un `vendor/` incomplet : refaites l'extraction
(ou re-zippez/ré-uploadez), et au besoin videz `anatt-api/bootstrap/cache/*.php`.

---

## Mémo des chemins

| Local | Serveur cPanel |
|---|---|
| `backend\vendor.zip` | `/home/anatt/anatt-api/vendor.zip` → extraire → `vendor/` |

Voir `DEPLOY-CPANEL.md` pour le déploiement complet.
