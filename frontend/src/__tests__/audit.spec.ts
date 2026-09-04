import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Journal d'audit : les options des filtres « Action » et « Entité » doivent
 * refléter les codes RÉELLEMENT émis par le backend. Une option morte (jamais
 * émise) ne filtre rien ; un code réel manquant est infiltrable. Ce test
 * verrouille l'alignement.
 */

const list = vi.fn()
vi.mock('../services/audit', () => ({
  auditService: { list: (...a: unknown[]) => list(...a) },
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  AppPagination: defineComponent({ setup: () => () => h('div') }),
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/audit/AuditListView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  return w
}

function valeursOptions(w: Awaited<ReturnType<typeof monter>>) {
  return w.findAll('option').map((o) => o.attributes('value')).filter((v): v is string => !!v)
}

beforeEach(() => {
  list.mockReset()
  list.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
})

describe('AuditListView — alignement des filtres', () => {
  it('propose les actions réellement émises par le backend', async () => {
    const options = valeursOptions(await monter())
    for (const code of [
      'IMPORT_OM', 'VALIDATE_OM', 'AMEND_OM', 'REJECT_OM',
      'RECAP_ETAT', 'CONTROL_ETAT', 'VISA_DAF_ETAT', 'APPROVE_DG_ETAT', 'REJECT_ETAT',
      'GENERATE_LOT', 'CONFIRM_LOT', 'ERROR_LOT', 'RECOUVRER_VERSEMENT',
      'ROLE_CREATE', 'ROLE_RENAME', 'ROLE_DELETE', 'ROLE_PERM_UPDATE', 'ROLE_PERM_RESET', 'USER_PERM_UPDATE',
      'LOGIN',
    ]) {
      expect(options).toContain(code)
    }
  })

  it('ne propose PAS d’actions mortes (jamais émises)', async () => {
    const options = valeursOptions(await monter())
    for (const mort of ['VERIFY_OM', 'VISA_DAF_OM', 'APPROVE_DG_OM']) {
      expect(options).not.toContain(mort)
    }
  })

  it('propose les entités réelles, y compris versement / role / role_permissions', async () => {
    const options = valeursOptions(await monter())
    for (const ent of ['ordre_mission', 'etat_paiement', 'lot_paiement', 'versement', 'bareme', 'user', 'role', 'role_permissions', 'session']) {
      expect(options).toContain(ent)
    }
  })
})
