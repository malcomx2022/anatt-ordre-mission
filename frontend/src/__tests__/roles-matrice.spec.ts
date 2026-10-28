import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import type { MatriceRoles } from '@/services/roles'

/*
 * Matrice des permissions (ADM-03, RBAC flexible).
 * - Sans `roles.gerer` (ADMIN), la matrice est en lecture seule : aucune case,
 *   aucun bouton d'enregistrement ni de gestion des rôles.
 * - Le SUPER_ADMIN (colonne verrouillée) et les clés réservées ne sont jamais
 *   cochables ; seules les colonnes réellement modifiées sont envoyées.
 * - Le serveur arbitre la séparation ordonnateur/payeur : son refus (422) est
 *   restitué tel quel et la modification reste à corriger.
 */

const matrice = vi.fn()
const remplacer = vi.fn()
const reinitialiser = vi.fn()
const creer = vi.fn()
const supprimer = vi.fn()
const renommer = vi.fn()

vi.mock('@/services', () => ({
  rolesService: {
    matrice: (...a: unknown[]) => matrice(...a),
    remplacer: (...a: unknown[]) => remplacer(...a),
    reinitialiser: (...a: unknown[]) => reinitialiser(...a),
    creer: (...a: unknown[]) => creer(...a),
    renommer: (...a: unknown[]) => renommer(...a),
    supprimer: (...a: unknown[]) => supprimer(...a),
  },
}))

let droits: string[] = []
vi.mock('@/composables/useAuth', () => ({ useAuth: () => ({ can: (p: string) => droits.includes(p) }) }))
const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: vi.fn(), show: vi.fn() }),
}))

const stubs = {
  AppLayout: defineComponent({
    props: { subtitle: { type: String, default: '' } },
    setup: (props, { slots }) => () => h('div', [h('p', { class: 'sous-titre' }, props.subtitle), slots.default?.()]),
  }),
}

const PERMISSIONS = [
  'om.consulter', 'om.approuver', 'paiements.generer', 'utilisateurs.gerer', 'roles.gerer',
] as MatriceRoles['permissions']

function reponse(): MatriceRoles {
  const profil = (cle: string, libelle: string, extra: Record<string, unknown> = {}) => ({
    cle, libelle, systeme: true, verrouille: false, personnalise: false, nb_utilisateurs: 1, ...extra,
  })
  return {
    profils: [
      profil('SUPER_ADMIN', 'Super administrateur', { verrouille: true }),
      profil('ADMIN', 'Administrateur'),
      profil('DG', 'Directeur Général', { personnalise: true }),
      profil('CAISSIER', 'Caissier régional', { systeme: false, nb_utilisateurs: 0 }),
      profil('AUDITEUR', 'Auditeur externe', { systeme: false, nb_utilisateurs: 2 }),
    ],
    permissions: [...PERMISSIONS],
    matrice: {
      SUPER_ADMIN: [],
      ADMIN: ['om.consulter', 'utilisateurs.gerer'],
      DG: ['om.consulter', 'om.approuver'],
      CAISSIER: [],
      AUDITEUR: ['om.consulter'],
    } as MatriceRoles['matrice'],
    non_assignables: ['roles.gerer'] as MatriceRoles['non_assignables'],
  }
}

