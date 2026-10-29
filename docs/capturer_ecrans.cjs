/*
 * Captures des écrans RÉELS de l'application, pour les maquettes de docs/ (refonte du 26/10/2026).
 *
 * Prérequis : l'API et le SPA tournent sur les données de démonstration.
 *   cd backend && php artisan migrate:fresh --seed && php artisan db:seed --class=DemoCircuitSeeder
 *   php artisan serve                                   # http://localhost:8000
 *   cd frontend && VITE_API_URL=http://localhost:8000 npm run build && npx vite preview --port 5173
 *   (build de production : pas de barre d'outils de développement sur les captures)
 *
 * Usage : node docs/capturer_ecrans.cjs
 *   API_URL / APP_URL pour d'autres adresses. Écrit docs/ecrans/*.jpg et docs/ecrans/ecrans.json,
 *   que lit docs/generer_maquettes.py.
 *
 * Le script fait d'abord avancer la mission DEMO-CIRCUIT dans le circuit (récapitulatif, contrôle,
 * visa, validation DG, amendement en trop-perçu, génération d'un lot MTN) pour que chaque écran
 * montre un état réaliste. À lancer sur une base fraîchement seedée : il n'est pas rejouable tel quel.
 */
const fs = require('fs')
const path = require('path')
const { chromium } = require('playwright')

const API = process.env.API_URL || 'http://localhost:8000'
const APP = process.env.APP_URL || 'http://localhost:5173'
const SORTIE = path.join(__dirname, 'ecrans')
const DEMO = 'CircuitTest2026!'

const COMPTES = {
  superadmin: ['superadmin@anatt.bj', 'password'],
  gestion: ['gestion@anatt.bj', 'password'],
  finance: ['finance@anatt.bj', 'password'],
  controle: ['demo.controleur@anatt.bj', DEMO],
  daf: ['demo.daf@anatt.bj', DEMO],
  dg: ['demo.dg@anatt.bj', DEMO],
}

const jetons = {}

async function api(compte, methode, chemin, corps) {
  const rep = await fetch(`${API}/api${chemin}`, {
    method: methode,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(jetons[compte] ? { Authorization: `Bearer ${jetons[compte]}` } : {}),
    },
    body: corps ? JSON.stringify(corps) : undefined,
  })
  const type = rep.headers.get('content-type') || ''
  const donnees = type.includes('json') ? await rep.json() : null
  if (!rep.ok) throw new Error(`${methode} ${chemin} → ${rep.status} ${JSON.stringify(donnees)}`)
  return donnees
}

async function connecter(compte) {
  const [email, password] = COMPTES[compte]
  for (;;) {
    try {
      const r = await api(compte, 'POST', '/auth/login', { email, password })
      jetons[compte] = r.data.token
      return
    } catch (e) {
      // Limite de 5 connexions par minute (throttle « login ») : on attend la minute suivante.
      if (!String(e.message).includes('→ 429')) throw e
      await new Promise((ok) => setTimeout(ok, 61000))
    }
  }
}

