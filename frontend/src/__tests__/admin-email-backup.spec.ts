import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Assainissement des écrans d'administration :
 *  - Configuration e-mail : appliquée par le serveur depuis le 28/10/2026 ; le
 *    bouton « Envoyer un e-mail de test » enregistre puis appelle le vrai test
 *    SMTP (POST /api/admin/settings/test-email) — l'ancien bouton déclenchait un
 *    lien de réinitialisation de mot de passe, jamais un test.
 *  - Sauvegardes : plus de bascule/fréquence/rétention factices (jamais persistées
 *    ni appliquées) — la planification vit dans le cron serveur.
 */

const get = vi.fn()
const put = vi.fn()
const post = vi.fn()
const del = vi.fn()
const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('../composables/useApi', () => ({
  useApi: () => ({ get, put, post, delete: del }),
  getErrorMessage: (e: { response?: { data?: { message?: string } } }) => e.response?.data?.message ?? 'Erreur',
}))
vi.mock('../composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: vi.fn() }),
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
}

beforeEach(() => {
  get.mockReset(); put.mockReset(); post.mockReset(); del.mockReset()
  toastSuccess.mockReset(); toastError.mockReset()
  get.mockResolvedValue({ data: { data: {} } })
})

describe('EmailConfigView — configuration appliquée', () => {
  async function monter() {
    const { default: View } = await import('../views/admin/EmailConfigView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    return w
  }
  const bouton = (w: Awaited<ReturnType<typeof monter>>, libelle: string) =>
    w.findAll('button').find((b) => b.text().includes(libelle))!

  it('n’est plus présentée comme indicative', async () => {
    const texte = (await monter()).text()
    expect(texte.toLowerCase()).not.toContain('indicatif')
    expect(texte).toContain('Serveur vide')
    expect(texte).toContain('.env')
  })

  it('enregistre l’expéditeur (adresse et nom)', async () => {
    put.mockResolvedValue({ data: { data: {} } })
    const w = await monter()
    const champs = w.findAll('input')
    await champs.find((i) => i.attributes('type') === 'email')!.setValue('missions@anatt.bj')
    await champs.find((i) => i.attributes('maxlength') === '100')!.setValue('ANaTT Missions')
    await bouton(w, 'Enregistrer').trigger('click')
    await flushPromises()
    expect(put).toHaveBeenCalledWith('/api/admin/settings', expect.objectContaining({
      fromAddress: 'missions@anatt.bj', fromName: 'ANaTT Missions',
    }))
    expect(toastSuccess).toHaveBeenCalledWith('Configuration e-mail enregistrée.')
  })

  it('affiche le refus du serveur au lieu de le taire', async () => {
    put.mockRejectedValue({ response: { data: { message: 'Le port SMTP doit être un entier entre 1 et 65535.' } } })
    const w = await monter()
    await bouton(w, 'Enregistrer').trigger('click')
    await flushPromises()
    expect(toastError).toHaveBeenCalledWith('Le port SMTP doit être un entier entre 1 et 65535.')
  })

  it('« Envoyer un e-mail de test » enregistre puis appelle le vrai test SMTP', async () => {
    put.mockResolvedValue({ data: { data: {} } })
    post.mockResolvedValue({ data: { data: { destinataire: 'sa@anatt.bj', transport: 'smtp' }, message: 'E-mail de test envoyé à sa@anatt.bj.' } })
    const w = await monter()
    await bouton(w, 'e-mail de test').trigger('click')
    await flushPromises()
    expect(put).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/api/admin/settings/test-email')
    expect(toastSuccess).toHaveBeenCalledWith('E-mail de test envoyé à sa@anatt.bj.')
  })

  it('n’envoie pas de test si l’enregistrement est refusé, et rend compte d’un échec d’envoi', async () => {
    put.mockRejectedValueOnce({ response: { data: { message: 'Chiffrement attendu : TLS, SSL ou Aucun.' } } })
    const w = await monter()
    await bouton(w, 'e-mail de test').trigger('click')
    await flushPromises()
    expect(post).not.toHaveBeenCalled()

    put.mockResolvedValue({ data: { data: {} } })
    post.mockRejectedValue({ response: { data: { message: "Échec de l'envoi : Connection refused" } } })
    await bouton(w, 'e-mail de test').trigger('click')
    await flushPromises()
    expect(toastError).toHaveBeenLastCalledWith("Échec de l'envoi : Connection refused")
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
