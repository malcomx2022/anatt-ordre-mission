# RBAC flexible — matrice rôle → permissions éditable

> Objectif : permettre d'ajuster **qui a le droit de faire quoi** sans toucher au
> code ni redéployer. L'écran **Matrice des permissions** (`/admin/roles`) devient
> éditable ; la matrice est persistée en base et prend effet immédiatement.
>
> Trois niveaux, cumulables :
> - **Niveau 1 — par profil** : la matrice rôle → permissions (ci-dessous).
> - **Niveau 2 — par utilisateur** : accorder/retirer une permission à **une
>   personne** au-dessus de son profil (section « Overrides par utilisateur »).
> - **Niveau 3 — rôles personnalisés** : créer de nouveaux profils au-delà des 8
>   profils système (section « Rôles personnalisés »).

## Principe

L'application était déjà autorisée **par permission** (middleware `permission:{clé}`
adossé à des Gates). Ce qui était figé, c'était la **matrice d'attribution**
(rôle → permissions), codée dans `config/profils.php` + `App\Support\Permissions`.

La flexibilité repose sur une **source de vérité vivante** en base, avec repli sur
la configuration :

```
role_permissions (table)   →  personnalisations (priment)
config/profils.php         →  défaut / repli
Permissions::MATRICE       →  dernier repli (config absente)
```

- Un profil **sans ligne** en base hérite de son **défaut de config**.
- Dès qu'on l'édite, **ses lignes font foi** ; il est marqué « personnalisé ».
- On peut le **réinitialiser** au défaut (suppression de ses lignes).
- Le **SUPER_ADMIN** n'apparaît jamais dans la matrice : accès implicite total
  (`Gate::before`).

Si la table `role_permissions` n'existe pas encore (migrations non jouées, certains
tests), le repository retombe **silencieusement** sur la config : l'app reste
fonctionnelle.

## Composants

| Élément | Rôle |
|---|---|
| `database/migrations/..._create_role_permissions_table.php` | table `(role, permission)`, unique |
| `App\Models\RolePermission` | modèle de la ligne |
| `App\Support\PermissionRepository` | résolution + cache + écriture (`matrice`, `pour`, `remplacer`, `reinitialiser`, `personnalises`) |
| `App\Support\Permissions::pour()` | délègue au repository |
| `App\Http\Controllers\Api\RoleController` | `index` / `update` / `reset` |
| `App\Http\Requests\UpdateRolePermissionsRequest` | validation de forme (clés connues) |
| `views/admin/RolesView.vue` | matrice éditable (cases à cocher) |
| `services/roles.ts` | client API |

La matrice est **mise en cache** (`rbac.matrice`) : une lecture BDD par cycle,
**purgée à chaque écriture** (`remplacer` / `reinitialiser`).

## API

| Méthode | Route | Permission | Effet |
|---|---|---|---|
| `GET` | `/api/admin/roles` | `utilisateurs.gerer` | matrice effective + catalogue des clés |
| `PUT` | `/api/admin/roles/{role}/permissions` | `roles.gerer` | remplace les permissions d'un profil |
| `DELETE` | `/api/admin/roles/{role}/permissions` | `roles.gerer` | réinitialise le profil au défaut |

`PUT` — corps : `{ "permissions": ["dashboard.consulter", "om.consulter", ...] }`
(liste complète et exhaustive du profil ; `present` mais peut être vide).

## Autorisation