/** Fait avancer la mission de démonstration pour peupler les écrans. */
async function preparer() {
  for (const c of Object.keys(COMPTES)) await connecter(c)

  const missions = (await api('gestion', 'GET', '/missions?per_page=100')).data
  const demo = missions.find((m) => (m.objet || '').includes('DEMO-CIRCUIT') || (m.imputation_budgetaire || '').includes('DEMO-CIRCUIT'))
    ?? missions[missions.length - 1]
  // Mission la plus ancienne (OM retranscrits, non validés) : récapitulatif en attente de contrôle.
  const aTraiter = missions.filter((m) => m.id !== demo.id && !m.etat_statut).sort((a, b) => a.id - b.id)[0]

  // Mission DEMO : état des avances jusqu'à la validation DG.
  const etat = (await api('gestion', 'POST', `/missions/${demo.id}/etat/recapituler`, { tranche: 'AVANCE' })).data
  await api('controle', 'PUT', `/etats/${etat.id}/numero`, { numero: 'EP-2026-0142' })
  await api('controle', 'POST', `/etats/${etat.id}/controler`)
  await api('daf', 'POST', `/etats/${etat.id}/viser`)
  await api('dg', 'POST', `/etats/${etat.id}/approuver`)

  // Un OM réduit après paiement de l'avance → trop-perçu (écran Régularisations).
  const ordres = (await api('gestion', 'GET', '/ordres?per_page=100')).data
  const omDemo = ordres.filter((o) => (o.mission_id ?? o.mission?.id) === demo.id)
  const reduit = omDemo[0]
  await api('gestion', 'POST', `/ordres/${reduit.id}/amender`, {
    nb_jours: 1, motif: 'Retour anticipé constaté par la direction (avenant).',
  })

  // Lot MTN de l'avance, laissé GENERE (en attente de dépôt).
  await fetch(`${API}/api/paiements/lots/generer`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${jetons.finance}`, 'Content-Type': 'application/json', Accept: '*/*' },
    body: JSON.stringify({ mission_id: demo.id, tranche: 'AVANCE', operateur: 'MTN' }),
  })

  // Une autre mission : récapitulatif établi, en attente de contrôle (file de travail).
  if (aTraiter) {
    try { await api('gestion', 'POST', `/missions/${aTraiter.id}/etat/recapituler`, { tranche: 'AVANCE' }) } catch (e) { console.warn(e.message) }
  }

  const agent = (await api('superadmin', 'GET', '/agents?per_page=5')).data[0]
  const audit = (await api('superadmin', 'GET', '/audit?per_page=5')).data[0]
  const lecteur = (await api('superadmin', 'GET', '/admin/users?search=lecteur')).data[0]
  return { demo: demo.id, om: reduit.id, agent: agent.id, audit: audit.id, user: lecteur.id }
}

/** Écran de génération : sélectionne la mission de démonstration pour afficher l'aperçu et le lot. */
async function choisirMission(page) {
  await page.getByRole('button', { name: /Rechercher une mission/ }).click()
  await page.getByPlaceholder(/Rechercher une mission/).fill('Essai')
  await page.getByRole('listbox').getByRole('option').first().click()
  await page.waitForLoadState('networkidle')
}

/** Écrans capturés : [fichier, route, compte, action facultative avant la capture]. */
function ecrans(ids) {
  return [
    ['01_login', '/login', null],
    ['02_mot_de_passe_oublie', '/mot-de-passe-oublie', null],
    ['03_reinitialisation_mdp', '/reinitialiser/demo?email=agent%40anatt.bj', null],
    ['04_dashboard', '/', 'superadmin'],
    ['05_personnel_liste', '/agents', 'gestion'],
    ['06_formulaire_personnel', '/agents/nouveau', 'gestion'],
    ['07_detail_personnel', `/agents/${ids.agent}`, 'gestion'],
    ['08_modification_personnel', `/agents/${ids.agent}/modifier`, 'gestion'],
    ['09_import_personnel', '/agents/import', 'gestion'],
    ['11_liste_ordres_mission', '/ordres', 'gestion'],
    ['12_om_creation', '/ordres/nouveau', 'gestion'],
    ['15_detail_om', `/ordres/${ids.om}`, 'gestion'],
    ['17_etat_paiement', `/missions/${ids.demo}/etat`, 'controle'],
    ['18_file_etats', '/etats', 'controle'],
    ['19_generation_mtn', '/paiements/mtn', 'finance', choisirMission],
    ['20_lots_paiement', '/paiements', 'finance'],
    ['22_reconciliation', '/paiements/reconciliation', 'finance'],
    ['23_consultation_baremes', '/taux', 'superadmin'],
    ['24_modification_baremes', '/taux/configurer', 'superadmin'],
    ['25_rapports', '/rapports', 'superadmin'],
    ['26_rapport_mensuel', '/rapports/mensuel', 'superadmin'],
    ['27_rapport_personnel', '/rapports/agents', 'superadmin'],
    ['28_export_rapports', '/rapports/export', 'superadmin'],
    ['29_journal_audit', '/audit', 'superadmin'],
    ['30_detail_action_audit', `/audit/${ids.audit}`, 'superadmin'],
    ['31_gestion_utilisateurs', '/admin/utilisateurs', 'superadmin'],
    ['32_formulaire_utilisateur', '/admin/utilisateurs/nouveau', 'superadmin'],
    ['33_gestion_roles', '/admin/roles', 'superadmin'],
    ['34_parametres', '/admin/parametres', 'superadmin'],
    ['35_profil_utilisateur', '/mon-profil', 'gestion'],
    ['38_page_404', '/page-inexistante', 'gestion'],
    ['39_page_403', '/403', 'gestion'],
    ['40_page_500', '/500', 'gestion'],
    ['41_page_maintenance', '/maintenance', null],
    // Écrans sans maquette d'origine
    ['42_missions', '/missions', 'gestion'],
    ['43_pdf_om', `/ordres/${ids.om}/pdf`, 'gestion'],
    ['44_historique_om', `/ordres/${ids.om}/historique`, 'gestion'],
    ['45_paiements_statuts', '/paiements/statuts', 'finance'],
    ['46_paiements_historique', '/paiements/historique', 'finance'],
    ['47_regularisations', '/paiements/regularisations', 'finance'],
    ['48_rapport_trop_percus', '/rapports/trop-percus', 'superadmin'],
    ['49_permissions_utilisateur', `/admin/utilisateurs/${ids.user}/permissions`, 'superadmin'],
    ['50_signatures', '/admin/signatures', 'superadmin'],
    ['51_config_email', '/admin/email', 'superadmin'],
    ['52_sauvegardes', '/admin/sauvegardes', 'superadmin'],
    ['53_moov', '/paiements/moov', 'finance', choisirMission],
  ]
}

/** Contexte de navigation, connecté au compte s'il y en a un (jeton posé avant le chargement du SPA). */
async function nouveauContexte(navigateur, compte) {
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', timezoneId: 'Africa/Porto-Novo' })
  if (compte) {
    await contexte.addInitScript((jeton) => localStorage.setItem('auth_token', jeton), jetons[compte])
  }
  return contexte
}

async function main() {
  fs.mkdirSync(SORTIE, { recursive: true })
  const ids = await preparer()
  // LANG / LANGUAGE : sans eux, le Chromium sans écran ignore --lang pour les champs date (« mm/dd/yyyy »).
  const navigateur = await chromium.launch({
    args: ['--lang=fr-FR'],
    env: { ...process.env, LANG: 'fr_FR.UTF-8', LANGUAGE: 'fr' },
  })
  const index = []

  for (const [fichier, route, compte, action] of ecrans(ids)) {
    let contexte = await nouveauContexte(navigateur, compte)
    let page = await contexte.newPage()
    await page.goto(APP + route, { waitUntil: 'networkidle' })
    // Session perdue (limite de 60 requêtes/min par compte : un 429 au rechargement déconnecte
    // le SPA, qui révoque le jeton) : nouvelle connexion après la minute, puis nouvel essai.
    if (compte && page.url().includes('/login')) {
      await contexte.close()
      await new Promise((ok) => setTimeout(ok, 61000))
      await connecter(compte)
      contexte = await nouveauContexte(navigateur, compte)
      page = await contexte.newPage()
      await page.goto(APP + route, { waitUntil: 'networkidle' })
      if (page.url().includes('/login')) throw new Error(`${fichier} : session perdue deux fois`)
    }
    if (action) await action(page)
    await page.waitForTimeout(1200) // animations d'entrée (fadeUp) et graphiques
    const cible = path.join(SORTIE, `${fichier}.jpg`)
    await page.screenshot({ path: cible, type: 'jpeg', quality: 80, fullPage: true })
    index.push({ fichier, route, compte: compte ? COMPTES[compte][0] : null, url: page.url().replace(APP, '') })
    console.log(`✓ ${fichier}  ${route}`)
    await contexte.close()
  }

  await navigateur.close()
  fs.writeFileSync(path.join(SORTIE, 'ecrans.json'), JSON.stringify({ capture_le: new Date().toISOString(), ecrans: index }, null, 2) + '\n')
}

main().catch((e) => { console.error(e); process.exit(1) })
