import { shallowRef } from 'vue'
import { api } from '@/services/http'

let cache: number | null = null
let promesse: Promise<number> | null = null

/** Valeur courante en mémoire (70 par défaut, mis à jour après chargement). */
export function partAvance(): number
{
  return cache ?? 70
}

/** Charge le pourcentage depuis les paramètres serveur (idempotent, en cache). */
export async function chargerPartAvance(): Promise<number>
{
  if (cache !== null) return cache
  if (promesse) return promesse

  promesse = api.get('/api/admin/settings').then(({ data }) => {
    const v = data.data?.partAvance
    cache = typeof v === 'number' ? v : 70
    return cache
  }).catch(() => {
    cache = 70
    return 70
  }).finally(() => {
    promesse = null
  })

  return promesse
}

/** Réactif : mis à jour automatiquement après `chargerPartAvance()`. */
const partAvanceRef = shallowRef<number>(partAvance())

/**
 * Pourcentage d'avance paramétrable (SUPER_ADMIN, clé `partAvance`).
 * Valeur par défaut : 70.
 * Le résultat est mis en cache en mémoire pour éviter de requêter à chaque
 * calcul — le paramètre change rarement et une staleness d'une session est
 * sans impact financier.
 */
export function usePartAvance()
{
  return { partAvance: partAvanceRef, charger: chargerPartAvance }
}