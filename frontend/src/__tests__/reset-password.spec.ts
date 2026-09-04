import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Réinitialisation de mot de passe : le formulaire doit transmettre l'email
 * (porté par le lien ?email=...) en plus du token — sans lui, l'API rejette la
 * demande (422). Sans email dans le lien, on refuse localement avec un message.
 */

const post = vi.fn()
vi.mock('../composables/useApi', () => ({ useApi: () => ({ post: (...a: unknown[]) => post(...a) }) }))

// Route stubbée : token en param, email en query (comme le lien e-mail).
let query: Record<string, string> = {}
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { token: 'jeton-123' }, query }),
}))

const stubs = {
  AppLogo: defineComponent({ setup: () => () => h('div') }),
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/auth/ResetPasswordView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  return w
}

async function saisirMotDePasse(w: Awaited<ReturnType<typeof monter>>) {
  const inputs = w.findAll('input[type="password"]')
  await inputs[0]!.setValue('NouveauMotDePasse9@')
  await inputs[1]!.setValue('NouveauMotDePasse9@')
}

beforeEach(() => {
  post.mockReset()
  post.mockResolvedValue({ data: {} })
  query = {}
})

describe('ResetPasswordView', () => {
  it('transmet le token ET l’email au backend', async () => {
    query = { email: 'agent@anatt.bj' }
    const w = await monter()
    await saisirMotDePasse(w)
    await w.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/auth/mot-de-passe/reset', expect.objectContaining({
      token: 'jeton-123',
      email: 'agent@anatt.bj',
      password: 'NouveauMotDePasse9@',
      password_confirmation: 'NouveauMotDePasse9@',
    }))
  })

  it('refuse localement quand le lien ne porte pas d’email', async () => {
    query = {}
    const w = await monter()
    await saisirMotDePasse(w)
    await w.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(post).not.toHaveBeenCalled()
    expect(w.text()).toContain('invalide')
  })
})
