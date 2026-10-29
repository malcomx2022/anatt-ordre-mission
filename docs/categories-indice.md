# Catégories d'indice — remplacement du vocabulaire `ORDRE_*`

> Référence unique de la catégorie d'indice ANaTT : codes, libellés, seuils,
> et règle de transition depuis l'ancien vocabulaire `ORDRE_1/2/3`.
>
> *Relu le 24/10/2026 contre le code* : seuils, appels de `normaliser()` et de `regleIn()`,
> migration et tests conformes. Ajouté : **quand** la catégorie d'un agent est déduite ou
> recalculée (§1), et la catégorie portée par l'OM.
>
> *Révisé le 29/10/2026 (après la dette)* : un agent créé sans indice ni salaire brut est refusé par le
> motif « L'indice ou le salaire brut est requis pour déterminer la catégorie. », et non plus par « Le
> champ categorie est obligatoire. » (§1, §6).

---

## 1. Le vocabulaire

| Code (stocké, API, import) | Libellé (écrans, PDF) | Plage imprimée sur l'état officiel | Indice | Salaire brut |
|---|---|---|---|---|
| `INDICE_SUP_800` | Indice ≥ 800 | `≥ 800` | ≥ 800 | ≥ 372 000 |
| `INDICE_400_799` | Indice 400-799 | `400-799` | 400 à 799 | 186 000 à 371 999 |
| `INDICE_INF_400` | Indice < 400 | `< 400` | < 400 | < 186 000 |

Les bornes sont **inclusives par le bas** : un indice de 800 ou un salaire de 372 000
relève de `INDICE_SUP_800`, un indice de 400 ou un salaire de 186 000 de `INDICE_400_799`
(`CategorieIndice::determiner`, comparaisons `>=`).

La catégorie est **reprise telle quelle de SEMO**. À défaut, elle se déduit de
l'**indice** (prioritaire) ou, en secours, du **salaire brut** — les deux jeux de
seuils sont concordants. Formule de reconstitution d'un indice manquant :
`indice = salaire_base × 12 / 3097` (`Agent::indiceDepuisSalaire`, diviseur
`Agent::DIVISEUR_INDICE`). Elle reste un **outil de calcul** : la déduction automatique de
la catégorie ne l'utilise pas (elle s'appuie sur les seuils de salaire concordants), et
aucun écran ne l'appelle aujourd'hui.

**Quand la catégorie est déduite.**

| Moment | Règle | Où |
|---|---|---|
| Création d'un agent | catégorie transmise retenue (ancien code traduit) ; **absente**, elle est déduite de l'indice ou du salaire. L'indice ou le salaire brut est **exigé** dans tous les cas : sans l'un ni l'autre, refus 422 « L'indice ou le salaire brut est requis pour déterminer la catégorie. » (champ `indice`) ; la catégorie elle-même n'est plus réclamée (`required_with:indice,salaire_brut`) | `StoreAgentRequest::prepareForValidation`, `withValidator` |
| Modification d'un agent | **recalculée d'office** dès que l'indice ou le salaire brut figure dans la requête, même si une catégorie est envoyée ; sinon la catégorie transmise est retenue | `UpdateAgentRequest::prepareForValidation` |
| Import CSV/XLSX | colonne `categorie` retenue (ancien code traduit) ; vide, elle est déduite. Sans indice ni salaire brut, la ligne est rejetée avec le même motif ; le rapport d'import liste aussi « Le champ categorie est obligatoire. » si la colonne est vide | `AgentService::normaliserLigne`, `validerLigne` |
| Formulaire d'agent (écran) | suggestion en temps réel depuis l'indice ou le salaire (`categorieDeduite`), modifiable avant l'enregistrement | `components/agents/AgentForm.vue` |
| Retranscription d'un OM | catégorie de l'OM **reprise de SEMO** si elle est saisie pour ce bénéficiaire, sinon celle de l'agent ; elle est ensuite **figée sur l'OM** | `MissionService` (`ordres.*.categorie`) |

Conséquence : le référentiel agent suit les seuils dès qu'on modifie l'indice ou le salaire,
tandis que la catégorie retenue pour le calcul du per diem est celle de l'**OM**, figée à la
retranscription. Modifier un agent ne change ni les OM déjà retranscrits ni leurs montants.
Côté serveur, un indice ou un salaire à 0 compte comme absent (`integer(...) ?: null`).

**Le code porte le seuil** : un export, un log ou une requête SQL se lisent sans
table de concordance. C'est la raison du nommage retenu.

