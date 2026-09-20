# Runbook — mettre à jour l'instance en ligne (cPanel, tâches Cron)

> Déroulé du **jour J**, à suivre dans l'ordre, cases à cocher. Chaque commande est prête à coller
> dans **cPanel > Cron Jobs**. Pour le *pourquoi* de chaque étape, voir
> [`DEPLOY-CPANEL.md`](../DEPLOY-CPANEL.md) § *Mise à jour d'une version déjà en ligne*.
>
> **Contexte visé** : compte cPanel `anatt`, **pas d'accès SSH**, **tâches Cron disponibles**.
> SPA sur `https://omsemo.anatt.bj` (`public_html/`), API sur `https://api-omsemo.anatt.bj`
> (`/home/anatt/anatt-api/`, racine `anatt-api/public`).

---

## Avant de commencer

- [ ] **PHP 8.4** sélectionné dans *Select PHP Version* — **minimum imposé**, pas une préférence :
      sous PHP 8.3 l'API renvoie 500 sur tous les appels (`DEPLOY-CPANEL.md` §4).
- [ ] Nouveau `frontend/dist/` récupéré (build local, ou artefact CI **`frontend-dist`** du run `main`).
- [ ] Nouveau code backend prêt à envoyer.
- [ ] `vendor/` régénéré **seulement si `composer.lock` a changé** (`composer install --no-dev
      --optimize-autoloader`, puis envoi zippé — cf. [`UPLOAD-VENDOR.md`](../UPLOAD-VENDOR.md)).
- [ ] Heure creuse choisie ; personne ne saisit d'OM pendant l'opération.

---

## 1 · Sauvegarder (non négociable)

- [ ] **Base** — phpMyAdmin > `anatt_missions` > **Exporter** > **Personnalisée** >
      cocher **« Ajouter DROP TABLE / VIEW »** > compression **gzip** > Exécuter.
      C'est cette case qui rend le dump réimportable tel quel en cas de retour arrière.
- [ ] **SPA** — Gestionnaire de fichiers > `public_html` > tout sélectionner > **Compresser**
      en `public_html_AAAA-MM-JJ.zip` > télécharger.
- [ ] **`.env`** — télécharger `anatt-api/.env` (il ne sera pas écrasé, mais il est irremplaçable).

---

## 2 · Relever ce qui manque à la base

phpMyAdmin > `anatt_missions` > onglet **SQL** :

```sql
SELECT migration, batch FROM migrations ORDER BY id;
```

- [ ] Comparer avec les noms de fichiers de `backend/database/migrations/` (sans `.php`).
- [ ] Noter les manquants — c'est exactement ce que l'étape 5 doit jouer.

---

## 3 · Vérifier le binaire PHP du Cron

`/usr/local/bin/php` est un **alias** qui pointe souvent vers une autre version que celle de
*Select PHP Version*. On ne le devine pas, on le vérifie.

**cPanel > Cron Jobs** — *Common Settings* : **Once Per Five Minutes** :

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -v > /home/anatt/php-version.log 2>&1
```

- [ ] Attendre un passage, ouvrir `/home/anatt/php-version.log` : il doit afficher **`PHP 8.4.x`**.
- [ ] **Supprimer cette tâche** avant de passer à la suite.

> Log vide ou absent → le chemin PHP est faux. Renseignez l'adresse e-mail du Cron Job : cPanel
> vous enverra la sortie (et l'erreur) par courriel.

---

## 4 · Envoyer le nouveau code de l'API

FTP vers `/home/anatt/anatt-api/` — écraser :

`app/` · `config/` · `database/` · `public/` · `resources/` · `routes/` ·
`bootstrap/app.php` · `bootstrap/providers.php`

**Ne pas toucher** :

| Chemin | Pourquoi |
|---|---|
| `anatt-api/.env` | Identifiants de base + `APP_KEY` |
| `anatt-api/storage/` | PDF générés, sauvegardes, logs |
| `anatt-api/bootstrap/cache/` | À **vider** (étape 6), jamais à écraser |
| `public_html/runtime-config.js` | Porte l'`apiUrl` de production |

- [ ] Code envoyé.
- [ ] `vendor/` réenvoyé **uniquement** si `composer.lock` a changé.

> Le code doit être en place **avant** de migrer : les fichiers de migration que vous venez
> d'envoyer sont précisément ce que l'étape 5 exécutera.

---

## 5 · Migrer la base

> **Une seule tâche Cron active à la fois.** Créez la suivante après avoir supprimé la précédente,
> sinon deux tâches se disputent les fichiers de log.

### 5a · Lister (ne modifie rien)

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate:status > /home/anatt/migration-status.log 2>&1
```

- [ ] `/home/anatt/migration-status.log` listé : les `Pending` correspondent à l'étape 2.
- [ ] Tâche supprimée.

### 5b · Migrer et purger les caches

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan migrate --force > /home/anatt/migration-run.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear >> /home/anatt/migration-run.log 2>&1
```

- [ ] `/home/anatt/migration-run.log` : chaque migration attendue affiche **`DONE`**.
- [ ] **Tâche supprimée** — sinon elle se rejoue toutes les cinq minutes et **écrase le log**.

> `--force` remplace la confirmation interactive, impossible en Cron.
> **Aucun `%` dans la commande** : Cron l'interprète comme un saut de ligne et tronque la tâche.

---

## 6 · Vérifier l'API avant de toucher au SPA

- [ ] `https://api-omsemo.anatt.bj/up` → page de santé Laravel en **200**.
- [ ] Un POST sur `/api/auth/login` répond en JSON.

> **500 partout** avec « Your Composer dependencies require a PHP version ">= 8.4.0" » → la
> version PHP du **site** (pas celle du Cron) est trop basse : *Select PHP Version* → 8.4.
>
> **500** sans ce message → caches encore compilés sur l'ancien code. Si `optimize:clear` n'a pas
> tourné, videz à la main (en gardant les dossiers et leur `.gitignore`) :
> `anatt-api/bootstrap/cache/*.php`, `anatt-api/storage/framework/views/*.php`,
> `anatt-api/storage/framework/cache/data/`.

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
- [ ] Console navigateur sans erreur rouge, ni CORS ni Mixed-Content.
- [ ] La fonctionnalité livrée par cette version se comporte comme attendu.
- [ ] Checklist complète : [`checklist-post-deploiement.md`](checklist-post-deploiement.md).

---

## 9 · Ménage

- [ ] Toutes les tâches Cron temporaires supprimées (étapes 3, 5a, 5b).
- [ ] `/home/anatt/php-version.log`, `migration-status.log`, `migration-run.log` supprimés.
- [ ] Sauvegardes de l'étape 1 archivées hors du serveur.

---

## Retour arrière

Si la recette échoue et que le correctif n'est pas immédiat :

1. **SPA** — vider `public_html/`, réextraire le zip de l'étape 1.
2. **Base** — phpMyAdmin > **Importer** le dump de l'étape 1 (ses `DROP TABLE` remplacent l'état
   actuel sans intervention manuelle).
3. **Code API** — réenvoyer le `anatt-api/` de la version précédente.

> `migrate:rollback --step=1` existe (les 32 migrations implémentent toutes `down()`), mais
> restaurer le dump reste plus sûr : un rollback ne rend pas les données qu'une migration a
> transformées.
