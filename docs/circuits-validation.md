# Les deux circuits de validation — document de référence

> Pourquoi l'application a **deux** chemins vers le statut `VALIDE` d'un ordre de
> mission, quand chacun s'applique, et pourquoi ce n'est **pas** une double source
> de vérité. Ce document est la référence unique sur le sujet ; le code et les
> tests renvoient ici.

---

## 1. Les deux voies

| | Circuit par **ÉTAT** (canonique) | Circuit par **OM** (repli) |
|---|---|---|
| Service | `EtatPaiementService` | `MissionService` / `ValidationController` |
| Objet validé | l'**état de paiement** de la mission (document signé) | un **OM** isolé |
| Étapes | récap → `CONTROLE` → `VISA_DAF` → `VALIDE` (par tranche) | `IMPORTE` → `VALIDE` (**une seule étape**) |
| Effet sur les OM | la validation finale du DG **propage** `VALIDE` aux N OM | fait passer **l'OM** à `VALIDE` |
| Quand | mission **avec** état de paiement (cas normal) | mission **sans** état (reprise de données, correction ponctuelle) |

**Le circuit par ÉTAT est le circuit officiel de l'ANaTT.** Une seule signature sur
l'état (scellée HMAC, avec paraphe archivé et numéro de registre par tranche) vaut
pour tous ses bénéficiaires. C'est le document que le circuit Contrôleur → DAF → DG
valide, et c'est lui qui ouvre la génération des fichiers de paiement.

**Le circuit par OM est un repli.** Il existe pour les missions **sans** état :
données reprises d'avant l'introduction des états, ou correction unitaire d'un
import. Il n'a pas vocation à être utilisé quand un état existe.

---

## 2. L'invariant : exclusivité **par mission**

Les deux circuits ne tournent **jamais en parallèle sur une même mission**. Dès
qu'un état de paiement existe pour la mission, le circuit par OM est **fermé** :

```php
// MissionService::exigerAbsenceDEtat() — appelé par valider/rejeter
if ($etat !== null) {
    throw ValidationException::withMessages([
        'etat' => ["… l'action « {$action} » doit être effectuée sur l'état, pas sur l'OM."],
    ]);
}
```

Sans ce verrou, le statut d'un OM pourrait diverger de celui du document signé de
sa mission — le type de bug qui « passe les tests individuellement ». Le verrou
garantit qu'il n'existe **qu'une seule autorité** sur le statut d'un OM à un
instant donné :

- mission **avec** état → l'**état** fait foi ;
- mission **sans** état → l'**OM** fait foi.

Cet invariant est verrouillé par `tests/Feature/CoherenceCircuitsTest.php`.

---

## 3. La porte de paiement est déjà unifiée

Quel que soit le circuit, l'éligibilité d'un versement à un fichier passe par le
**même** filtre, `Versement::scopeEligiblesLot()` :

> un versement est éligible si son OM est `VALIDE` **ET**
> (la mission n'a **aucun** état **OU** l'état **de sa tranche** est `VALIDE`).

Conséquences :

- `VALIDE` a le **même sens** dans les deux circuits : l'OM est verrouillé et
  payable. `scopeEligiblesLot` n'a donc jamais à distinguer par quel circuit un
  OM est arrivé à `VALIDE`.
- Verrou **par tranche** : valider les **avances** n'ouvre **pas** les reliquats.
- Seconde barrière (défense en profondeur) : même si un OM est forcé à `VALIDE`
  hors circuit, aucun fichier n'est écrit tant que l'état de la tranche n'est pas
  validé — couvert par `tests/Feature/Paiement/BarriereEtatTest.php`.

---

## 4. Le circuit par OM tient en une seule étape

Le circuit par OM ne connaît qu'une transition : `IMPORTE → VALIDE` (le contrôleur
valide directement, permission `om.valider`), plus un rejet motivé depuis `IMPORTE`.
Il n'y a **pas** d'étapes DAF/DG par OM : la validation à trois signatures
(contrôle → visa DAF → validation DG) se joue **uniquement sur l'état** de la
mission (`EtatPaiementService`).

> Historique : un mode « double validation par OM » a existé (drapeau
> `workflow.double_validation`, statuts OM `VERIFIE` / `VISA_DAF`). Il était
> désactivé par défaut, dépourvu d'interface, et redondant avec le circuit par
> état — il a été retiré. Les valeurs d'enum `VERIFIE` / `VISA_DAF` restent
> acceptées en base pour d'éventuelles données héritées, mais ne sont plus
> produites. Seule `separation_des_taches` subsiste dans `config/workflow.php`
> (appliquée au circuit par état).

---

## 5. Pourquoi ne pas « fusionner » les deux circuits

Supprimer le circuit par OM pour tout faire passer par un état **casserait la
mémoire métier** :

1. **Reprise de données** : les missions importées/validées **avant** la feature
   « état » n'ont pas d'état ; elles deviendraient impossibles à valider ou
   rejeter.
2. **L'état est le document légal** (scellé HMAC par tranche, `lignes` figées,
   paraphes archivés, numéro de registre) — il ne peut être ni « fusionné dans
   l'OM » ni retiré.
3. **Migrations destructives** : l'enum `OrdreMission.statut` (`VERIFIE`,
   `VISA_DAF`), les colonnes de signature OM et le scellé HMAC au niveau OM
   disparaîtraient sur des données de production.

La position retenue : **conserver les deux circuits**, garantir leur exclusivité
par le verrou `exigerAbsenceDEtat` et l'unification à la porte de paiement, et
documenter ici quand chacun s'applique. Le repli par OM pourra être retiré le jour
où plus aucune mission sans état ne subsiste (fin de la reprise de données).

---

## 6. Références

| Sujet | Emplacement |
|---|---|
| Circuit par état | `app/Services/EtatPaiementService.php` |
| Circuit par OM | `app/Services/MissionService.php`, `app/Http/Controllers/Api/ValidationController.php` |
| Règles de statut / séparation des tâches | `app/Support/Workflow.php`, `config/workflow.php` |
| Exclusivité (verrou) | `MissionService::exigerAbsenceDEtat()` |
| Porte de paiement unifiée | `Versement::scopeEligiblesLot()` |
| Tests — exclusivité & sens de VALIDE | `tests/Feature/CoherenceCircuitsTest.php` |
| Tests — barrière de paiement | `tests/Feature/Paiement/BarriereEtatTest.php` |
| Tests — circuit OM (une étape) | `tests/Feature/CircuitValidationTest.php` |
| Tests — séparation des tâches | `tests/Feature/SeparationTachesTest.php` |
