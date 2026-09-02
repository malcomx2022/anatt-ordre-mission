# RBAC flexible — matrice rôle → permissions éditable

> Objectif : permettre d'ajuster **qui a le droit de faire quoi** sans toucher au
> code ni redéployer. L'écran **Matrice des permissions** (`/admin/roles`) devient
> éditable ; la matrice est persistée en base et prend effet immédiatement.

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
