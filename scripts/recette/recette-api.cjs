#!/usr/bin/env node
/*
 * Recette transverse sur l'API réellement démarrée (Node 22, aucune dépendance).
 *
 * Complète Pest sur ce qui n'est vrai que sur l'API servie : réponses JSON sans en-tête Accept,
 * en-têtes de sécurité, limites de débit, fichiers réellement téléchargés (.txt, .xlsx, PDF),
 * RBAC de bout en bout avec les 8 comptes de démonstration, cycles de paiement complets
 * (erreur et régénération, lot complémentaire, idempotence), régularisation d'un trop-perçu,
 * circuit par OM d'une mission sans état.
 *
 * Rejouable : chaque exécution crée ses propres agents, missions et références (suffixe unique).
 * Prérequis : base seedée (comptes de démonstration, APP_ENV=local). Voir scripts/recette/README.md.
 *   API_URL=http://127.0.0.1:8000 node scripts/recette/recette-api.cjs
 */
const {
  API, COMPTES, section, controle, egal, statut, refus, echec, requete, get, post, put, exiger,
  connecter, attendreApi, nomFichier, estPdf, lireXlsx, terminer,
} = require('./lib.cjs')

const RUN = `${Date.now().toString(36).toUpperCase()}`
const CRLF = '\r\n'
const chiffres = (n) => Array.from({ length: n }, () => Math.floor(Math.random() * 10)).join('')
/** Numéro national unique : 01 + préfixe opérateur + 6 chiffres. */
const tel = (prefixe) => `01${prefixe}${chiffres(6)}`
const ref = (n) => `REC-${RUN}-${n}`

async function creerAgent(nom, prefixe, operateur, indice = 850) {
  const rep = await exiger(post('gestion', '/agents', {
    nom, prenoms: 'Recette', matricule: `REC-${RUN}-${nom}`, indice, telephone: tel(prefixe), operateur,
  }), `création de l'agent ${nom}`)
  return rep.json.data
}

async function omParReference(reference) {
  const liste = await exiger(get('gestion', `/ordres?search=${reference}`), `recherche ${reference}`)
  const om = liste.json.data.find((o) => o.reference_semo === reference)
  if (!om) echec(`OM ${reference} introuvable`)
  return (await exiger(get('gestion', `/ordres/${om.id}`), `fiche ${reference}`)).json.data
}

