# Déploiement du circuit de validation sur une base déjà en ligne

> **Document historique (juillet-septembre 2026) — ne plus suivre tel quel.** Il décrit le
> passage au circuit à trois signatures **par OM**, piloté par le drapeau
> `WORKFLOW_DOUBLE_VALIDATION`. Ce circuit a été retiré : les trois signatures se jouent
> désormais sur l'**état de paiement** de la mission, et le circuit par OM n'a plus qu'une étape
> (`docs/circuits-validation.md` §4). Vérifié contre le code le 23/10/2026, puis le 25/10/2026 :
>
> | Section | Statut |
> |---|---|
> | §1-2 Migrations du 27/07/2026 | Toujours présentes ; elles s'exécutent avec les autres à toute installation ou mise à jour (`php artisan migrate`). Les statuts `VERIFIE` / `VISA_DAF` restent acceptés en base mais ne sont plus produits. **`users.role` n'est plus un ENUM** : `2026_09_02_150000_change_users_role_to_string` en a fait une chaîne libre (rôles personnalisés). Le SQL du §2.1 pour `users` est donc dépassé. |
> | §3.1-3.2 Procédure par SSH (`git pull`, `composer install` sur le serveur) | **Inadaptée** à l'hébergement cPanel sans SSH : suivre `DEPLOY-CPANEL.md`. |
> | §3.3 Comptes du circuit (`ComptesCircuitSeeder`) | **Toujours valable**, complété le 25/10 : lancement par tâche Cron, fonction imprimée (n° 30) et super administrateur protégé (n° 31). Voir le §3.3. |
> | §3.4 Bascule `WORKFLOW_DOUBLE_VALIDATION`, §4 interrupteur « Double validation » | **Obsolètes** : le drapeau n'existe plus, et l'interrupteur a disparu de l'écran *Paramètres*. Seul `WORKFLOW_SEPARATION_TACHES` subsiste dans `config/workflow.php`. |
> | §5 Contrôle fonctionnel (boutons Vérifier / Viser / Valider sur l'OM) | **Obsolète** : le contrôle se fait sur l'état (`docs/checklist-post-deploiement.md`, `docs/workflows-par-profil.md`). |
> | §6 Retour arrière | **Obsolète et dangereux** : 33 migrations existent aujourd'hui, et `migrate:rollback --step=3` annulerait les **trois dernières**, pas celles du 27/07. Revenir en arrière par la restauration d'une sauvegarde (`DEPLOY-CPANEL.md` § *Mise à jour*, H). |
>
> Pour une mise à jour d'aujourd'hui, suivre `DEPLOY-CPANEL.md` et `docs/runbook-mise-a-jour-cron.md`.

> Procédure de mise à jour d'une instance **en production** vers le circuit
> `CONTROLEUR → DAF → DG`. Le SQL présenté ici est celui que Laravel produit
> réellement pour MySQL 8 (compilé depuis la grammaire du framework, non recopié
> de mémoire).

---

## 1. Ce que la mise à jour change dans la base

Trois migrations, aucune suppression de donnée, aucune colonne existante modifiée
autrement que par l'élargissement de deux ENUM.

| Migration | Effet |
|---|---|
| `2026_07_27_120000_add_visa_daf_to_ordre_missions` | statut `VISA_DAF` + 11 colonnes de signature DAF/DG |
| `2026_07_27_120100_add_daf_dg_roles_and_signature_to_users` | rôles `DAF`/`DG` + `fonction`, `signature_path` |
| `2026_07_27_140000_add_verification_controleur_to_ordre_missions` | statut `VERIFIE` + 6 colonnes de vérification |

**Toutes les nouvelles colonnes sont `NULL`ables et sans valeur par défaut.** Les
lignes existantes restent donc valides telles quelles : un OM déjà `VALIDE`
conserve son statut, ses montants et ses versements, et affiche simplement des
blocs de signature vides s'il est réimprimé.

### Aucun statut existant n'est réécrit

Les migrations n'exécutent aucun `UPDATE` sur les données en montée de version.
`VALIDE` garde exactement le même sens qu'avant — l'OM est payable — et
`Versement::scopeEligiblesLot()` n'est pas modifié. Le module paiement, y compris
les lots déjà générés ou confirmés, est strictement inchangé.

---

## 2. Le SQL réellement exécuté

### 2.1 Élargissement des ENUM

```sql
alter table `ordre_missions`
  modify `statut` enum('BROUILLON','IMPORTE','VERIFIE','VISA_DAF','EN_ATTENTE',
                       'VALIDE','REJETE','SOLDE','ANNULE') not null default 'IMPORTE';

alter table `users`
  modify `role` enum('SUPER_ADMIN','ADMIN','GESTIONNAIRE_OM','AGENT_FINANCIER',
                     'DAF','DG','CONTROLEUR','LECTEUR') not null default 'LECTEUR';
```

> ⚠️ **Ces deux instructions reconstruisent la table.** MySQL ne sait faire une
> modification d'ENUM *en place* que si les nouvelles valeurs sont ajoutées **à la
> fin** de la liste. Ici `VERIFIE`, `VISA_DAF`, `DAF` et `DG` sont insérées **au
> milieu**, ce qui force une copie complète de la table avec verrou d'écriture.
>
> L'ordre des valeurs dans l'ENUM est fonctionnellement neutre pour l'application
> (aucun `ORDER BY` sur ces colonnes, toutes les comparaisons se font par chaîne),
> le placement a été choisi pour la lisibilité du schéma. Sur les volumes d'une
> agence — quelques milliers d'OM, quelques dizaines de comptes — la reconstruction
> se compte en secondes. **Mesurez avant** (§3.1) et prévoyez malgré tout une
> fenêtre hors service.

### 2.2 Colonnes ajoutées

`ordre_missions` — 17 colonnes, toutes `NULL` :

```sql
-- Étape 1 : vérification par le contrôleur
alter table `ordre_missions` add `verifie_par` bigint unsigned null after `commentaire_rejet`;
alter table `ordre_missions` add constraint `ordre_missions_verifie_par_foreign`
  foreign key (`verifie_par`) references `users` (`id`) on delete set null;
alter table `ordre_missions` add `verifie_le` timestamp null after `verifie_par`;
alter table `ordre_missions` add `verifie_nom` varchar(191) null after `verifie_le`;
alter table `ordre_missions` add `verifie_fonction` varchar(191) null after `verifie_nom`;
alter table `ordre_missions` add `verifie_commentaire` text null after `verifie_fonction`;
alter table `ordre_missions` add `signature_verif_hash` varchar(64) null after `verifie_commentaire`;

-- Étape 2 : visa DAF   → visa_daf_par / _le / _nom / _fonction / _commentaire + signature_daf_hash
-- Étape 3 : validation DG → valide_dg_par / _le / _nom / _fonction + signature_dg_hash
```

`users` — 2 colonnes :

```sql
alter table `users` add `fonction` varchar(191) null after `telephone`;
alter table `users` add `signature_path` varchar(191) null after `fonction`;
```

**Pourquoi `_nom` et `_fonction` en plus de la clé étrangère ?** Ce sont des
instantanés figés au moment de la signature. Si le DAF est renommé, muté ou son
compte supprimé six mois plus tard, la réimpression d'un document déjà signé ne
doit pas réécrire l'histoire. La clé étrangère sert au lien technique
(`on delete set null`), l'instantané au document.

---

## 3. Procédure

### 3.1 Avant — mesurer et sauvegarder

```bash
# Volumétrie : dimensionne la fenêtre d'indisponibilité
mysql -u USER -p BASE -e "
  SELECT 'ordre_missions' t, COUNT(*) lignes FROM ordre_missions
  UNION ALL SELECT 'users', COUNT(*) FROM users;"

# Sauvegarde — non négociable, les ALTER d'ENUM ne sont pas transactionnels sous MySQL
php artisan db:backup --compress
# ou : mysqldump -u USER -p --single-transaction BASE | gzip > avant-circuit.sql.gz
```

Vérifiez que l'archive est **lisible** avant d'aller plus loin :

```bash
gunzip -t avant-circuit.sql.gz && echo "archive saine"
```

### 3.2 Mise à jour

```bash
php artisan down --render="errors::503"      # fenêtre hors service

git pull origin main
composer install --no-dev --optimize-autoloader

php artisan migrate --force                   # les 3 migrations
php artisan config:clear && php artisan config:cache
php artisan route:cache

php artisan up
```

Le déploiement s'arrête ici si vous ne voulez pas encore basculer : **le flag est
à `false` par défaut, l'application se comporte exactement comme avant.** Les
colonnes sont en place, inutilisées, et n'affectent rien.

### 3.3 Créer les comptes du circuit

> *Section à jour au 25/10/2026.*

Renseignez le `.env` de production. Les variables `*_FONCTION` sont facultatives ; leurs défauts
viennent de `config/profils.php` :

```env
CONTROLEUR_NAME="Paul AGBO"
CONTROLEUR_EMAIL=p.agbo@anatt.bj
CONTROLEUR_FONCTION="Contrôleur"
DAF_NAME="Euloge D. ATOHOUN"
DAF_EMAIL=e.atohoun@anatt.bj
DAF_FONCTION="Directeur de l'Administration et des Finances"
DG_NAME="Richard DADA"
DG_EMAIL=r.dada@anatt.bj
DG_FONCTION="Directeur Général"
```

```bash
php artisan db:seed --class=ComptesCircuitSeeder --force
```

**Sur cPanel, sans SSH**, il y a deux voies :

- **à la première installation** : jouez le seeder en local, sur la base à exporter (`DEPLOY-CPANEL.md` §1.3) ;
- **sur une instance en ligne** : passez par une tâche Cron ponctuelle (même méthode que
  `DEPLOY-CPANEL.md` § *Mise à jour*, D) :

  ```bash
  /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan optimize:clear > /home/anatt/comptes-circuit.log 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php /home/anatt/anatt-api/artisan db:seed --class=ComptesCircuitSeeder --force >> /home/anatt/comptes-circuit.log 2>&1
  ```

  `optimize:clear` vient d'abord : si la configuration est en cache, les variables que vous venez
  d'ajouter au `.env` resteraient invisibles. Le log contient les **mots de passe temporaires** en
  clair. Lisez-le, puis **supprimez la tâche et le fichier `comptes-circuit.log`** aussitôt.

Le seeder affiche **une seule fois** un mot de passe temporaire aléatoire par
compte :

```
+------------+-------------------+--------------------+-------------------------+
| Profil     | Nom               | Email              | Mot de passe temporaire |
+------------+-------------------+--------------------+-------------------------+
| CONTROLEUR | Paul AGBO         | p.agbo@anatt.bj    | ••••••••••••••••••••    |
| DAF        | Euloge D. ATOHOUN | e.atohoun@anatt.bj | ••••••••••••••••••••    |
| DG         | Richard DADA      | r.dada@anatt.bj    | ••••••••••••••••••••    |
+------------+-------------------+--------------------+-------------------------+
```

Transmettez-les par un canal sûr. Chaque titulaire devra le changer à sa première
connexion — l'API reste verrouillée tant qu'il ne l'a pas fait.

Garanties du seeder, verrouillées par tests :

- **relançable sans risque** : sur un compte existant il aligne le rôle et la
  fonction, et ne réinitialise **jamais** le mot de passe ;
- **aucun mot de passe en dur** ni dérivé de l'email ;
- un poste laissé vide dans le `.env` est **ignoré**, jamais deviné ;
- **un super administrateur n'est jamais rétrogradé** (n° 31). Si l'e-mail d'un poste est celui
  d'un compte `SUPER_ADMIN`, ce compte reste tel quel et le poste n'est pas attribué ; le seeder
  le signale. Donnez au titulaire une adresse distincte de celle de son compte d'administration.

**Nom et fonction imprimés sous la signature d'un état.** Ils sont figés au moment de la signature,
dans cet ordre de priorité :

1. **Paramètres › Signatures** (écran du super administrateur) : nom, prénoms, fonction et paraphe
   du titulaire en poste ;
2. à défaut, le **compte** du signataire : son nom, et la fonction posée par ce seeder
   (`*_FONCTION`). Avant le correctif n° 30, cette fonction n'était jamais imprimée ;
3. à défaut, le libellé du poste (« Le Directeur Général »…).

> Les comptes de démonstration (`daf@anatt.bj`, `dg@anatt.bj`, mot de passe
> `password`) appartiennent à `UserSeeder` et ne sont créés qu'en `local` et
> `testing`. Ils n'atteignent jamais la production.

### 3.4 Basculer le circuit

Une fois les comptes créés **et** leurs titulaires connectés au moins une fois :

```env
WORKFLOW_DOUBLE_VALIDATION=true
```

```bash
php artisan config:cache
```

La bascule est **immédiate et réversible** : repasser à `false` restaure la
validation en une étape, sans migration. Les OM en cours restent à leur statut.

---

## 4. Le piège du toggle « Double validation »

L'écran **Administration → Paramètres** contient un interrupteur
« Double validation » qui **n'est pas branché** sur le circuit, délibérément.

`SettingsView.vue` l'initialise à `true` et le persiste dans
`storage/app/settings.json`. Le brancher tel quel basculerait le circuit dès le
premier enregistrement des paramètres, sans décision explicite — exactement ce que
le flag doit empêcher.

Si vous voulez un jour piloter le circuit depuis l'interface, il faudra
**d'abord** forcer la clé à `false` dans `settings.json`, puis seulement brancher
la lecture. En attendant, cet interrupteur n'a aucun effet.

---

## 5. Vérification après déploiement

```bash
# Le schéma porte bien les nouvelles valeurs
mysql -u USER -p BASE -e "SHOW COLUMNS FROM ordre_missions LIKE 'statut';"
mysql -u USER -p BASE -e "SHOW COLUMNS FROM users LIKE 'role';"

# Aucun OM n'a changé de statut pendant la migration
mysql -u USER -p BASE -e "SELECT statut, COUNT(*) FROM ordre_missions GROUP BY statut;"

# Les comptes du circuit existent avec le bon profil
mysql -u USER -p BASE -e "SELECT email, role, must_change_password FROM users WHERE role IN ('CONTROLEUR','DAF','DG');"
```

Contrôle fonctionnel, dans cet ordre :

1. connexion de chaque titulaire → l'application impose le changement de mot de passe ;
2. sur un OM `IMPORTE`, le contrôleur voit **Vérifier** (et non « Valider ») ;
3. après vérification, le DAF voit **Apposer le visa DAF** ; le DG ne voit rien encore ;
4. après visa, le DG voit **Valider définitivement** ;
5. l'OM n'apparaît dans la prévisualisation d'un lot de paiement **qu'après**
   l'étape 4 — c'est le point à vérifier en priorité.

---

## 6. Retour arrière

> ⚠️ **Ne plus exécuter cette section.** Le drapeau qu'elle désactive n'existe plus. Surtout,
> `migrate:rollback --step=3` annule aujourd'hui les **trois dernières** migrations : la séquence
> des lots de paiement, le renommage des catégories et les notifications. Ce ne sont pas celles du
> circuit. Pour revenir en arrière, restaurez une sauvegarde (`DEPLOY-CPANEL.md` § *Mise à jour*, H).

### Désactiver sans rien défaire — à privilégier

```env
WORKFLOW_DOUBLE_VALIDATION=false
```

```bash
php artisan config:cache
```

Suffit dans la quasi-totalité des cas. Les colonnes restent, inutilisées.

> Attention aux OM figés en cours de circuit : un OM à `VERIFIE` ou `VISA_DAF`
> n'est plus « avançable » en circuit simple, qui n'accepte que `IMPORTE`. Avant
> de repasser à `false`, ramenez-les :
>
> ```sql
> UPDATE ordre_missions SET statut = 'IMPORTE'
>  WHERE statut IN ('VERIFIE','VISA_DAF');
> ```

### Annuler les migrations — dernier recours

```bash
php artisan migrate:rollback --step=3
```

Le `down()` de chaque migration ramène d'abord les statuts concernés à `IMPORTE`
et rétrograde les comptes `DAF`/`DG` en `LECTEUR`, **avant** de restreindre les
ENUM — sans quoi MySQL rejetterait des valeurs devenues hors contrainte. Les
colonnes de signature sont supprimées : **les visas déjà apposés sont perdus.**
Restaurez la sauvegarde du §3.1 plutôt que de faire un rollback sur une base
portant des signatures réelles.

---

## 7. Récapitulatif

| Étape | Commande | Réversible ? |
|---|---|---|
| Sauvegarde | `php artisan db:backup --compress` | — |
| Migrations | `php artisan migrate --force` | oui (rollback, perte des visas) |
| Comptes | `php artisan db:seed --class=ComptesCircuitSeeder --force` | oui (désactiver les comptes) |
| Bascule | `WORKFLOW_DOUBLE_VALIDATION=true` + `config:cache` | **oui, immédiat** |

Les trois premières étapes ne changent **aucun comportement**. Seule la
quatrième bascule le circuit, et c'est la seule qui s'annule d'une ligne.