async function monter() {
  const { default: View } = await import('../views/admin/RolesView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}
type Wrapper = Awaited<ReturnType<typeof monter>>

const COLONNES = ['SUPER_ADMIN', 'ADMIN', 'DG', 'CAISSIER', 'AUDITEUR']

/** Cellule (permission × profil) de la matrice. */
function cellule(w: Wrapper, perm: string, role: string) {
  const ligne = w.findAll('tbody tr').find((tr) => {
    const cle = tr.find('td span.block')
    return cle.exists() && cle.text() === perm
  })
  if (!ligne) throw new Error(`Ligne ${perm} introuvable`)
  return ligne.findAll('td')[1 + COLONNES.indexOf(role)]!
}

const bouton = (w: Wrapper, libelle: string) => w.findAll('button').find((b) => b.text().includes(libelle))

beforeEach(() => {
  droits = []
  for (const f of [matrice, remplacer, reinitialiser, creer, supprimer, renommer, toastSuccess, toastError]) f.mockReset()
  matrice.mockResolvedValue(reponse())
})
afterEach(() => vi.restoreAllMocks())

describe('RolesView — lecture seule sans roles.gerer', () => {
  it('affiche la matrice sans aucune case ni action d’écriture', async () => {
    droits = ['utilisateurs.gerer']
    const w = await monter()

    expect(w.get('.sous-titre').text()).toContain('lecture seule')
    expect(w.findAll('input[type="checkbox"]')).toHaveLength(0)
    expect(bouton(w, 'Enregistrer les modifications')).toBeUndefined()
    expect(w.text()).not.toContain('Rôles personnalisés')

    // Les droits accordés restent lisibles.
    expect(cellule(w, 'om.approuver', 'DG').find('[aria-label="Accordée"]').exists()).toBe(true)
    expect(cellule(w, 'om.approuver', 'ADMIN').text()).toBe('—')
  })
})

describe('RolesView — édition (SUPER_ADMIN)', () => {
  beforeEach(() => {
    droits = ['utilisateurs.gerer', 'roles.gerer']
  })

  it('verrouille la colonne SUPER_ADMIN et les clés réservées', async () => {
    const w = await monter()

    // SUPER_ADMIN : tout accordé, rien de cochable, même sans ligne en base.
    for (const perm of PERMISSIONS) {
      expect(cellule(w, perm, 'SUPER_ADMIN').find('input').exists()).toBe(false)
      expect(cellule(w, perm, 'SUPER_ADMIN').find('[aria-label="Accordée"]').exists()).toBe(true)
    }
    // roles.gerer n'est attribuable à aucun profil.
    for (const role of COLONNES.slice(1)) {
      expect(cellule(w, 'roles.gerer', role).find('input').exists()).toBe(false)
    }
    // Les autres cases reflètent la matrice serveur.
    expect((cellule(w, 'om.approuver', 'DG').get('input').element as HTMLInputElement).checked).toBe(true)
    expect((cellule(w, 'om.approuver', 'ADMIN').get('input').element as HTMLInputElement).checked).toBe(false)
    expect(w.get('.sous-titre').text()).toContain('Ajustez les droits')
  })

  it('n’active l’enregistrement qu’après une vraie modification', async () => {
    const w = await monter()
    const enregistrer = () => bouton(w, 'Enregistrer les modifications')!

    expect(enregistrer().attributes('disabled')).toBeDefined()

    await cellule(w, 'om.consulter', 'CAISSIER').get('input').trigger('change')
    expect(enregistrer().attributes('disabled')).toBeUndefined()
    expect(enregistrer().text()).toContain('(1)')

    // Décocher ce qu'on vient de cocher revient à l'état serveur.
    await cellule(w, 'om.consulter', 'CAISSIER').get('input').trigger('change')
    expect(enregistrer().attributes('disabled')).toBeDefined()
  })

  it('n’envoie que les profils modifiés, avec leur liste complète de permissions', async () => {
    remplacer.mockResolvedValue([])
    const w = await monter()

    await cellule(w, 'utilisateurs.gerer', 'ADMIN').get('input').trigger('change') // retrait
    await cellule(w, 'om.consulter', 'CAISSIER').get('input').trigger('change') // ajout
    await bouton(w, 'Enregistrer les modifications')!.trigger('click')
    await flushPromises()

    expect(remplacer).toHaveBeenCalledTimes(2)
    expect(remplacer).toHaveBeenCalledWith('ADMIN', ['om.consulter'])
    expect(remplacer).toHaveBeenCalledWith('CAISSIER', ['om.consulter'])
    expect(toastSuccess).toHaveBeenCalledWith('Matrice des permissions enregistrée.')
    expect(bouton(w, 'Enregistrer les modifications')!.attributes('disabled')).toBeDefined()
    // ADMIN est désormais personnalisé : il peut être réinitialisé.
    expect(bouton(w, 'Réinitialiser Administrateur')).toBeDefined()
  })

  it('restitue le refus de séparation ordonnateur/payeur et garde la modification', async () => {
    remplacer.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Données invalides.',
          errors: { permissions: ['Séparation ordonnateur/payeur : om.approuver est incompatible avec paiements.generer.'] },
        },
      },
    })
    const w = await monter()

    await cellule(w, 'paiements.generer', 'DG').get('input').trigger('change')
    await bouton(w, 'Enregistrer les modifications')!.trigger('click')
    await flushPromises()

    expect(remplacer).toHaveBeenCalledWith('DG', ['om.consulter', 'om.approuver', 'paiements.generer'])
    expect(toastError).toHaveBeenCalledWith(
      'Séparation ordonnateur/payeur : om.approuver est incompatible avec paiements.generer.',
    )
    expect(toastSuccess).not.toHaveBeenCalled()
    // Rien n'est tenu pour enregistré : la modification reste en attente.
    expect(bouton(w, 'Enregistrer les modifications')!.attributes('disabled')).toBeUndefined()
    // La règle est rappelée sous la matrice.
    expect(w.text()).toContain('séparation ordonnateur/payeur')
  })

  it('réinitialise un profil système personnalisé au défaut', async () => {
    reinitialiser.mockResolvedValue(['om.consulter', 'om.approuver', 'utilisateurs.gerer'])
    const w = await monter()

    // Seul DG est personnalisé ; un rôle non système ne se « réinitialise » pas.
    const boutons = w.findAll('button').filter((b) => b.text().startsWith('Réinitialiser'))
    expect(boutons.map((b) => b.text())).toEqual(['Réinitialiser Directeur Général'])

    await boutons[0]!.trigger('click')
    await flushPromises()

    expect(reinitialiser).toHaveBeenCalledWith('DG')
    expect((cellule(w, 'utilisateurs.gerer', 'DG').get('input').element as HTMLInputElement).checked).toBe(true)
    expect(bouton(w, 'Réinitialiser Directeur Général')).toBeUndefined()
    expect(bouton(w, 'Enregistrer les modifications')!.attributes('disabled')).toBeDefined()
  })
})

