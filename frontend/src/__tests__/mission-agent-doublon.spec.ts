import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ref } from 'vue'

/*
 * Un même agent ne figure qu'une fois par mission (règle serveur : ordres.*.agent_id
 * distinct). Le wizard le signalait seulement à l'enregistrement (422) : il le
 * détecte désormais comme une référence SEMO en double, et bloque l'étape.
 */

vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: ref(70) }) }))
vi.mock('@/services/baremes', () => ({ baremesService: { list: vi.fn().mockResolvedValue([]) } }))
vi.mock('@/services/missions', () => ({ missionsService: { create: vi.fn() } }))

beforeEach(() => setActivePinia(createPinia()))

describe('wizard de retranscription — agent en double', () => {
  it('signale l’agent présent deux fois et bloque l’étape des bénéficiaires', async () => {
    const { useOrdresMissionStore } = await import('@/stores/ordres-mission')
    const store = useOrdresMissionStore()
    const commun = { categorie: 'INDICE_SUP_800' as const, type_prise_en_charge: 'JOURNEE_COMPLETE' as const }
    store.ajouterBeneficiaire({ ...commun, agent_id: 7, reference_semo: 'SEMO-1' })
    store.ajouterBeneficiaire({ ...commun, agent_id: 7, reference_semo: 'SEMO-2' })

    expect(store.agentsEnDoublon.has(7)).toBe(true)
    expect(store.beneficiaireValide(store.beneficiaires[0]!)).toBe(false)
    expect(store.champsManquants(store.beneficiaires[1]!)).toContain('agent déjà présent dans la mission')
    expect(store.etape2Valide).toBe(false)

    store.beneficiaires[1]!.agent_id = 8
    expect(store.agentsEnDoublon.size).toBe(0)
    expect(store.etape2Valide).toBe(true)
  })
})
