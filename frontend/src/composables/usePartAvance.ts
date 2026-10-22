import { shallowRef } from 'vue'
import { api } from '@/services/http'

/** Pourcentage d'avance par défaut si le serveur est injoignable ou muet. */
const DEFAUT = 70

let cache: number | null = null
let promesse: Promise<number> | null = null

/**
 * Réactif : source de vérité unique, mise à jour par `chargerPartAvance()`.
 * Les templates qui l'affichent se rafraîchissent dès que la valeur serveur
 * arrive (ou change après un nouveau réglage + `reinitialiser()`).
 */
const partAvanceRef = shallowRef<number>(DEFAUT)

/** Valeur courante en mémoire (70 par défaut, mise à jour après chargement). */
export function partAvance(): number
{
  return cache ?? DEFAUT
}

/**
 * Charge le pourcentage depuis les paramètres serveur (idempotent, en cache).
 *
 * Interroge la route de LECTURE publique `parametres/part-avance`, accessible à
 * tout utilisateur authentifié — et non `settings`, réservée au SUPER_ADMIN
 * (`parametres.gerer`), qui renverrait 403 aux autres profils.
 */
export async function chargerPartAvance(): Promise<number>
{
  if (cache !== null) return cache
  if (promesse) return promesse

  promesse = api.get('/api/admin/parametres/part-avance').then(({ data }) => {
    const v = data.data
    return appliquer(typeof v === 'number' ? v : DEFAUT)
  }).catch(() => {
    // Échec (423 mot de passe à changer, 401, réseau…) : on affiche le défaut
    // SANS le mettre en cache, pour retenter au prochain appel (sinon 70 %
    // resterait figé toute la session alors que le réglage serveur diffère).
    partAvanceRef.value = DEFAUT
    return DEFAUT
  }).finally(() => {
    promesse = null
  })

  return promesse
}

/** Fixe la valeur (cache + ref réactif) et la renvoie. */
function appliquer(valeur: number): number
{
  cache = valeur
  partAvanceRef.value = valeur

  return valeur
}

/**
 * Purge la valeur mémorisée pour forcer un rechargement au prochain appel.
 * Utilisée à la déconnexion (ne pas conserver le réglage d'une session à
 * l'autre) et après un changement de réglage (SUPER_ADMIN) pour refléter la
 * nouvelle part sans recharger la page.
 */
export function reinitialiser(): void
{
  cache = null
  promesse = null
  partAvanceRef.value = DEFAUT
}

/**
 * Pourcentage d'avance paramétrable (SUPER_ADMIN, clé `partAvance`).
 * Valeur par défaut : 70.
 * Le résultat est mis en cache en mémoire pour éviter de requêter à chaque
 * calcul — le paramètre change rarement et une staleness d'une session est
 * sans impact financier.
 */
export function usePartAvance()
{
  return { partAvance: partAvanceRef, charger: chargerPartAvance, reinitialiser }
}
