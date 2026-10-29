/*
 * Outils communs aux scripts de recette (Node 22, fetch natif, aucune dépendance).
 *
 * - client HTTP vers l'API réellement servie (API_URL, défaut http://127.0.0.1:8000) ;
 * - connexion des comptes de démonstration, avec attente sur 429 (limite de connexions par minute,
 *   comme docs/capturer_ecrans.cjs) — les autres limites (60 appels/min, 5 opérations de paiement/min)
 *   sont gérées de la même façon : on attend la fenêtre suivante et on rejoue la requête ;
 * - assertions : la première qui échoue arrête le script (code de sortie 1) ;
 * - lecture d'un classeur .xlsx (zip + XML) sans bibliothèque, pour contrôler le fichier Moov.
 */
const zlib = require('zlib')

const API = (process.env.API_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '')
const MOT_DE_PASSE = process.env.RECETTE_PASSWORD || 'password'

const COMPTES = {
  superadmin: 'superadmin@anatt.bj',
  admin: 'admin@anatt.bj',
  gestion: 'gestion@anatt.bj',
  finance: 'finance@anatt.bj',
  controle: 'controle@anatt.bj',
  daf: 'daf@anatt.bj',
  dg: 'dg@anatt.bj',
  lecteur: 'lecteur@anatt.bj',
}

const jetons = {}
let nbControles = 0
let sectionCourante = ''

const pause = (ms) => new Promise((ok) => setTimeout(ok, ms))

/** Titre d'une section du récapitulatif. */
function section(titre) {
  sectionCourante = titre
  console.log(`\n== ${titre}`)
}

/** Échec : message clair puis sortie en code 1 (premier écart). */
function echec(libelle, detail) {
  console.error(`  [ECART] ${libelle}`)
  if (detail !== undefined) console.error(`          ${typeof detail === 'string' ? detail : JSON.stringify(detail)}`)
  console.error(`\nRecette interrompue au premier écart (section « ${sectionCourante} »), après ${nbControles} contrôle(s) conforme(s).`)
  process.exit(1)
}

/** Assertion booléenne. */
function controle(libelle, condition, detail) {
  if (!condition) echec(libelle, detail)
  nbControles++
  console.log(`  [OK] ${libelle}`)
}

/** Assertion d'égalité stricte (comparaison JSON pour les objets). */
function egal(libelle, obtenu, attendu) {
  const a = typeof obtenu === 'object' ? JSON.stringify(obtenu) : obtenu
  const b = typeof attendu === 'object' ? JSON.stringify(attendu) : attendu
  controle(libelle, a === b, `attendu ${JSON.stringify(attendu)}, obtenu ${JSON.stringify(obtenu)}`)
}

/** Réponse attendue en statut donné ; renvoie la réponse pour d'autres contrôles. */
function statut(libelle, rep, attendu) {
  controle(`${libelle} (HTTP ${attendu})`, rep.status === attendu,
    `HTTP ${rep.status} : ${rep.text ? rep.text.slice(0, 400) : ''}`)
  return rep
}

/** Premier motif d'un 422, tel que la SPA l'affiche (getErrorMessage). */
function premierMotif(rep) {
  const errors = rep.json?.errors
  if (errors && typeof errors === 'object') {
    for (const v of Object.values(errors)) {
      const m = Array.isArray(v) ? v[0] : v
      if (m) return String(m)
    }
  }
  return rep.json?.message ?? ''
}

/** Refus 422 portant exactement (ou contenant) le motif attendu, en français. */
function refus(libelle, rep, motif, { contient = false, code = 422 } = {}) {
  const obtenu = premierMotif(rep)
  const conforme = rep.status === code && (contient ? obtenu.includes(motif) : obtenu === motif)
  controle(`${libelle} (HTTP ${code} « ${contient ? '…' + motif + '…' : motif} »)`, conforme,
    `HTTP ${rep.status}, motif « ${obtenu} »`)
  return rep
}

/**
 * Requête vers l'API. `qui` est un nom de compte (jeton mémorisé), un jeton brut, ou null.
 * Options : body (objet JSON), accept (false : pas d'en-tête Accept), headers.
 * Sur 429, attend la fenêtre suivante (Retry-After) et rejoue, jusqu'à 6 fois.
 */
