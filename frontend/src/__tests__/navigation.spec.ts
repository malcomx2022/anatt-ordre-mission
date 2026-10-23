import { describe, it, expect } from 'vitest'

import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'
import { defineComponent, h } from 'vue'
import AppSidebar from '../components/shared/AppSidebar.vue'
import { useAuthStore } from '../stores/auth'
import { routes } from '../router'
import { NAVIGATION, toutesLesEntrees } from '../components/shared/navigation'
import { ROLE_PERMISSIONS, TOUTES_PERMISSIONS } from '../utils/permissions'
import type { Permission } from '../utils/permissions'
import type { UserRole } from '../types'

/*
 * Cohérence entre le menu, le routeur et le RBAC.
 *
 * Trois régressions silencieuses sont possibles ici, et se voient seulement à
 * l'usage, profil par profil :
 *   1. une entrée de menu qui pointe vers une route inexistante (404) ;
 *   2. une entrée visible dont la route exige un droit que l'utilisateur n'a
 *      pas (403 après le clic) ;
 *   3. une route authentifiée sans `meta.permission` alors que son écran
 *      appelle une API protégée (écran vide + 403 de l'API).
 */

/** Routes authentifiées ouvertes à tout profil : elles ne touchent que le compte lui-même. */
const ROUTES_LIBRES = ['/mon-profil', '/changer-mot-de-passe']

/** Routes publiques ou techniques, hors périmètre du RBAC. */
const HORS_RBAC = ['/login', '/mot-de-passe-oublie', '/reinitialiser/:token', '/403', '/500', '/maintenance']

const parChemin = new Map(routes.map((r) => [r.path, r]))

/** Composant neutre : on teste le menu, pas les écrans qu'il ouvre. */
const stub = defineComponent({ render: () => h('div') })

const permissionsDe = (chemin: string): Permission[] => {
  const meta = parChemin.get(chemin)?.meta as { permission?: Permission | Permission[] } | undefined
  if (!meta?.permission) return []
  return Array.isArray(meta.permission) ? meta.permission : [meta.permission]
}

describe('routeur', () => {
  it('ne déclare pas deux routes sous le même nom', () => {
    // Vue Router garde silencieusement la DERNIÈRE : /ordres et /missions ont
    // longtemps partagé le nom « missions », et les push par nom aboutissaient
    // sur l'écran de l'autre.
    const noms = routes.map((r) => r.name).filter(Boolean) as string[]
    const doublons = noms.filter((n, i) => noms.indexOf(n) !== i)

    expect([...new Set(doublons)]).toEqual([])
  })

  it('protège chaque route authentifiée par une permission', () => {
    const sansPermission = routes
      .filter((r) => (r.meta as { auth?: boolean } | undefined)?.auth)
      .map((r) => r.path)
      .filter((p) => permissionsDe(p).length === 0 && !ROUTES_LIBRES.includes(p))

    expect(sansPermission).toEqual([])
  })

  it('n’emploie que des clés de permission déclarées', () => {
    const inconnues = routes
      .flatMap((r) => permissionsDe(r.path).map((p) => `${r.path} -> ${p}`))
      .filter((entree) => !TOUTES_PERMISSIONS.includes(entree.split(' -> ')[1] as Permission))

    expect(inconnues).toEqual([])
  })

  it('n’expose aucune route protégée sans exiger l’authentification', () => {
    const orphelines = routes
      .filter((r) => permissionsDe(r.path).length > 0)
      .filter((r) => !(r.meta as { auth?: boolean } | undefined)?.auth)
      .map((r) => r.path)

    expect(orphelines).toEqual([])
  })

  it('couvre toutes les routes par une catégorie explicite', () => {
    // Empêche une route d'échapper aux contrôles ci-dessus en restant hors liste.
    const nonClassees = routes
      .map((r) => r.path)
      .filter(
        (p) =>
          !(parChemin.get(p)?.meta as { auth?: boolean; guest?: boolean } | undefined)?.auth &&
          !HORS_RBAC.includes(p) &&
          !p.startsWith('/:pathMatch'),
      )

    expect(nonClassees).toEqual([])
  })
})