---

## 2. Où vit la vérité

| Couche | Fichier | Contenu |
|---|---|---|
| Serveur | `App\Support\CategorieIndice` | codes, libellés, plages, seuils, `determiner()`, `normaliser()`, `libelle()`, `plage()`, `valeursAcceptees()`, `regleIn()` |
| Client | `frontend/src/types/agent.ts` | `CATEGORIES`, `CATEGORIE_LABELS`, `categorieLabel()` |
| Client (seuils) | `frontend/src/utils/benin.ts` | `categorieDeduite()` — miroir du calcul serveur, pour le retour visuel temps réel |

**Champ vidé côté client.** Dans les formulaires d'agent, un champ `v-model.number` vidé
vaut `''` (chaîne vide), pas `null`. `categorieDeduite()` ignore toute valeur qui n'est pas
un nombre (`estNombre`) : un indice effacé laisse la main au salaire brut. Avant ce
garde-fou, effacer l'indice faisait proposer « Indice < 400 » quel que soit le salaire
(défaut n° 7 de la revue d'octobre 2026, `docs/revue-projet-2026-10.md` ; test
`frontend/src/__tests__/benin.spec.ts`).

Avant cette bascule, les seuils (800 / 400 / 372 000 / 186 000) étaient dupliqués
en quatre endroits côté serveur. Ils n'existent plus qu'une fois par couche : une
révision du barème de l'ANaTT se fait désormais dans **deux** fichiers, pas vingt.

**Règle** : ne jamais afficher le code brut. Les écrans passent par
`categorieLabel()` / `CATEGORIE_LABELS`. Le PDF de l'OM et le document de retenue passent par
`CategorieIndice::libelle()` (`PdfGenerator`). L'état de paiement imprime la **plage**
(`CategorieIndice::plage()`, via `LignesEtatPaiement`). La plage retombe sur l'indice réel de
l'agent, puis sur « — », pour un code inconnu (donnée héritée). `libelle()` renvoie le code tel
quel dans ce cas : c'est le seul chemin par lequel un code brut peut encore apparaître.

La validation passe par `CategorieIndice::regleIn()` (`in:` sur `valeursAcceptees()`, soit le
vocabulaire courant plus l'héritage `ORDRE_*`) dans `StoreAgentRequest`, `UpdateAgentRequest`,
`StoreMissionRequest`, `StoreBaremeRequest` et l'import (`AgentService`). Les exports Excel et PDF des
rapports ne portent pas la catégorie.

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
  mis en favori avant la bascule restent valides ; le même filtre sert aux compteurs de
  l'écran Personnel (`GET /api/agents/stats`).

(Liste vérifiée le 24/10/2026 : ce sont les seuls appels à `CategorieIndice::normaliser()`.)

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
cd backend  && ./vendor/bin/pest                 # dont CategorieIndiceTransitionTest, Unit/CategorieTest
cd frontend && npm run test:unit -- --run && npm run type-check && npm run lint:check   # dont benin.spec.ts
```

> `npm run test:unit` seul lance Vitest en mode **surveillance** et ne rend pas la main :
> `-- --run` l'exécute une fois, comme la CI.

| Test | Couvre |
|---|---|
| `backend/tests/Feature/CategorieIndiceTransitionTest.php` | vocabulaire produit, anciens codes acceptés (création, filtres agents et barème, import CSV), code inconnu rejeté |
| `backend/tests/Unit/CategorieTest.php` | normalisation, libellés, plages, seuils |
| `backend/tests/Feature/ImportModeleAgentsTest.php` | modèles d'import distribués (3 catégories, 2 opérateurs) |
| `backend/tests/Feature/AgentTest.php` | indice ou salaire brut exigé ; sans l'un ni l'autre, motif porté par l'indice, pas par la catégorie |
| `frontend/src/__tests__/benin.spec.ts` | seuils d'indice prioritaires sur le salaire, champ indice vidé ignoré |

Vérifications manuelles après déploiement :

1. **Créer un agent** avec un indice de 850 → catégorie proposée « Indice ≥ 800 ».
   Effacer l'indice et saisir un salaire de 200 000 → « Indice 400-799 » (et non « < 400 »).
2. **Importer** un CSV portant encore `ORDRE_1` → importé et traduit.
3. **Retranscrire un OM** → montant identique à celui d'avant la bascule.
4. **Réimprimer un état déjà signé** → document identique au pixel près.