const apercu = async (missionId, tranche, operateur) =>
  (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${missionId}&tranche=${tranche}&operateur=${operateur}`), 'aperçu')).json.data

const lots = async (missionId, tranche, operateur) =>
  (await exiger(get('finance', `/paiements/lots?mission_id=${missionId}&tranche=${tranche}&operateur=${operateur}`), 'lots')).json.data

const generer = (missionId, tranche, operateur, qui = 'finance') =>
  post(qui, '/paiements/lots/generer', { mission_id: missionId, tranche, operateur })

/** Établit puis fait valider l'état d'une tranche par les trois signataires. */
async function validerEtat(missionId, tranche, numero) {
  const etat = (await exiger(post('gestion', `/missions/${missionId}/etat/recapituler`, { tranche }), 'récapitulatif')).json.data
  await exiger(post('controle', `/etats/${etat.id}/controler`), 'contrôle')
  await exiger(post('daf', `/etats/${etat.id}/viser`), 'visa')
  await exiger(put('dg', `/etats/${etat.id}/numero`, { numero }), 'numéro')
  return (await exiger(post('dg', `/etats/${etat.id}/approuver`), 'validation')).json.data
}

/** Chaque appel doit être refusé en 403 JSON « Acces refuse. », même sans en-tête Accept. */
async function verifierInterdits(liste) {
  for (const [qui, methode, chemin] of liste) {
    const rep = await requete(qui, methode, chemin, { body: methode === 'GET' ? undefined : {}, accept: false })
    egal(`${qui} ${methode} ${chemin} : 403 JSON « Acces refuse. »`, [rep.status, rep.json?.message], [403, 'Acces refuse.'])
  }
}

async function main() {
  console.log(`Recette de l'API sur ${API} (exécution ${RUN})`)
  await attendreApi()

  // ── 1. API servie : JSON sans Accept, en-têtes ─────────────────────────────
  section('API servie : erreurs JSON sans en-tête Accept, en-têtes de sécurité')
  const up = await requete(null, 'GET', '/up', { accept: false })
  egal('/up répond 200', up.status, 200)

  const sansJeton = await get(null, '/auth/me', { accept: false })
  egal('sans jeton ni Accept : 401 JSON « Non authentifie. »', [sansJeton.status, sansJeton.type.includes('application/json'), sansJeton.json?.message], [401, true, 'Non authentifie.'])
  const jetonFaux = await get('1|jeton-invalide', '/ordres', { accept: false })
  egal('jeton invalide : 401 JSON', [jetonFaux.status, jetonFaux.json?.message], [401, 'Non authentifie.'])
  const inconnue = await get(null, '/route-inexistante', { accept: false })
  egal('route inconnue sans Accept : 404 JSON', [inconnue.status, inconnue.json?.message], [404, 'Route introuvable.'])
  const mauvaiseMethode = await get(null, '/auth/login', { accept: false })
  controle('méthode non permise sans Accept : 405 JSON', mauvaiseMethode.status === 405 && mauvaiseMethode.type.includes('json'), `${mauvaiseMethode.status} ${mauvaiseMethode.type}`)
  egal('en-têtes de sécurité sur une réponse API', [
    sansJeton.headers.get('x-content-type-options'), sansJeton.headers.get('x-frame-options'),
    sansJeton.headers.get('content-security-policy'), sansJeton.headers.get('referrer-policy'),
  ], ['nosniff', 'DENY', "default-src 'none'; frame-ancestors 'none'", 'strict-origin-when-cross-origin'])
  controle('pas de HSTS en HTTP', !sansJeton.headers.get('strict-transport-security'))

  const loginFaux = await post(null, '/auth/login', { email: 'lecteur@anatt.bj', password: 'mauvais-mot-de-passe' }, { accept: false })
  refus('identifiants erronés : 422 en français, sans Accept', loginFaux, 'Identifiants incorrects.')
  refus('connexion sans e-mail : motif générique traduit',
    await post(null, '/auth/login', { password: 'x' }), 'Le champ adresse e-mail est obligatoire.')

  // ── 2. Connexion des 8 comptes ────────────────────────────────────────────
  section('Connexion des 8 comptes de démonstration')
  const roles = {}
  for (const c of Object.keys(COMPTES)) {
    const d = await connecter(c)
    roles[c] = d
    controle(`${COMPTES[c]} connecté (${d.user.role}), ${d.permissions.length} permission(s)`, Boolean(d.token) && Array.isArray(d.permissions))
  }
  const me = await exiger(get('finance', '/auth/me'), '/auth/me')
  egal('/auth/me renvoie le compte connecté', me.json.data.email, 'finance@anatt.bj')
  const perms = (await exiger(get('dg', '/auth/me/permissions'), 'permissions')).json.data
  controle('DG : om.approuver sans paiements.generer/confirmer (ordonnateur ≠ payeur)',
    perms.includes('om.approuver') && !perms.includes('paiements.generer') && !perms.includes('paiements.confirmer'), perms)
  controle('LECTEUR : ni personnel.consulter ni baremes.consulter', !roles.lecteur.permissions.includes('personnel.consulter') && !roles.lecteur.permissions.includes('baremes.consulter'))

  // ── 3. RBAC : lectures autorisées, refus attendus ─────────────────────────
  section('RBAC : lectures autorisées et refus 403 attendus')
  const autorises = [
    ['lecteur', '/ordres'], ['lecteur', '/rapports/mensuel'], ['lecteur', '/missions'],
    ['gestion', '/agents'], ['finance', '/paiements/lots'], ['controle', '/audit'],
    ['admin', '/admin/roles'], ['admin', '/admin/users'], ['superadmin', '/admin/settings'],
    ['lecteur', '/admin/parametres/part-avance'], ['lecteur', '/admin/parametres/application'],
  ]
  for (const [qui, chemin] of autorises) statut(`${qui} lit ${chemin}`, await get(qui, chemin), 200)
  egal('part de l\'avance (lecture publique) : 70', (await get('lecteur', '/admin/parametres/part-avance')).json.data, 70)

  const interdits = [
    ['lecteur', 'GET', '/agents'], ['lecteur', 'GET', '/baremes'], ['lecteur', 'GET', '/audit'],
    ['lecteur', 'GET', '/paiements/lots'], ['gestion', 'POST', '/paiements/lots/generer'],
    ['finance', 'POST', '/missions'], ['finance', 'POST', '/agents'], ['dg', 'POST', '/paiements/lots/generer'],
    ['admin', 'GET', '/admin/settings'], ['admin', 'PUT', '/admin/roles/LECTEUR/permissions'],
    ['admin', 'POST', '/admin/backups'], ['finance', 'GET', '/admin/users'],
    ['daf', 'POST', '/ordres/1/valider'], ['lecteur', 'POST', '/ordres/1/amender'],
  ]
  await verifierInterdits(interdits)
  refus('séparation ordonnateur/payeur : DG + paiements.generer refusé à la matrice',
    await put('superadmin', '/admin/roles/DG/permissions', { permissions: [...perms, 'paiements.generer'] }),
    'Séparation ordonnateur/payeur : un profil qui valide l’état (om.approuver) ne peut pas aussi générer ou confirmer les paiements.')
  egal('matrice inchangée après le refus', (await exiger(get('dg', '/auth/me/permissions'), 'permissions')).json.data.includes('paiements.generer'), false)

  // ── 4. Retranscription ────────────────────────────────────────────────────
  section('Retranscription : 2 OM sur 3 jours, répartition 70/30, refus de saisie')
  const mtnA = await creerAgent(`MTNA${RUN}`, '97', 'MTN')
  const mtnB = await creerAgent(`MTNB${RUN}`, '96', 'MTN', 450)
  const moovA = await creerAgent(`MOOVA${RUN}`, '66', 'MOOV')
  const corps = {
    objet: `Recette API ${RUN}`, destination: 'Parakou', zone_code: 'INTERIEUR',
    date_depart: '2026-07-01', date_retour: '2026-07-03', prise_en_charge_tiers: false,
    ordres: [
      { reference_semo: ref(1), agent_id: mtnA.id, role: 'PARTICIPANT', type_prise_en_charge: 'JOURNEE_COMPLETE' },
      { reference_semo: ref(2), agent_id: mtnB.id, role: 'CAV', type_prise_en_charge: 'JOURNEE_COMPLETE' },
      { reference_semo: ref(3), agent_id: moovA.id, role: 'PARTICIPANT', type_prise_en_charge: 'JOURNEE_COMPLETE' },
    ],
  }
  refus('type hébergement en zone INTERIEUR refusé',
    await post('gestion', '/missions', { ...corps, ordres: [{ ...corps.ordres[0], type_prise_en_charge: 'LOGE_SEUL' }] }), 'incompatible', { contient: true })
  refus('retour avant le départ refusé',
    await post('gestion', '/missions', { ...corps, date_retour: '2026-06-01' }),
    'La date de retour doit être postérieure ou égale à la date de départ.')
  refus('agent inexistant refusé', await post('gestion', '/missions', { ...corps, ordres: [{ ...corps.ordres[0], agent_id: 99999999 }] }), "n'existe pas", { contient: true })
  const mission = statut('mission de 3 OM retranscrite', await post('gestion', '/missions', corps), 201).json.data
  const om1 = await omParReference(ref(1))
  const om2 = await omParReference(ref(2))
  egal('INDICE_SUP_800 × 3 j journée complète : 135 000 = 94 500 + 40 500', [om1.nb_jours, om1.montant_total, om1.avance, om1.reliquat], [3, 135000, 94500, 40500])
  egal('CAV payé selon SA catégorie (INDICE_400_799 : 35 000 × 3)', [om2.categorie, om2.montant_total, om2.avance, om2.reliquat], ['INDICE_400_799', 105000, 73500, 31500])
  refus('doublon SEMO refusé', await post('gestion', '/missions', { ...corps, ordres: [corps.ordres[0]] }), `La référence SEMO ${ref(1)} existe déjà (doublon interdit).`)

  // ── 5. Verrou de paiement et circuit de l'état ────────────────────────────
  section('Verrou de paiement, ordre du circuit, rejet et numéro unique')
  refus('génération refusée tant que rien n\'est validé', await generer(mission.id, 'AVANCE', 'MTN'), 'Aucun versement éligible', { contient: true })
  const etat = (await exiger(post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'AVANCE' }), 'récapitulatif')).json.data
  egal('état des avances BROUILLON : 3 bénéficiaires, 94 500 + 73 500 + 94 500', [etat.statut, etat.nb_beneficiaires, etat.montant_total], ['BROUILLON', 3, 262500])
  refus('génération refusée sur un état BROUILLON', await generer(mission.id, 'AVANCE', 'MTN'),
    'la génération des fichiers exige sa validation finale par le Directeur Général', { contient: true })
  await verifierInterdits([
    ['gestion', 'POST', `/etats/${etat.id}/controler`], ['controle', 'POST', `/etats/${etat.id}/viser`],
    ['daf', 'POST', `/etats/${etat.id}/approuver`], ['finance', 'PUT', `/etats/${etat.id}/numero`],
    ['lecteur', 'POST', `/etats/${etat.id}/rejeter`],
  ])
  refus('visa DAF impossible avant le contrôle', await post('daf', `/etats/${etat.id}/viser`), 'BROUILLON', { contient: true })
  const numero = `REC-${RUN}-A`
  egal('numéro de registre saisi dès le brouillon', (await exiger(put('dg', `/etats/${etat.id}/numero`, { numero }), 'numéro')).json.data.numero, numero)
  refus('validation DG impossible avant le visa', await post('dg', `/etats/${etat.id}/approuver`), 'BROUILLON', { contient: true })
  refus('validation par OM refusée quand la mission a un état',
    await post('controle', `/ordres/${om1.id}/valider`), 'possède un état de paiement', { contient: true })
  refus('rejet sans motif suffisant refusé', await post('controle', `/etats/${etat.id}/rejeter`, { motif: 'non' }), 'motif', { contient: true })
  await exiger(post('controle', `/etats/${etat.id}/controler`), 'contrôle')
  const rejete = statut('rejet motivé par le DAF', await post('daf', `/etats/${etat.id}/rejeter`, { motif: 'Pièces justificatives manquantes' }), 200).json.data
  egal('état REJETE, signatures amont effacées', [rejete.statut, rejete.controle, rejete.commentaire_rejet], ['REJETE', null, 'Pièces justificatives manquantes'])
  egal('récapitulatif rétabli après rejet', (await exiger(post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'AVANCE' }), 'récapitulatif')).json.data.statut, 'BROUILLON')
  await exiger(post('controle', `/etats/${etat.id}/controler`), 'contrôle')
  await exiger(post('daf', `/etats/${etat.id}/viser`), 'visa')
  egal('validation finale', (await exiger(post('dg', `/etats/${etat.id}/approuver`), 'validation')).json.data.statut, 'VALIDE')
  refus('numéro figé après la validation finale', await put('dg', `/etats/${etat.id}/numero`, { numero: `${numero}-BIS` }),
    'Cet état est validé : son numéro est figé avec le document.')
  const file = (await exiger(get('controle', `/etats?statut=VALIDE&search=${encodeURIComponent(numero)}`), 'file des états')).json
  egal('file de travail filtrée par numéro', file.data.map((e) => e.id), [etat.id])
  controle('PDF de l\'état validé', estPdf(await get('controle', `/missions/${mission.id}/etat/telecharger`)))
  const pdf = (await exiger(get('lecteur', `/ordres/${om1.id}/pdf`), 'PDF OM')).json.data
  controle('PDF de l\'OM : clé de cache avec le statut de l\'état', pdf.fichier.includes('-ETAT-VALIDE'), pdf)
  controle('PDF de l\'OM téléchargeable par le lecteur', estPdf(await get('lecteur', `/ordres/${om1.id}/pdf/download`)))
  controle('PDF brouillon de l\'OM', estPdf(await get('lecteur', `/ordres/${om1.id}/pdf/brouillon`)))

  // ── 6. Fichier MTN, erreur de dépôt, régénération ─────────────────────────
  section('Fichier MTN : en-têtes HTTP, erreur de dépôt, régénération avec le numéro courant')
  const gen = statut('génération MTN AVANCE', await generer(mission.id, 'AVANCE', 'MTN'), 200)
  controle('téléchargement en pièce jointe (Content-Disposition)', /attachment/i.test(gen.headers.get('content-disposition') || ''), gen.headers.get('content-disposition'))
  controle('Content-Type texte', /^text\/plain/.test(gen.headers.get('content-type') || ''), gen.headers.get('content-type'))
  const texte = gen.buffer.toString('latin1')
  const lignes = texte.split(CRLF)
  egal('en-tête HDR : compte SARALY, 2 lignes, 168 000', lignes[0], 'HDR,SARALY,2,168000')
  controle('fins de ligne CRLF uniquement, saut de ligne final', texte.endsWith(CRLF) && !texte.replace(/\r\n/g, '').includes('\n') && lignes.length === 4)
  egal('ligne 1 : MSISDN 229 + numéro de l\'agent', lignes[1].split(',').slice(0, 4), ['1', 'MSISDN', `229${mtnA.telephone}`, '94500'])
  egal('ligne 2 : le CAV payé 73 500', lignes[2].split(',').slice(0, 4), ['2', 'MSISDN', `229${mtnB.telephone}`, '73500'])
  controle('libellés en MAJUSCULES ASCII', [lignes[1], lignes[2]].every((l) => /^[ -~]+$/.test(l) && l.split(',')[4] === l.split(',')[4].toUpperCase()))
  let lot = (await lots(mission.id, 'AVANCE', 'MTN'))[0]
  egal('lot GENERE, séquence 1', [lot.statut, lot.sequence, lot.montant_total], ['GENERE', 1, 168000])
  await verifierInterdits([
    ['dg', 'POST', `/paiements/lots/${lot.id}/confirmer`], ['controle', 'POST', `/paiements/lots/${lot.id}/erreur`],
    ['lecteur', 'GET', `/paiements/lots/${lot.id}/download`],
  ])
  egal('versement passé GENERE', (await omParReference(ref(1))).versements.find((v) => v.type === 'AVANCE').statut, 'GENERE')

  const nouveauTel = tel('91')
  await exiger(put('gestion', `/agents/${mtnA.id}`, { telephone: nouveauTel, operateur: 'MTN' }), 'correction du numéro')
  egal('un versement GENERE garde le numéro du fichier déposé', (await omParReference(ref(1))).versements.find((v) => v.type === 'AVANCE').telephone, mtnA.telephone)
  egal('lot marqué en ERREUR', (await exiger(post('finance', `/paiements/lots/${lot.id}/erreur`), 'erreur')).json.data.statut, 'ERREUR')
  const vErreur = (await omParReference(ref(1))).versements.find((v) => v.type === 'AVANCE')
  egal('versements EN_ATTENTE avec le numéro COURANT de l\'agent', [vErreur.statut, vErreur.telephone], ['EN_ATTENTE', nouveauTel])
  refus('un lot en erreur ne se confirme pas', await post('finance', `/paiements/lots/${lot.id}/confirmer`), 'ERREUR', { contient: true })
  const regen = statut('régénération', await generer(mission.id, 'AVANCE', 'MTN'), 200)
  controle('fichier régénéré avec le nouveau numéro', regen.buffer.toString('latin1').includes(`,229${nouveauTel},94500,`))
  const lotRegen = (await lots(mission.id, 'AVANCE', 'MTN'))[0]
  egal('régénération sur place (même lot, séquence 1)', [lotRegen.id, lotRegen.sequence, lotRegen.statut], [lot.id, 1, 'GENERE'])
  egal('re-téléchargement identique au fichier régénéré', (await get('finance', `/paiements/lots/${lot.id}/download`)).buffer.equals(regen.buffer), true)
  egal('confirmation', (await exiger(post('finance', `/paiements/lots/${lot.id}/confirmer`), 'confirmation')).json.data.statut, 'CONFIRME')
  refus('un lot confirmé ne passe pas en erreur', await post('finance', `/paiements/lots/${lot.id}/erreur`), 'Un lot confirmé ne peut pas être marqué en erreur.')

  // ── 7. Lot complémentaire, idempotence ────────────────────────────────────
  section('Lot complémentaire et idempotence (un versement confirmé n\'est jamais réinclus)')
  const versMtn = tel('90')
  await exiger(put('gestion', `/agents/${moovA.id}`, { telephone: versMtn, operateur: 'MTN' }), 'changement d\'opérateur')
  egal('versement en attente rebasculé sur MTN', (await omParReference(ref(3))).versements.find((v) => v.type === 'AVANCE').operateur, 'MTN')
  const apC = await apercu(mission.id, 'AVANCE', 'MTN')
  egal('aperçu MTN : seul le nouveau venu (déjà payés exclus)', [apC.nb_lignes, apC.beneficiaires.map((b) => b.reference_semo)], [1, [ref(3)]])
  const compl = statut('génération du lot complémentaire', await generer(mission.id, 'AVANCE', 'MTN'), 200)
  controle('fichier …_AVANCE_COMPL2.txt', /_AVANCE_COMPL2\.txt$/.test(nomFichier(compl) || ''), nomFichier(compl))
  egal('contenu du lot complémentaire', compl.buffer.toString('latin1').split(CRLF).slice(0, 2).map((l) => l.split(',').slice(0, 4).join(',')),
    ['HDR,SARALY,1,94500', `1,MSISDN,229${versMtn},94500`])
  const liste = await lots(mission.id, 'AVANCE', 'MTN')
  egal('liste des lots : le plus récent d\'abord, séquences 2 puis 1', liste.map((l) => [l.sequence, l.statut]), [[2, 'GENERE'], [1, 'CONFIRME']])
  egal('l\'ancien lot confirmé n\'est pas réécrit', liste[1].montant_total, 168000)
  egal('Moov : plus rien de payable', (await apercu(mission.id, 'AVANCE', 'MOOV')).nb_lignes, 0)
  refus('génération Moov sans versement éligible refusée', await generer(mission.id, 'AVANCE', 'MOOV'), 'Aucun versement éligible (non confirmé) pour ce lot.')
  await exiger(post('finance', `/paiements/lots/${liste[0].id}/confirmer`), 'confirmation')
  egal('après confirmation : aperçu MTN vide', (await apercu(mission.id, 'AVANCE', 'MTN')).nb_lignes, 0)
  refus('aucun troisième lot (anti-double-paiement)', await generer(mission.id, 'AVANCE', 'MTN'), 'anti-double-paiement', { contient: true })
  egal('toujours 2 lots MTN AVANCE', (await lots(mission.id, 'AVANCE', 'MTN')).length, 2)
  const synthese = (await exiger(get('finance', '/paiements/statuts'), 'statuts')).json.data
  controle('synthèse des versements : aucun statut de régularisation', synthese.every((s) => !['A_RECOUVRER', 'RECOUVRE'].includes(s.statut)))
  const histo = (await exiger(get('finance', `/paiements/historique?per_page=100&date_debut=${new Date(Date.now() - 864e5).toISOString().slice(0, 10)}`), 'historique')).json
  controle('historique : les 3 avances confirmées présentes', [ref(1), ref(2), ref(3)].every((r) => histo.data.some((v) => v.reference_semo === r && v.type === 'AVANCE')), histo.meta)

  // ── 8. Reliquats : verrou propre à la tranche ─────────────────────────────
  section('Reliquats : verrou propre à la tranche, puis amendement refusé')
  egal('reliquats non payables tant que leur état n\'est pas validé', (await apercu(mission.id, 'RELIQUAT', 'MTN')).nb_lignes, 0)
  const brouillonR = (await exiger(post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'RELIQUAT' }), 'récapitulatif')).json.data
  refus('numéro de registre unique entre états', await put('dg', `/etats/${brouillonR.id}/numero`, { numero }),
    `Le numéro « ${numero} » est déjà attribué à un autre état.`)
  const etatR = await validerEtat(mission.id, 'RELIQUAT', `REC-${RUN}-R`)
  egal('état des reliquats VALIDE (96 000 + 31 500 + 40 500 = 112 500)', [etatR.statut, etatR.montant_total], ['VALIDE', 112500])
  egal('reliquats MTN payables : 3 lignes', (await apercu(mission.id, 'RELIQUAT', 'MTN')).nb_lignes, 3)
  refus('amendement refusé une fois les reliquats validés',
    await post('gestion', `/ordres/${om1.id}/amender`, { nb_jours: 2, motif: 'Réduction après validation' }), 'validé et scellé', { contient: true })
  controle('état général consolidé (PDF)', estPdf(await get('lecteur', `/missions/${mission.id}/etat/final`)))

  // ── 9. Mission sans état : circuit par OM, trop-perçu ─────────────────────
  section('Mission sans état : validation par OM, rejet, trop-perçu → RECOUVRE')
  const moovB = await creerAgent(`MOOVB${RUN}`, '67', 'MOOV')
  const moovC = await creerAgent(`MOOVC${RUN}`, '68', 'MOOV')
  const sansEtat = (await exiger(post('gestion', '/missions', {
    objet: `Reprise de données ${RUN}`, destination: 'Natitingou', zone_code: 'INTERIEUR',
    date_depart: '2026-08-03', date_retour: '2026-08-07', prise_en_charge_tiers: false,
    ordres: [
      { reference_semo: ref(10), agent_id: moovB.id, role: 'PARTICIPANT', type_prise_en_charge: 'JOURNEE_COMPLETE' },
      { reference_semo: ref(11), agent_id: moovC.id, role: 'PARTICIPANT', type_prise_en_charge: 'JOURNEE_COMPLETE' },
    ],
  }), 'mission sans état')).json.data
  const omB = await omParReference(ref(10))
  const omC = await omParReference(ref(11))
  egal('5 j journée complète : 225 000 = 157 500 + 67 500', [omB.montant_total, omB.avance, omB.reliquat], [225000, 157500, 67500])
  egal('le DAF ne valide pas un OM (om.valider)', (await post('daf', `/ordres/${omB.id}/valider`)).status, 403)
  egal('validation par OM (controle@) : VALIDE', (await exiger(post('controle', `/ordres/${omB.id}/valider`), 'validation OM')).json.data.statut, 'VALIDE')
  refus('rejet d\'OM sans motif suffisant refusé', await post('daf', `/ordres/${omC.id}/rejeter`, { motif: 'court' }), 'motif', { contient: true })
  const omRejete = (await exiger(post('daf', `/ordres/${omC.id}/rejeter`, { motif: 'Mission annulée par la direction' }), 'rejet OM')).json.data
  egal('OM rejeté par le DAF (om.rejeter), versements ANNULE', [omRejete.statut, ...omRejete.versements.map((v) => v.statut)], ['REJETE', 'ANNULE', 'ANNULE'])
  const apSE = await apercu(sansEtat.id, 'AVANCE', 'MOOV')
  egal('aperçu Moov : seul l\'OM validé', [apSE.nb_lignes, apSE.total], [1, 157500])

  const moov = statut('génération Moov (mission sans état)', await generer(sansEtat.id, 'AVANCE', 'MOOV'), 200)
  controle('Content-Type du classeur Moov', /spreadsheetml|octet-stream/.test(moov.headers.get('content-type') || ''), moov.headers.get('content-type'))
  const x = lireXlsx(moov.buffer)
  egal('classeur Moov : Sheet1, 229 + numéro en texte, montant numérique', [x.nomFeuille, x.cellules.B2?.valeur, x.cellules.B2?.type !== 'n', x.cellules.G2?.valeur, x.cellules.G2?.type, x.cellules.A3], ['Sheet1', `229${moovB.telephone}`, true, 157500, 'n', undefined])
  const lotSE = (await lots(sansEtat.id, 'AVANCE', 'MOOV'))[0]
  await exiger(post('finance', `/paiements/lots/${lotSE.id}/confirmer`), 'confirmation')

  statut('réduction à 1 jour (circuit par OM : avance confirmée engagée)', await post('gestion', `/ordres/${omB.id}/amender`, { nb_jours: 1, motif: 'Mission écourtée à une journée' }), 200)
  const omTp = await omParReference(ref(10))
  egal('total 45 000, avance engagée 157 500, reliquat −112 500', [omTp.montant_total, omTp.avance_engagee, omTp.reliquat], [45000, 157500, -112500])
  const vTp = omTp.versements.find((v) => v.type === 'RELIQUAT')
  egal('versement RELIQUAT A_RECOUVRER', [vTp.montant, vTp.statut], [-112500, 'A_RECOUVRER'])
  egal('trop-perçu jamais dans un fichier', (await apercu(sansEtat.id, 'RELIQUAT', 'MOOV')).nb_lignes, 0)
  const reg = (await exiger(get('finance', `/paiements/regularisations?statut=A_RECOUVRER&search=${ref(10)}`), 'régularisations')).json
  egal('régularisation listée : 112 500 à recouvrer, lien vers l\'OM', [reg.data[0]?.montant_a_recouvrer, reg.data[0]?.ordre_mission_id, reg.total_a_recouvrer], [112500, omB.id, 112500])
  const doc = await get('finance', `/paiements/regularisations/${vTp.id}/document`)
  controle('document de retenue : PDF en pièce jointe', estPdf(doc) && /attachment/i.test(doc.headers.get('content-disposition') || ''))
  const tpAvant = (await exiger(get('lecteur', '/rapports/trop-percus'), 'rapport trop-perçus')).json.data
  egal('le gestionnaire ne constate pas le recouvrement (paiements.confirmer)', (await post('gestion', `/paiements/regularisations/${vTp.id}/recouvrer`)).status, 403)
  egal('recouvrement constaté : RECOUVRE', (await exiger(post('finance', `/paiements/regularisations/${vTp.id}/recouvrer`), 'recouvrement')).json.data.statut, 'RECOUVRE')
  egal('OM soldé', (await omParReference(ref(10))).statut, 'SOLDE')
  refus('recouvrer deux fois refusé', await post('finance', `/paiements/regularisations/${vTp.id}/recouvrer`), 'RECOUVRE', { contient: true })
  const tpApres = (await exiger(get('lecteur', '/rapports/trop-percus'), 'rapport trop-perçus')).json.data
  egal('rapport des trop-perçus : 112 500 passés de « à recouvrer » à « recouvrés » (montants absolus)',
    [tpAvant.a_recouvrer.montant - tpApres.a_recouvrer.montant, tpApres.recouvre.montant - tpAvant.recouvre.montant, tpApres.total.montant - tpAvant.total.montant],
    [112500, 112500, 0])

  // ── 10. Rapports, export, audit, pagination, notifications ────────────────
  section('Rapports, export Excel, audit, pagination, déconnexion')
  const exp = (await exiger(get('finance', '/rapports/export?dimension=operateur&format=xlsx'), 'export')).json.data
  controle('export Excel produit', /\.xlsx$/.test(exp.fichier || ''), exp)
  const telech = await get('finance', `/rapports/export/download?fichier=${encodeURIComponent(exp.fichier)}`)
  controle('export Excel téléchargé : archive xlsx valide', telech.status === 200 && telech.buffer.subarray(0, 2).toString('latin1') === 'PK' && Boolean(lireXlsx(telech.buffer).nomFeuille))
  refus('export : dimension inconnue refusée', await get('finance', '/rapports/export?dimension=planete'), 'dimension', { contient: true })
  const audit = (await exiger(get('controle', '/audit?action=CONFIRM_LOT'), 'audit')).json
  controle('journal d\'audit : confirmations tracées', (audit.data || []).length > 0 && audit.data.every((l) => l.action === 'CONFIRM_LOT'), audit.meta)
  statut('détail d\'une entrée d\'audit', await get('controle', `/audit/${audit.data[0].id}`), 200)
  const hist = (await exiger(get('lecteur', `/ordres/${om1.id}/historique`), 'historique OM')).json.data
  controle('historique de l\'OM : import tracé', hist.some((h) => h.action === 'IMPORT_OM'), hist.map((h) => h.action))
  egal('pagination bornée à 100', (await exiger(get('lecteur', '/ordres?per_page=1000'), 'pagination')).json.meta.per_page, 100)
  egal('pagination bornée à 5', (await exiger(get('lecteur', '/missions?per_page=1'), 'pagination')).json.meta.per_page, 5)
  const notifs = (await exiger(get('finance', '/notifications'), 'notifications')).json
  controle('notifications de l\'agent financier', Array.isArray(notifs.data) && typeof notifs.meta.non_lues === 'number')
  statut('tout marquer lu', await post('finance', '/notifications/lues'), 200)
  egal('plus aucune notification non lue', (await exiger(get('finance', '/notifications'), 'notifications')).json.meta.non_lues, 0)
  statut('déconnexion du lecteur', await post('lecteur', '/auth/logout'), 200)
  egal('jeton révoqué : 401', (await get('lecteur', '/auth/me')).status, 401)

  terminer('Recette de l\'API')
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
