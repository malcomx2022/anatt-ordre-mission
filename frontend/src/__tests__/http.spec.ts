import { describe, it, expect } from 'vitest'

import { unwrap, toPage } from '../services/http'

/*
 * unwrap doit distinguer « enveloppe dont data vaut null » de « pas d'enveloppe ».
 * Le bug historique — payload?.data ?? payload — renvoyait l'enveloppe entière
 * quand data valait null, transformant une réponse « aucun état » en objet
 * truthy et faisant planter le rendu de l'écran État de paiement (page blanche).
 */
describe('unwrap', () => {
  it('renvoie null quand l’enveloppe porte data: null', () => {
    expect(unwrap({ data: null, message: 'ok' })).toBeNull()
  })

  it('déballe l’objet contenu dans data', () => {
    expect(unwrap({ data: { id: 7, statut: 'BROUILLON' } })).toEqual({ id: 7, statut: 'BROUILLON' })
  })

  it('renvoie la charge telle quelle en l’absence de clé data', () => {
    expect(unwrap({ id: 42 })).toEqual({ id: 42 })
  })

  it('préserve une valeur data falsy mais légitime', () => {
    expect(unwrap({ data: 0 })).toBe(0)
    expect(unwrap({ data: '' })).toBe('')
    expect(unwrap({ data: false })).toBe(false)
  })

  it('tolère une charge nulle ou primitive', () => {
    expect(unwrap(null)).toBeNull()
    expect(unwrap('brut')).toBe('brut')
  })
})

describe('toPage', () => {
  it('normalise une réponse paginée', () => {
    const page = toPage({ data: [{ id: 1 }], meta: { current_page: 2, last_page: 5, per_page: 10, total: 42 } })
    expect(page.items).toHaveLength(1)
    expect(page.meta.current_page).toBe(2)
    expect(page.meta.total).toBe(42)
  })

  it('retombe sur une page vide sans données', () => {
    expect(toPage({}).items).toEqual([])
  })
})