describe('menu latéral', () => {
  const entrees = toutesLesEntrees()

  it('ne pointe que vers des routes déclarées', () => {
    const introuvables = entrees.map((e) => e.to).filter((to) => !parChemin.has(to))

    expect(introuvables).toEqual([])
  })

  it('exige la même permission que la route visée', () => {
    const ecarts = entrees
      .filter((e) => {
        const attendues = permissionsDe(e.to)
        // L'entrée doit exiger AU MOINS ce que la route exige, sinon elle
        // s'affiche pour un utilisateur qui sera renvoyé sur /403.
        return attendues.some((p) => e.permission !== p)
      })
      .map((e) => `${e.to} : menu=${e.permission ?? '—'} route=${permissionsDe(e.to).join('|') || '—'}`)

    expect(ecarts).toEqual([])
  })

  it('ne comporte ni doublon ni lien mort', () => {
    const chemins = entrees.map((e) => e.to)

    expect(chemins.filter((c, i) => chemins.indexOf(c) !== i)).toEqual([])
  })

  it('écrit ses libellés en français accentué', () => {
    // Le menu affichait « Etats de paiement », « Generer MTN », « Parametres »…
    // alors que les écrans et la documentation portent les accents.
    const sansAccent = /\b(Etats?|Generer|Regularisations|Reconciliation|Baremes|percus|Systeme|Parametres|roles)\b/
    const libelles = [...NAVIGATION.map((g) => g.title), ...entrees.map((e) => e.label)]

    expect(libelles.filter((l) => sansAccent.test(l))).toEqual([])
  })

  it('rend accessible tout écran métier depuis le menu', () => {
    // Les écrans atteignables uniquement par une URL tapée à la main sont des
    // écrans morts : /admin/roles, /admin/email, /admin/sauvegardes et les
    // trois écrans Paiements l'ont été.
    const parametrees = /:/
    const depuisLeMenu = new Set(entrees.map((e) => e.to))

    const inaccessibles = routes
      .filter((r) => (r.meta as { auth?: boolean } | undefined)?.auth)
      .map((r) => r.path)
      .filter(
        (p) =>
          !parametrees.test(p) && !depuisLeMenu.has(p) && !ROUTES_LIBRES.includes(p),
      )

    expect(inaccessibles).toEqual([])
  })
})

describe('accès effectif par profil', () => {
  const profils = Object.keys(ROLE_PERMISSIONS) as UserRole[]

  it.each(profils)('n’affiche à %s que des écrans qu’il peut ouvrir', (profil) => {
    const droits = ROLE_PERMISSIONS[profil]

    const visibles = toutesLesEntrees().filter((e) => !e.permission || droits.includes(e.permission))
    const refusees = visibles
      .filter((e) => permissionsDe(e.to).some((p) => !droits.includes(p)))
      .map((e) => e.to)

    expect(refusees).toEqual([])
  })

  it('laisse au LECTEUR un menu strictement en lecture', () => {
    const droits = ROLE_PERMISSIONS.LECTEUR
    const visibles = toutesLesEntrees()
      .filter((e) => !e.permission || droits.includes(e.permission))
      .map((e) => e.to)

    // Ni personnel, ni paiements, ni barème, ni administration ; ni la
    // retranscription, réservée au gestionnaire.
    expect(visibles).toEqual([
      '/',
      '/missions',
      '/ordres',
      '/etats',
      '/rapports',
      '/rapports/mensuel',
      '/rapports/agents',
      '/rapports/trop-percus',
      '/rapports/export',
    ])
  })

  it('ouvre au DG la validation finale sans lui donner la main sur les paiements', () => {
    const droits = ROLE_PERMISSIONS.DG

    expect(droits).toContain('om.approuver')
    expect(droits).not.toContain('paiements.generer')
    expect(droits).not.toContain('paiements.confirmer')
  })
})

describe('miroir du menu', () => {
  it('regroupe les entrées sans groupe vide', () => {
    for (const groupe of NAVIGATION) {
      expect(groupe.items.length).toBeGreaterThan(0)
    }
  })
})

describe('rendu du menu', () => {
  const monter = async (chemin: string, role: UserRole) => {
    const routeur = createRouter({
      history: createMemoryHistory(),
      // Mêmes chemins et mêmes métadonnées, écrans neutralisés.
      routes: routes.map((r) => ({ path: r.path, name: r.name, component: stub, meta: r.meta })),
    })
    setActivePinia(createPinia())
    useAuthStore().$patch({
      user: { id: 1, name: 'Test', email: 't@anatt.bj', role, actif: true } as never,
      permissions: [...ROLE_PERMISSIONS[role]],
    })

    routeur.push(chemin)
    await routeur.isReady()

    return mount(AppSidebar, { global: { plugins: [routeur] } })
  }

  it('déplie les sous-écrans de la section courante seulement', async () => {
    const wrapper = await monter('/paiements', 'AGENT_FINANCIER')
    const liens = wrapper.findAll('a').map((a) => a.attributes('href'))

    expect(liens).toContain('/paiements/mtn')
    expect(liens).toContain('/paiements/statuts')
    // Section Rapports fermée : ses sous-écrans restent masqués.
    expect(liens).not.toContain('/rapports/mensuel')
  })

  it('reste déplié lorsqu’on se trouve sur un sous-écran', async () => {
    const wrapper = await monter('/admin/roles', 'SUPER_ADMIN')

    expect(wrapper.findAll('a').map((a) => a.attributes('href'))).toContain('/admin/roles')
  })

  it('masque au LECTEUR les sections auxquelles il n’a pas droit', async () => {
    const wrapper = await monter('/', 'LECTEUR')
    const texte = wrapper.text()

    expect(texte).toContain('Ordres de Mission')
    expect(texte).not.toContain('Personnel')
    expect(texte).not.toContain('Paiements')
    expect(texte).not.toContain('Utilisateurs')
  })
})
