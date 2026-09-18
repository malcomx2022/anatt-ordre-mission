# Checklist de vérification post-déploiement — ANaTT Ordres de mission

> À dérouler **après le déploiement** sur cPanel. Chaque bloc confirme un maillon : d'abord l'API
> Laravel (`anatt-api/`), puis les fichiers du SPA, la connexion à l'API, le cache, les parcours et
> les correctifs livrés.
>
> - **SPA** : `https://omsemo.anatt.bj` (contenu de `frontend/dist/` dans `public_html/`)
> - **API** : `https://api-omsemo.anatt.bj` (application Laravel dans `anatt-api/`, racine `anatt-api/public`)
>
> Référence détaillée : [`DEPLOY-CPANEL.md`](../DEPLOY-CPANEL.md).

---

## 01 · API Laravel

Le back-end doit répondre **avant** tout test du SPA.

- [ ] **Santé** : `https://api-omsemo.anatt.bj/up` renvoie la page de santé Laravel en **200**.
- [ ] **Route API** : un POST sur `/api/auth/login` répond en JSON ; un GET renvoie une erreur JSON (route vivante, pas une page cPanel).
- [ ] **`.env` serveur correct** : `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` renseignée, `APP_URL=https://api-omsemo.anatt.bj`, `CORS_ALLOWED_ORIGINS=https://omsemo.anatt.bj`.
- [ ] **Base de données** : `DB_DATABASE / DB_USERNAME / DB_PASSWORD` valides et **schéma à jour**.
- [ ] **Aucune migration en attente** : dans phpMyAdmin, `SELECT migration FROM migrations ORDER BY id;` contient bien **tous** les noms de fichiers de `backend/database/migrations/` (sans `.php`). Un nom manquant = migration non jouée. Voir `DEPLOY-CPANEL.md` § *Mise à jour*, B et D.
- [ ] **PHP 8.2 / 8.3** sélectionné, extensions actives : `mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo, gd, zip, curl`.
- [ ] **Permissions** : `storage/` (récursif) et `bootstrap/cache/` en écriture (755/775) — sinon erreur 500 au premier appel.
- [ ] **Caches applicatifs vidés** après changement de code ou de `.env` : `php artisan optimize:clear` (ou `config:clear` + `view:clear`).
- [ ] **HTTPS (AutoSSL)** actif sur `api-omsemo.anatt.bj`.

> Sur mutualisé sans SSH, les commandes `artisan` passent par une **tâche Cron ponctuelle** (voie la plus
> largement disponible) ou le **Terminal cPanel** s'il est activé, avec le chemin **explicite** de la
> version PHP sélectionnée — `/opt/cpanel/ea-php83/root/usr/bin/php /home/anatt/anatt-api/artisan …`
> et non l'alias `/usr/local/bin/php`, qui pointe souvent vers une autre version. Si l'hébergeur n'offre
> ni l'un ni l'autre, la base se met à jour par **migration à blanc en local puis réimport**
> (`DEPLOY-CPANEL.md` § *Mise à jour*, D, voie 3).

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

- [ ] Les 7 routes ci-dessus rendent leur écran, zéro page blanche.
- [ ] Console navigateur sans erreur rouge sur ces pages.

---

## 06 · Correctifs livrés (main)

Vérifier ce qui a changé dans ce build.

- [ ] **Fiche d'état** : une mission sans récapitulatif affiche la carte d'état vide + bouton « Établir le récapitulatif » (c'était le cas de la page blanche ; titre de l'écran au pluriel « États de paiement »).
- [ ] **Filtre missions** : une mission dont le reliquat est « Visé DAF » (avance déjà validée) remonte bien sous le filtre **Visé DAF** (le filtre couvre désormais les deux tranches, cohérent avec les deux badges affichés).
- [ ] **File de travail** : un Super Administrateur ouvre `/etats` sur « Tous », pas piégé sur une file vide (un acteur dédié — contrôleur / DAF / DG — atterrit toujours sur sa propre file).

---

## 07 · Filet de sécurité

- [ ] Sauvegarde de l'ancien `public_html/` conservée avant l'upload (rollback possible).
- [ ] Comptes de démonstration désactivés/supprimés en production (le mot de passe `password` n'existe qu'en local).

> Si la page blanche persiste après upload **et** purge de cache : relever la **console navigateur**
> (erreurs rouges) sur `/missions/1/etat` et la réponse de `GET /api/missions/1/etat` — ce serait alors
> un cas propre à l'environnement.