- **Lecture** de la matrice : `utilisateurs.gerer` → l'**ADMIN** la consulte
  (l'écran est en lecture seule pour lui).
- **Écriture** : nouvelle clé `roles.gerer`, **exclusive au SUPER_ADMIN** — comme
  `superadmins.gerer` et `parametres.gerer`, elle n'est attribuable à aucun profil.

## Garde-fous (serveur, non contournables par l'UI)

1. **SUPER_ADMIN non éditable** — 422 sur toute tentative de modifier sa matrice.
2. **Clés réservées** — `superadmins.gerer`, `parametres.gerer`, `roles.gerer` ne
   peuvent être attribuées à un autre profil (422).
3. **Clé inconnue** — toute clé hors catalogue est refusée (422).
4. **Séparation ordonnateur / payeur** — un profil portant `om.approuver`
   (validation de l'état) ne peut cumuler `paiements.generer` / `paiements.confirmer`
   (exécution du décaissement). Règle de contrôle interne ANaTT (422).
5. **Profil inconnu** — 404.

Chaque édition est **auditée** : `ROLE_PERM_UPDATE` / `ROLE_PERM_RESET`
(auteur, avant/après).

## Prise d'effet

Les droits de l'utilisateur courant proviennent du serveur au **login** et via
`GET /api/auth/me/permissions`. Après une modification de la matrice, les profils
concernés voient leurs droits ajustés **à la connexion / au rafraîchissement
suivant**. Le cache serveur est purgé immédiatement à l'écriture.

## Défaut vs mirroir front

`frontend/src/utils/permissions.ts` (`ROLE_PERMISSIONS`) reste le **catalogue des
clés et la baseline par défaut** utilisée par les tests de cohérence
menu ↔ route ↔ API (`navigation.spec.ts`). La matrice **effective** affichée et
éditée dans l'écran provient toujours de l'API — elle peut donc diverger du défaut
une fois personnalisée, ce qui est le comportement attendu.

---

# Overrides par utilisateur (RBAC flexible — niveau 2)

> Ajuster les droits d'**une personne** au-delà de son profil : lui **accorder**
> une permission supplémentaire, ou lui **retirer** une permission que son profil
> donnerait. Écran : **Permissions de l'utilisateur** (`/admin/utilisateurs/:id/permissions`).

## Principe

```
permissions effectives = base_du_profil  ∪  accordées  −  retirées
```

- La base du profil vient de la matrice (niveau 1, elle-même éditable).
- Un override est soit un **accord** (`granted = true`), soit un **retrait**
  (`granted = false`), stocké dans la table **`user_permissions`**.
- Le **SUPER_ADMIN** est hors périmètre (accès implicite total ; overrides ignorés).

## Composants

| Élément | Rôle |
|---|---|
| `database/migrations/..._create_user_permissions_table.php` | table `(user_id, permission, granted)`, unique |
| `App\Models\UserPermission` | ligne d'override ; relation `User::permissionOverrides()` |
| `App\Services\UserPermissionService` | `overrides` / `effectives` / `remplacer` / `combiner` |
| `User::permissions()` | base ∪ accordées − retirées (mémoïsé par requête) |
| `UserController::permissions` / `updatePermissions` | GET / PUT |
| `views/admin/UserPermissionsView.vue` | éditeur par utilisateur (cases à cocher) |
| `services/users.ts` | client API |

`User::permissions()` est **mémoïsé** le temps de la requête (`oublierPermissions()`
invalide le mémo après écriture). Le login et `/auth/me/permissions` reflètent donc
l'effectif (profil + overrides) dès la connexion/rafraîchissement suivant.

## API

| Méthode | Route | Permission | Effet |
|---|---|---|---|
| `GET` | `/api/admin/users/:id/permissions` | `utilisateurs.gerer` | base, accordées, retirées, effectif |
| `PUT` | `/api/admin/users/:id/permissions` | `roles.gerer` | remplace les overrides d'un utilisateur |

`PUT` — corps : `{ "granted": ["audit.consulter"], "revoked": ["om.consulter"] }`
(les deux listes sont `present`, éventuellement vides).

## Autorisation

- **Lecture** : `utilisateurs.gerer` (l'ADMIN consulte, écran en lecture seule).
- **Écriture** : `roles.gerer`, **exclusive au SUPER_ADMIN** — accorder un droit à
  une personne est une élévation de privilège, réservée au même niveau que
  l'édition de la matrice.

## Garde-fous (serveur)

1. **SUPER_ADMIN cible** — refusé (overrides sans effet sur lui) — 422.
2. **Clé réservée accordée** (`superadmins.gerer` / `parametres.gerer` / `roles.gerer`)
   — refusé — 422.
3. **Clé accordée ET retirée** — refusé — 422.
4. **Clé inconnue** — refusé (validation) — 422.
5. **Séparation ordonnateur/payeur** sur l'**effectif** — un utilisateur avec
   `om.approuver` ne peut cumuler `paiements.generer` / `paiements.confirmer` — 422.

Chaque édition est **auditée** : `USER_PERM_UPDATE` (accords, retraits, avant/après).

## UI

L'écran affiche, par permission : la colonne **Profil** (héritée du rôle, en
lecture) et la colonne **Accès effectif** (cases à cocher). Cocher une case hors
profil = **accordé** ; décocher une case du profil = **retiré**. À l'enregistrement,
le front dérive `granted` (cochées hors base) et `revoked` (base décochées).
Édition gardée par `roles.gerer` ; sinon lecture seule.

---

# Rôles personnalisés (RBAC flexible — niveau 3)

> Créer des profils **au-delà des 8 profils système**, avec leur propre jeu de
> permissions, et y affecter des utilisateurs. Écran : **Matrice des permissions**
> (`/admin/roles`), carte « Rôles personnalisés ».

## Principe

- Les **8 profils système** restent définis dans le code (`App\Support\Permissions`) :
  ils portent la sémantique du circuit de signature (CONTROLEUR → DAF → DG) et les
  protections SUPER_ADMIN. Ils ne sont **ni supprimables ni renommables** (leurs
  permissions restent éditables — niveau 1).
- Les **rôles personnalisés** sont des données (table **`roles`**, `systeme = false`).
  Leur jeu de permissions vit dans `role_permissions` comme pour les profils système
  (aucun défaut de config : un rôle neuf part sans permission).
- Un rôle personnalisé est un profil d'**accès** : il n'entre pas dans le circuit de
  signature (réservé aux profils système qui en portent les clés).

## Composants

| Élément | Rôle |
|---|---|
| `database/migrations/..._create_roles_table.php` | table `(code, libelle, systeme)` |
| `database/migrations/..._change_users_role_to_string.php` | `users.role` : enum figé → chaîne libre |
| `App\Models\Role` | rôle personnalisé |
| `App\Support\PermissionRepository` | `roles` / `codes` / `creerRole` / `renommerRole` / `supprimerRole` / `roleExiste` / `estSysteme` ; la matrice boucle sur **tous** les rôles |
| `App\Http\Controllers\Api\RoleController` | `store` / `rename` / `destroy` |
| `App\Http\Requests\StoreRoleRequest` | validation (format du code, libellé) |
| `views/admin/RolesView.vue` | carte « Rôles personnalisés » (créer / renommer / supprimer) |

Les codes de rôle valides pour l'affectation d'un utilisateur (`StoreUserRequest` /
`UpdateUserRequest`) proviennent désormais de `PermissionRepository::codes()`
(système + personnalisés), non d'une liste figée.

## API

| Méthode | Route | Permission | Effet |
|---|---|---|---|
| `POST` | `/api/admin/roles` | `roles.gerer` | crée un rôle personnalisé (`code`, `libelle`, `permissions?`) |
| `PUT` | `/api/admin/roles/:role` | `roles.gerer` | renomme un rôle personnalisé (`libelle`) |
| `DELETE` | `/api/admin/roles/:role` | `roles.gerer` | supprime un rôle personnalisé |

La liste (`GET /api/admin/roles`) renvoie désormais chaque profil avec `systeme`
et `nb_utilisateurs`. Les permissions d'un rôle personnalisé s'éditent via
`PUT /api/admin/roles/:role/permissions` (comme un profil système).

## Garde-fous (serveur)

1. **Code** : format `^[A-Z][A-Z0-9_]*$`, unique, sans collision avec un profil système — 422.
2. **Renommer / supprimer un profil système** — refusé — 422.
3. **Supprimer un rôle encore porté par des utilisateurs** — refusé (réaffecter d'abord) — 422.
4. Clés réservées et **séparation ordonnateur/payeur** s'appliquent aussi aux permissions
   initiales d'un rôle personnalisé — 422.

Audité : `ROLE_CREATE` / `ROLE_RENAME` / `ROLE_DELETE`. La suppression retire aussi
les lignes `role_permissions` du rôle.

---

# Édition du rôle d'un utilisateur existant

> Changer le profil d'un compte déjà créé, directement depuis la liste des
> utilisateurs. Écran : **Gestion des utilisateurs** (`/admin/utilisateurs`),
> action « Modifier le rôle » (modale).

## Principe

Le rôle ne se choisissait qu'à la création (`UserCreateView`). Une action par
ligne ouvre une modale proposant les rôles assignables — **profils système +
rôles personnalisés** (niveau 3) — et applique le changement via l'endpoint
utilisateur existant. Aucun nouvel endpoint : `PUT /api/admin/users/:id { role }`.

## Composants

| Élément | Rôle |
|---|---|
| `views/admin/UserManagementView.vue` | action + modale ; catalogue des rôles chargé via `rolesService.matrice()` (filtre et libellés de badge inclus) |
| `UserController::update` (existant) | applique le rôle, révoque les tokens, journalise |
| `UpdateUserRequest` (niveau 3) | `role` validé contre `PermissionRepository::codes()` (système + personnalisés) |

## Autorisation & garde-fous (serveur, déjà en place)

- Action réservée à `utilisateurs.gerer` ; `SUPER_ADMIN` n'est proposé qu'à un
  super administrateur (double contrôle UI + `verrouSuperAdmin`).
- Le **dernier SUPER_ADMIN actif** ne peut être rétrogradé — 422.
- Un changement de rôle **révoque les sessions actives** du compte (les nouvelles
  permissions s'appliquent à la reconnexion) et est **audité** (`CHANGE_ROLE_USER`).

## Prise d'effet

Immédiate côté base ; l'utilisateur concerné, ses tokens révoqués, récupère ses
nouveaux droits à sa prochaine connexion (login + `/auth/me/permissions`).