describe('RolesView — rôles personnalisés', () => {
  beforeEach(() => {
    droits = ['utilisateurs.gerer', 'roles.gerer']
  })

  it('crée un rôle avec un code en majuscules puis recharge la matrice', async () => {
    creer.mockResolvedValue(undefined)
    const w = await monter()
    const [code, libelle] = w.findAll('input[type="text"]')

    expect(bouton(w, 'Créer le rôle')!.attributes('disabled')).toBeDefined()
    await code!.setValue(' caissier_regional ')
    await libelle!.setValue(' Caissier régional ')
    await bouton(w, 'Créer le rôle')!.trigger('click')
    await flushPromises()

    expect(creer).toHaveBeenCalledWith('CAISSIER_REGIONAL', 'Caissier régional')
    expect(matrice).toHaveBeenCalledTimes(2)
    expect(toastSuccess).toHaveBeenCalledWith('Rôle « Caissier régional » créé.')
  })

  it('ne propose la suppression que d’un rôle sans utilisateur, après confirmation', async () => {
    supprimer.mockResolvedValue(undefined)
    const confirmer = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const w = await monter()

    const lignes = w.findAll('div.py-2\\.5')
    const caissier = lignes.find((l) => l.text().includes('CAISSIER'))!
    const auditeur = lignes.find((l) => l.text().includes('AUDITEUR'))!
    expect(auditeur.findAll('button')[1]!.attributes('disabled')).toBeDefined()

    await caissier.findAll('button')[1]!.trigger('click')
    await flushPromises()

    expect(confirmer).toHaveBeenCalled()
    expect(supprimer).toHaveBeenCalledWith('CAISSIER')
  })

  it('ne supprime rien si la confirmation est refusée', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const w = await monter()

    const caissier = w.findAll('div.py-2\\.5').find((l) => l.text().includes('CAISSIER'))!
    await caissier.findAll('button')[1]!.trigger('click')
    await flushPromises()

    expect(supprimer).not.toHaveBeenCalled()
  })
})
