import { test, expect, type Page, type Route } from '@playwright/test'

/*
 * Parcours critique (e2e) — de la connexion à la génération du fichier de paiement.
 *
 * L'API est mockée au niveau réseau (page.route) : le test exerce le VRAI SPA
 * Vue — routeur, gardes d'auth, store Pinia, intercepteur axios (jeton Bearer),
 * navigation filtrée par permission, écrans de retranscription et de paiement —
 * sans dépendre d'un backend Laravel en marche. Le comportement métier du
 * circuit de validation (contrôle → DAF → DG, séparation des tâches, idempotence)
 * reste couvert côté backend par la suite Pest (RecetteE2ETest, CoherenceCircuitsTest,
 * BarriereEtatTest…). Ici on verrouille l'assemblage de l'interface.
 *
 * Compte joué : agent financier (paiements.generer / confirmer).
 */

const USER = {
  id: 5,
  name: 'Awa FINANCE',
  email: 'finance@anatt.bj',
  role: 'AGENT_FINANCIER',
  must_change_password: false,
}

const PERMISSIONS = [
  'dashboard.consulter',
  'personnel.consulter',
  'om.consulter',
  'paiements.consulter',
  'paiements.generer',
  'paiements.confirmer',
  'baremes.consulter',
  'rapports.consulter',
]

// Une mission BOHICON avec deux bénéficiaires MTN, comme dans SCENARIO-DEMO.
const MISSION = { id: 10, objet: 'Dossiers de passation de marchés', destination: 'BOHICON' }

const ORDRES = [
  {
    id: 1,
    reference_semo: '0055000167',
    statut: 'VALIDE',
    role: 'PARTICIPANT',
    categorie: 'ORDRE_1',
    montant_total: 285000,
    agent: { id: 1, nom: 'SOUMANOU DJARA', prenoms: 'Sabirou' },
    mission: MISSION,
  },
  {
    id: 2,
    reference_semo: '0055000161',
    statut: 'VALIDE',
    role: 'PARTICIPANT',
    categorie: 'ORDRE_1',
    montant_total: 295000,
    agent: { id: 2, nom: 'ATCHA', prenoms: 'Gabriel' },
    mission: MISSION,
  },
]

const APERCU = {
  mission_id: 10,
  mission: 'Dossiers de passation de marchés — BOHICON',
  tranche: 'AVANCE',
  operateur: 'MTN',
  nb_lignes: 2,
  total: 406000,
  beneficiaires: [
    { versement_id: 11, reference_semo: '0055000167', beneficiaire: 'SOUMANOU DJARA SABIROU', telephone: '0197984877', montant: 199500 },
    { versement_id: 12, reference_semo: '0055000161', beneficiaire: 'ATCHA GABRIEL', telephone: '0196695824', montant: 206500 },
  ],
}

const META = { current_page: 1, last_page: 1, per_page: 100, total: 2 }

/**
 * Installe les réponses mockées. `state.lot` porte le cycle du lot MTN :
 * inexistant → GENERE (après génération) → CONFIRME (après confirmation).
 */
