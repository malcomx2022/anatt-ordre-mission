import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Assainissement des écrans d'administration :
 *  - Configuration Email : plus de bouton « Envoyer un test » (il déclenchait en
 *    réalité un lien de réinitialisation de mot de passe, jamais un vrai test SMTP)
 *    et un bandeau rappelle que le transport réel vient du .env serveur.
 *  - Sauvegardes : plus de bascule/fréquence/rétention factices (jamais persistées
 *    ni appliquées) — la planification vit dans le cron serveur.
 */

const get = vi.fn()
const put = vi.fn()
const post = vi.fn()
const del = vi.fn()
vi.mock('../composables/useApi', () => ({
  useApi: () => ({ get, put, post, delete: del }),
}))
vi.mock('../composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }),
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
}

beforeEach(() => {
  get.mockReset(); put.mockReset(); post.mockReset(); del.mockReset()
  get.mockResolvedValue({ data: { data: {} } })
})

describe('EmailConfigView — assainissement', () => {
  async function monter() {
    const { default: View } = await import('../views/admin/EmailConfigView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    return w
  }

  it('n’expose plus de bouton « Envoyer un test »', async () => {
    const w = await monter()
    const labels = w.findAll('button').map((b) => b.text())
    expect(labels.some((t) => t.includes('test'))).toBe(false)
  })

  it('rappelle que le transport réel est piloté par le .env serveur', async () => {
    const w = await monter()
    expect(w.text()).toContain('.env')
    expect(w.text().toLowerCase()).toContain('indicatif')
  })
})

describe('EmailConfigView — notifications réellement envoyées', () => {
  // Miroir de backend/app/Support/NotificationContenu.php (7 types) : l'écran
  // présentait des modèles de l'ancien circuit (« chef de service », « approuvé par la DG »).
  it('liste les 7 notifications du circuit actuel, avec leur destinataire', async () => {
    const { default: View } = await import('../views/admin/EmailConfigView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    const texte = w.text()
    for (const sujet of [
      'OM {reference_semo} validé — paiement préparable',
      'OM {reference_semo} rejeté',
      'Lot {tranche} {operateur} confirmé',
      'État de paiement {numero} — signature attendue',
      'État de paiement {numero} validé — fichiers générables',
      'État de paiement {numero} rejeté',
      'Trop-perçu sur OM {reference_semo} — régularisation à traiter',
    ]) expect(texte).toContain(sujet)
    expect(texte).toContain('om.viser')
    expect(texte).not.toContain('chef de service')
    expect(texte).not.toContain('Mission soumise')
  })
})

describe('BackupView — assainissement', () => {
  async function monter() {
    const { default: View } = await import('../views/admin/BackupView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    return w
  }

  it('ne propose plus de réglage de fréquence/rétention factice', async () => {
    const w = await monter()
    expect(w.findAll('select')).toHaveLength(0)
    expect(w.text()).toContain('cron')
  })
})
