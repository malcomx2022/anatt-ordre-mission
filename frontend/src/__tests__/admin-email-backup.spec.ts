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
