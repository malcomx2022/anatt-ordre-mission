# Déploiement ANaTT sur cPanel (hébergement mutualisé)

Guide pas-à-pas pour déployer **sans accès SSH** (FTP + phpMyAdmin), avec :

- **SPA Vue** sur le domaine principal — `https://omsemo.anatt.bj`
- **API Laravel** sur un sous-domaine — `https://api-omsemo.anatt.bj`

> Adaptez partout `omsemo.anatt.bj` et `cpaneluser` à vos valeurs réelles.

```
public_html/                 -> SPA Vue (contenu de frontend/dist/)
anatt-api/                   -> application Laravel (hors web)
anatt-api/public/            -> racine du sous-domaine api-omsemo.anatt.bj
```

---

## 0. Prérequis (sur votre PC)

PHP 8.2+, Composer et Node 18+ installés en local (pour préparer les artefacts).
Le serveur mutualisé n'aura besoin **ni de Composer ni de Node** : on y envoie le code déjà prêt.

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
```powershell
# Sur une base locale dédiée (ex. anatt_export)
php artisan migrate:fresh
php artisan db:seed --class=ZoneSeeder
php artisan db:seed --class=BaremeSeeder
php artisan db:seed --class=UserSeeder      # crée le SUPER_ADMIN (+ comptes démo à supprimer ensuite)
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

---

## 2. Préparer le FRONTEND en local

L'URL de l'API est **modifiable après déploiement** via `runtime-config.js` (pas besoin de recompiler à chaque changement de domaine). On peut donc builder dès maintenant.

```powershell
cd frontend
npm ci
npm run build      # produit frontend/dist/
```

`dist/` contient déjà : `index.html`, `assets/`, les favicons, `site.webmanifest`,
`.htaccess` (routing SPA) et `runtime-config.js`.

---

## 3. cPanel — Base de données

1. **cPanel > MySQL® Databases**
   - Créez une base : `cpaneluser_anatt`
   - Créez un utilisateur : `cpaneluser_anatt` + mot de passe fort
   - Ajoutez l'utilisateur à la base avec **ALL PRIVILEGES**
2. **cPanel > phpMyAdmin** : sélectionnez la base, onglet **Import**, envoyez `anatt_prod.sql`.

---

## 4. cPanel — Sous-domaine de l'API

1. **cPanel > Domains (ou Subdomains)** : créez `api-omsemo.anatt.bj`.
2. Réglez sa **Document Root** sur `anatt-api/public`
   (créez le dossier `anatt-api` à la racine du compte, **au même niveau** que `public_html`, pas dedans).
3. **cPanel > Select PHP Version** : choisissez **PHP 8.2 ou 8.3** et activez les extensions :
   `mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo, gd, zip, curl`.

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
- `DB_DATABASE / DB_USERNAME / DB_PASSWORD` de l'étape 3
- `CORS_ALLOWED_ORIGINS=https://omsemo.anatt.bj`
- `DB_DUMP_BINARY=` chemin mysqldump de l'hébergeur si vous utilisez les sauvegardes (sinon laissez `mysqldump`)
- Bloc `MAIL_*` (SMTP de l'hébergeur)

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
Puis `https://api-omsemo.anatt.bj/api/auth/login` (en POST) répondra ; un simple GET renverra une erreur JSON, c'est normal.

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
4. Testez une génération de fichier MTN/Moov et un export PDF.

---

## 9. Tâches récurrentes (facultatif) — Cron cPanel

Le projet tourne en `QUEUE_CONNECTION=sync` : **aucun cron requis** pour fonctionner.
Si vous activez la file `database` (gros volumes) ou voulez des sauvegardes planifiées :

```bash
# cPanel > Cron Jobs (chemin PHP de l'hébergeur, souvent /usr/local/bin/php)
# Sauvegarde quotidienne à 02h00
0 2 * * *  /usr/local/bin/php /home/cpaneluser/anatt-api/artisan db:backup --compress

# (si QUEUE_CONNECTION=database) traiter la file chaque minute
* * * * *  /usr/local/bin/php /home/cpaneluser/anatt-api/artisan queue:work --stop-when-empty
```

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
| Lenteur 1er chargement | config non cachée | (Optionnel, si Terminal cPanel dispo) `php artisan config:cache route:cache` |

---

## Récapitulatif des artefacts à uploader

| Source (local) | Destination (cPanel) |
|---|---|
| `backend/` (avec `vendor/`) | `anatt-api/` |
| `backend/.env.production.example` → rempli | `anatt-api/.env` |
| `frontend/dist/` (contenu) | `public_html/` |
| `anatt_prod.sql` | importé via phpMyAdmin |