async function installMocks(page: Page): Promise<void> {
  const state: { lot: Record<string, unknown> | null } = { lot: null }

  const json = (route: Route, body: unknown, status = 200) =>
    route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) })

  await page.route('**/api/**', async (route) => {
    const req = route.request()
    const method = req.method()
    const path = new URL(req.url()).pathname

    // ── Authentification ──
    if (path.endsWith('/api/auth/login') && method === 'POST') {
      return json(route, { data: { token: 'jeton-e2e', user: USER, permissions: PERMISSIONS } })
    }
    if (path.endsWith('/api/auth/me') && method === 'GET') return json(route, { data: USER })
    if (path.endsWith('/api/auth/me/permissions') && method === 'GET') return json(route, { data: PERMISSIONS })
    if (path.endsWith('/api/auth/logout')) return json(route, {})
    if (path.endsWith('/api/admin/parametres/part-avance')) return json(route, { data: 70 })

    // ── Ordres de mission (liste : écran Ordres + alimente le sélecteur de mission) ──
    if (path.endsWith('/api/ordres') && method === 'GET') {
      return json(route, { data: ORDRES, meta: META })
    }

    // ── Génération de paiement ──
    if (path.endsWith('/api/paiements/lots/previsualiser')) return json(route, { data: APERCU })
    if (path.endsWith('/api/paiements/lots') && method === 'GET') {
      return json(route, { data: state.lot ? [state.lot] : [], meta: { current_page: 1, last_page: 1, per_page: 20, total: state.lot ? 1 : 0 } })
    }
    if (path.endsWith('/api/paiements/lots/generer') && method === 'POST') {
      // Petit lot : le backend renvoie directement le fichier (blob) à télécharger.
      state.lot = { id: 99, mission_id: 10, tranche: 'AVANCE', operateur: 'MTN', statut: 'GENERE', nb_lignes: 2, montant_total: 406000, nom_fichier: 'dossiers-de-passation-de-marches_AVANCE.txt' }
      return route.fulfill({
        status: 200,
        contentType: 'text/plain',
        headers: { 'content-disposition': 'attachment; filename="dossiers-de-passation-de-marches_AVANCE.txt"' },
        body: 'HDR,SARALY,2,406000\r\n1,MSISDN,2290197984877,199500,SOUMANOU DJARA SABIROU\r\n2,MSISDN,2290196695824,206500,ATCHA GABRIEL\r\n',
      })
    }
    if (path.match(/\/api\/paiements\/lots\/\d+\/confirmer$/) && method === 'POST') {
      if (state.lot) state.lot = { ...state.lot, statut: 'CONFIRME', confirme_le: new Date().toISOString() }
      return json(route, { data: state.lot })
    }

    // ── Repli : tout autre appel renvoie une page vide (les écrans rendent leur coquille). ──
    if (method === 'GET') return json(route, { data: [], meta: { current_page: 1, last_page: 1, per_page: 0, total: 0 } })
    return json(route, { data: null })
  })
}

test('redirige vers le login quand on demande un écran protégé sans session', async ({ page }) => {
  await installMocks(page)

  await page.goto('/paiements/mtn', { waitUntil: 'domcontentloaded' })

  await expect(page).toHaveURL(/\/login/)
  await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible()
})

test('parcours critique : connexion → mission → génération du fichier MTN → confirmation', async ({ page }) => {
  await installMocks(page)

  // 1) Écran protégé demandé sans session → garde d'auth → login (avec redirect).
  await page.goto('/paiements/mtn', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible()

  // 2) Connexion (agent financier).
  await page.getByPlaceholder('votre.email@anatt.bj').fill('finance@anatt.bj')
  await page.locator('input[type="password"]').fill('password')
  await page.getByRole('button', { name: /Se connecter/ }).click()

  // 3) Retour automatique sur l'écran demandé : le shell authentifié est rendu.
  //    (Navigation SPA — on reste dans l'application, sans rechargement complet.)
  await expect(page.getByText('Génération paiement MTN')).toBeVisible()

  // 4) La mission retranscrite (chargée depuis /api/ordres) alimente le sélecteur :
  //    on la choisit → aperçu des deux bénéficiaires MTN.
  await page.getByRole('combobox').first().selectOption({ label: 'Dossiers de passation de marchés — BOHICON' })
  await expect(page.getByText('SOUMANOU DJARA SABIROU')).toBeVisible()
  await expect(page.getByText('2 ligne(s)')).toBeVisible()

  // 5) Génération du fichier .txt (téléchargement déclenché par le navigateur).
  const telechargement = page.waitForEvent('download').catch(() => null)
  await page.getByRole('button', { name: /Générer le fichier/ }).click()
  await telechargement

  // 6) Le lot passe à « Généré » : la confirmation devient possible.
  await expect(page.getByRole('button', { name: 'Confirmer le paiement' })).toBeVisible()

  // 7) Confirmation manuelle après dépôt → lot confirmé, réinclusion bloquée.
  await page.getByRole('button', { name: 'Confirmer le paiement' }).click()
  await expect(page.getByText(/Lot confirmé/)).toBeVisible()
})