async function requete(qui, methode, chemin, { body, accept = true, headers = {} } = {}) {
  const jeton = qui ? (jetons[qui] ?? qui) : null
  const url = chemin.startsWith('http') ? chemin : `${API}${chemin.startsWith('/up') ? '' : '/api'}${chemin}`
  for (let essai = 0; ; essai++) {
    const rep = await fetch(url, {
      method: methode,
      headers: {
        ...(accept ? { Accept: 'application/json' } : {}),
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(jeton ? { Authorization: `Bearer ${jeton}` } : {}),
        ...headers,
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
    if (rep.status === 429 && essai < 6) {
      const attente = Math.max(1, parseInt(rep.headers.get('retry-after') || '60', 10)) + 1
      console.log(`  ... limite de débit atteinte (${methode} ${chemin}) : attente de ${attente} s`)
      await pause(attente * 1000)
      continue
    }
    const buffer = Buffer.from(await rep.arrayBuffer())
    const type = rep.headers.get('content-type') || ''
    const text = /json|text|xml|html/.test(type) ? buffer.toString('utf8') : ''
    let json = null
    if (type.includes('json')) {
      try { json = JSON.parse(text) } catch { json = null }
    }
    return { status: rep.status, headers: rep.headers, type, buffer, text, json }
  }
}

const get = (qui, chemin, o) => requete(qui, 'GET', chemin, o)
const post = (qui, chemin, body, o = {}) => requete(qui, 'POST', chemin, { ...o, body: body ?? {} })
const put = (qui, chemin, body, o = {}) => requete(qui, 'PUT', chemin, { ...o, body: body ?? {} })

/** Exige un succès (2xx) sans compter de contrôle : étapes de préparation. */
async function exiger(promesse, libelle) {
  const rep = await promesse
  if (rep.status < 200 || rep.status >= 300) echec(libelle, `HTTP ${rep.status} : ${rep.text.slice(0, 400)}`)
  return rep
}

/** Connecte un compte de démonstration (attente sur 429 gérée par requete()). */
async function connecter(compte, motDePasse = MOT_DE_PASSE) {
  const rep = await post(null, '/auth/login', { email: COMPTES[compte] ?? compte, password: motDePasse })
  if (rep.status !== 200 || !rep.json?.data?.token) {
    echec(`connexion de ${COMPTES[compte] ?? compte}`, `HTTP ${rep.status} : ${rep.text.slice(0, 300)}`)
  }
  jetons[compte] = rep.json.data.token
  return rep.json.data
}

/** Attend que l'API réponde (/up), jusqu'à `secondes`. */
async function attendreApi(secondes = 60) {
  for (let i = 0; i < secondes; i++) {
    try {
      const rep = await fetch(`${API}/up`)
      if (rep.ok) return
    } catch { /* pas encore démarrée */ }
    await pause(1000)
  }
  echec(`API joignable sur ${API}`, 'aucune réponse de /up')
}

/** Nom de fichier d'un Content-Disposition. */
function nomFichier(rep) {
  const cd = rep.headers.get('content-disposition') || ''
  const m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
  return m ? decodeURIComponent(m[1]) : null
}

const estPdf = (rep) => rep.status === 200 && rep.buffer.subarray(0, 5).toString('latin1') === '%PDF-'

/* ── Lecture minimale d'un .xlsx (zip) ─────────────────────────────────────── */

/** Extrait les entrées d'une archive zip (méthodes 0 et 8) depuis le répertoire central. */
function lireZip(buf) {
  let fin = -1
  for (let i = buf.length - 22; i >= 0; i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { fin = i; break }
  }
  if (fin < 0) throw new Error('archive zip invalide')
  const nb = buf.readUInt16LE(fin + 10)
  let p = buf.readUInt32LE(fin + 16)
  const fichiers = {}
  for (let n = 0; n < nb; n++) {
    const methode = buf.readUInt16LE(p + 10)
    const taille = buf.readUInt32LE(p + 20)
    const lgNom = buf.readUInt16LE(p + 28)
    const lgExtra = buf.readUInt16LE(p + 30)
    const lgComm = buf.readUInt16LE(p + 32)
    const local = buf.readUInt32LE(p + 42)
    const nom = buf.subarray(p + 46, p + 46 + lgNom).toString('utf8')
    const debut = local + 30 + buf.readUInt16LE(local + 26) + buf.readUInt16LE(local + 28)
    const brut = buf.subarray(debut, debut + taille)
    fichiers[nom] = methode === 8 ? zlib.inflateRawSync(brut) : brut
    p += 46 + lgNom + lgExtra + lgComm
  }
  return fichiers
}

const deXml = (s) => s.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&amp;/g, '&')

/**
 * Lit la première feuille d'un .xlsx : { nomFeuille, cellules: { A1: { valeur, type } } }.
 * type : 's' (chaîne partagée), 'inlineStr', 'str' ou 'n' (numérique).
 */
function lireXlsx(buf) {
  const f = lireZip(buf)
  const classeur = f['xl/workbook.xml'].toString('utf8')
  const nomFeuille = deXml((classeur.match(/<sheet [^>]*name="([^"]+)"/) || [])[1] || '')
  const partagees = []
  if (f['xl/sharedStrings.xml']) {
    for (const si of f['xl/sharedStrings.xml'].toString('utf8').match(/<si>[\s\S]*?<\/si>/g) || []) {
      partagees.push(deXml((si.match(/<t[^>]*>[\s\S]*?<\/t>/g) || []).map((t) => t.replace(/<[^>]+>/g, '')).join('')))
    }
  }
  const feuille = f['xl/worksheets/sheet1.xml'].toString('utf8')
  const cellules = {}
  for (const c of feuille.match(/<c [^>]*?(?:\/>|>[\s\S]*?<\/c>)/g) || []) {
    const ref = (c.match(/ r="([A-Z]+\d+)"/) || [])[1]
    const type = (c.match(/ t="([^"]+)"/) || [])[1] || 'n'
    const v = (c.match(/<v>([\s\S]*?)<\/v>/) || [])[1]
    const is = (c.match(/<is>[\s\S]*?<t[^>]*>([\s\S]*?)<\/t>/) || [])[1]
    let valeur = null
    if (type === 's') valeur = partagees[parseInt(v, 10)]
    else if (type === 'inlineStr') valeur = deXml(is ?? '')
    else if (type === 'str') valeur = deXml(v ?? '')
    else valeur = v === undefined ? null : Number(v)
    cellules[ref] = { valeur, type }
  }
  return { nomFeuille, cellules }
}

/** Fin de script : récapitulatif. */
function terminer(titre) {
  console.log(`\n${titre} : ${nbControles} contrôles conformes.`)
  return nbControles
}

module.exports = {
  API, COMPTES, jetons, pause, section, controle, egal, statut, refus, premierMotif, echec,
  requete, get, post, put, exiger, connecter, attendreApi, nomFichier, estPdf, lireXlsx, terminer,
}
