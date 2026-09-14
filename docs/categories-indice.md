# Catégories d'indice — remplacement du vocabulaire `ORDRE_*`

> Référence unique de la catégorie d'indice ANaTT : codes, libellés, seuils,
> et règle de transition depuis l'ancien vocabulaire `ORDRE_1/2/3`.

---

## 1. Le vocabulaire

| Code (stocké, API, import) | Libellé (écrans, PDF) | Plage imprimée sur l'état officiel | Indice | Salaire brut |
|---|---|---|---|---|
| `INDICE_SUP_800` | Indice ≥ 800 | `≥ 800` | ≥ 800 | ≥ 372 000 |
| `INDICE_400_799` | Indice 400-799 | `400-799` | 400 à 799 | 186 000 à 372 000 |
| `INDICE_INF_400` | Indice < 400 | `< 400` | < 400 | < 186 000 |

La catégorie est **reprise telle quelle de SEMO**. À défaut, elle se déduit de
l'**indice** (prioritaire) ou, en secours, du **salaire brut** — les deux jeux de
seuils sont concordants. Formule de reconstitution d'un indice manquant :
`indice = salaire_base × 12 / 3097`.

**Le code porte le seuil** : un export, un log ou une requête SQL se lisent sans
table de concordance. C'est la raison du nommage retenu.

---

## 2. Où vit la vérité

| Couche | Fichier | Contenu |
|---|---|---|
| Serveur | `App\Support\CategorieIndice` | codes, libellés, plages, seuils, `determiner()`, `normaliser()`, `regleIn()` |
| Client | `frontend/src/types/agent.ts` | `CATEGORIES`, `CATEGORIE_LABELS`, `categorieLabel()` |
| Client (seuils) | `frontend/src/utils/benin.ts` | `categorieDeduite()` — miroir du calcul serveur, pour le retour visuel temps réel |

Avant cette bascule, les seuils (800 / 400 / 372 000 / 186 000) étaient dupliqués
en quatre endroits côté serveur. Ils n'existent plus qu'une fois par couche : une
révision du barème de l'ANaTT se fait désormais dans **deux** fichiers, pas vingt.

**Règle** : ne jamais afficher le code brut. Les écrans passent par
`categorieLabel()` / `CATEGORIE_LABELS`, les PDF par `CategorieIndice::libelle()`.

---

## 3. Transition depuis `ORDRE_1/2/3`

L'ancien vocabulaire **n'est plus produit** nulle part. Il reste **accepté en
entrée** et traduit silencieusement par `CategorieIndice::normaliser()` :

- création / modification d'agent (`StoreAgentRequest`, `UpdateAgentRequest`) ;
- retranscription d'une mission (`StoreMissionRequest`, par bénéficiaire) ;
- création d'un barème (`StoreBaremeRequest`) ;
- import CSV/XLSX du personnel (`AgentService::normaliserLigne`) — les fichiers
  déjà préparés par l'ANaTT continuent de fonctionner ;
- filtres de liste `?categorie=…` (`AgentService`, `BaremeService`) — les liens
  mis en favori avant la bascule restent valides.

Un code inconnu des deux vocabulaires (`ORDRE_9`, `INDICE_X`) est **rejeté** en
422 comme avant : la normalisation ne masque pas une erreur de saisie.

> Le **journal d'audit n'est pas réécrit**. Les entrées antérieures à la bascule
> conservent `ORDRE_*` dans leur payload : un journal d'audit atteste de ce qui
> s'est passé, avec le vocabulaire en vigueur à ce moment-là.

---

## 4. La migration de données

`2026_09_14_090000_renommer_categories_ordre_en_indice.php` traite les trois
colonnes `enum` — `agents.categorie`, `baremes.categorie`,
`ordre_missions.categorie` — **en trois temps** :

1. **élargir** l'enum aux six valeurs (les deux vocabulaires cohabitent) ;
2. **traduire** les données, les trois tables dans une seule transaction ;
3. **refermer** l'enum sur les trois nouvelles valeurs.

Les trois colonnes basculent **ensemble** : si le barème migrait sans les OM,
`Bareme::taux()` ne retrouverait plus ses taux et le calcul du per diem
échouerait. Le `down()` est symétrique et rejoue le même déroulé en sens inverse.

> ⚠️ Sur MySQL, un `ALTER TABLE` qui réduirait directement l'enum aux nouvelles
> valeurs **tronquerait toutes les lignes existantes**. D'où le déroulé en trois
> temps. Sur SQLite (tests), le `change()` recrée la table : même résultat.

Les migrations `create_*` d'origine sont **laissées intactes** — l'historique des
migrations ne se réécrit pas. Conséquence utile : chaque installation fraîche et
chaque exécution de la suite de tests **rejoue réellement la bascule**.

---

## 5. Ce qui n'est pas impacté (et pourquoi)

| Élément | Raison |
|---|---|
| **Scellé HMAC des états** | Il porte `étape · mission · tranche · numéro · nb · montants · signataire · horodatage` — pas la catégorie. Aucun état signé n'est invalidé. |
| **États déjà signés** | Leur instantané `lignes` stocke la **plage** (`≥ 800`), jamais le code. Ils restent imprimables à l'identique, sans régénération. |
| **Fichiers MTN (.txt) / Moov (.xlsx)** | Ils ne transportent aucune catégorie. Format de paiement inchangé. |
| **Montants** | Barème et OM migrent dans la même transaction : aucun taux, aucun per diem, aucune avance ne bouge. |

---

## 6. Recette

```bash
cd backend  && ./vendor/bin/pest                 # dont CategorieIndiceTransitionTest
cd frontend && npm run test:unit && npm run type-check && npm run lint:check
```

Vérifications manuelles après déploiement :

1. **Créer un agent** avec un indice de 850 → catégorie proposée « Indice ≥ 800 ».
2. **Importer** un CSV portant encore `ORDRE_1` → importé et traduit.
3. **Retranscrire un OM** → montant identique à celui d'avant la bascule.
4. **Réimprimer un état déjà signé** → document identique au pixel près.
