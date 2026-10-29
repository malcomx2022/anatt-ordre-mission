#!/usr/bin/env node
/*
 * Rejeu de docs/SCENARIO-DEMO.md sur l'API réellement démarrée (Node 22, aucune dépendance).
 *
 * Base NEUVE obligatoire (références SEMO uniques) :
 *   cd backend && php artisan migrate:fresh --seed && QUEUE_CONNECTION=sync php artisan serve
 *   API_URL=http://127.0.0.1:8000 node scripts/recette/rejeu-scenario.cjs
 *
 * Chaque attendu du scénario est une assertion : le script s'arrête en code 1 au premier écart
 * et affiche, s'il va au bout, « N contrôles conformes ». Voir scripts/recette/README.md.
 */
const {
  section, controle, egal, statut, refus, echec, get, post, put, exiger, connecter, attendreApi,
  nomFichier, estPdf, lireXlsx, terminer, API,
} = require('./lib.cjs')

const OBJET = 'Dossiers de passation de marchés'
const LIBELLE = 'DOSSIERS DE PASSATION DE MARCHES'
const SLUG = 'dossiers-de-passation-de-marches'
const CRLF = '\r\n'

const AGENTS = [
  { ref: '0055000167', nom: 'SOUMANOU DJARA', prenoms: 'Sabirou', telephone: '0197984877', operateur: 'MTN', transport: 0 },
  { ref: '0055000161', nom: 'ATCHA', prenoms: 'Gabriel', telephone: '0196695824', operateur: 'MTN', transport: 10000 },
  { ref: '0055000165', nom: 'MONHOUNVEDO', prenoms: 'Rousselot', telephone: '0167267455', operateur: 'MOOV', transport: 0 },
  { ref: '0055000162', nom: 'ABLESSOU', prenoms: 'Sacré cœur', telephone: '0167752011', operateur: 'MOOV', transport: 0 },
]
const ATTENDUS = {
  '0055000167': { total: 285000, avance: 199500, reliquat: 85500 },
  '0055000161': { total: 295000, avance: 206500, reliquat: 88500 },
  '0055000165': { total: 285000, avance: 199500, reliquat: 85500 },
  '0055000162': { total: 285000, avance: 199500, reliquat: 85500 },
}

/** OM par référence SEMO, avec ses versements et ses amendements (fiche détail). */
async function omParReference(qui, ref) {
  const liste = await exiger(get(qui, `/ordres?search=${ref}`), `recherche de l'OM ${ref}`)
  const om = liste.json.data.find((o) => o.reference_semo === ref)
  if (!om) echec(`OM ${ref} introuvable`)
  return (await exiger(get(qui, `/ordres/${om.id}`), `fiche de l'OM ${ref}`)).json.data
}

/** Dernier lot (séquence la plus récente) d'une mission × tranche × opérateur. */
async function dernierLot(missionId, tranche, operateur) {
  const rep = await exiger(get('finance', `/paiements/lots?mission_id=${missionId}&tranche=${tranche}&operateur=${operateur}`), 'liste des lots')
  return rep.json.data[0]
}

/** Circuit complet d'un état : contrôle → visa DAF → numéro + validation DG. */
async function circuit(etatId, numero, libelle) {
  egal(`${libelle} : contrôlé par controle@`, (await exiger(post('controle', `/etats/${etatId}/controler`), 'contrôle')).json.data.statut, 'CONTROLE')
  egal(`${libelle} : visé par daf@`, (await exiger(post('daf', `/etats/${etatId}/viser`), 'visa')).json.data.statut, 'VISA_DAF')
  await exiger(put('dg', `/etats/${etatId}/numero`, { numero }), 'numéro')
  egal(`${libelle} : validé par dg@ (n° ${numero})`, (await exiger(post('dg', `/etats/${etatId}/approuver`), 'validation')).json.data.statut, 'VALIDE')
}

async function main() {
  console.log(`Rejeu du scénario de démonstration sur ${API}`)
  await attendreApi()

  section('Connexions (comptes de démonstration)')
  for (const c of ['gestion', 'controle', 'daf', 'dg', 'finance', 'superadmin']) {
    const d = await connecter(c)
    controle(`connexion ${d.user.email} (${d.user.role})`, Boolean(d.token))
  }

  const deja = await get('gestion', `/ordres?search=${AGENTS[0].ref}`)
  if (deja.json?.data?.length) {
    echec('base neuve', `la référence SEMO ${AGENTS[0].ref} existe déjà : relancer php artisan migrate:fresh --seed`)
  }

  // ── ÉTAPE A ─────────────────────────────────────────────────────────────
  section('Étape A — créer les employés (gestion@)')
  refus('agent sans indice ni salaire brut refusé',
    await post('gestion', '/agents', { nom: 'TEST', prenoms: 'Sans indice', matricule: 'DEMO-0000', telephone: '0197000099', operateur: 'MTN' }),
    "L'indice ou le salaire brut est requis pour déterminer la catégorie.")
  refus('numéro MTN déclaré MOOV refusé',
    await post('gestion', '/agents', { nom: 'TEST', prenoms: 'Incohérent', matricule: 'DEMO-0001', indice: 850, telephone: '0197000099', operateur: 'MOOV' }),
    "Le numéro correspond à l'opérateur MTN, pas à MOOV.")

  const agents = {}
  for (const a of AGENTS) {
    const rep = statut(`création de ${a.nom} ${a.prenoms}`, await post('gestion', '/agents', {
      nom: a.nom, prenoms: a.prenoms, matricule: `DEMO-${a.ref.slice(-4)}`, indice: 850,
      telephone: a.telephone, operateur: a.operateur,
    }), 201)
    egal(`  message de création`, rep.json.message, 'Agent créé avec succès.')
    egal(`  catégorie déduite de l'indice 850`, rep.json.data.categorie, 'INDICE_SUP_800')
    agents[a.ref] = rep.json.data
  }

  // ── ÉTAPE B ─────────────────────────────────────────────────────────────
  section('Étape B — retranscrire l\'OM SEMO (gestion@)')
  const ordre = (a) => ({
    reference_semo: a.ref, agent_id: agents[a.ref].id, role: 'PARTICIPANT', type_prise_en_charge: null,
    lignes: [{ type: 'UN_REPAS', nb_jours: 1 }, { type: 'JOURNEE_COMPLETE', nb_jours: 6 }],
    ...(a.transport ? { montant_transport: a.transport } : {}),
  })
  const corpsMission = {
    objet: OBJET, destination: 'BOHICON', date_depart: '2026-05-18', date_retour: '2026-05-23',
    prise_en_charge_tiers: false, ordres: AGENTS.map(ordre),
  }
  refus('même agent deux fois dans la mission refusé',
    await post('gestion', '/missions', { ...corpsMission, ordres: [ordre(AGENTS[0]), { ...ordre(AGENTS[1]), agent_id: agents[AGENTS[0].ref].id }] }),
    'Le même agent figure deux fois dans la mission : un OM par bénéficiaire.')

  const repMission = statut('mission retranscrite', await post('gestion', '/missions', corpsMission), 201)
  const mission = repMission.json.data
  egal('zone déduite de la destination BOHICON', mission.zone_code, 'INTERIEUR')
  egal('4 OM créés', mission.ordres_mission.length, 4)

  refus('référence SEMO en double refusée (anti-doublon)',
    await post('gestion', '/missions', { ...corpsMission, ordres: [ordre(AGENTS[0])] }),
    'La référence SEMO 0055000167 existe déjà (doublon interdit).')

  const oms = {}
  for (const a of AGENTS) {
    const om = await omParReference('gestion', a.ref)
    oms[a.ref] = om
    const att = ATTENDUS[a.ref]
    egal(`${a.ref} ${a.nom} : total / avance / reliquat / transport`,
      [om.montant_total, om.avance, om.reliquat, om.montant_transport ?? 0], [att.total, att.avance, att.reliquat, a.transport])
    egal(`${a.ref} : statut IMPORTE, versements AVANCE + RELIQUAT en attente sur ${a.operateur}`,
      [om.statut, ...om.versements.map((v) => `${v.type}:${v.statut}:${v.operateur}`).sort()],
      ['IMPORTE', `AVANCE:EN_ATTENTE:${a.operateur}`, `RELIQUAT:EN_ATTENTE:${a.operateur}`])
  }

  // ── ÉTAPE C ─────────────────────────────────────────────────────────────
  section('Étape C — état des AVANCES (gestion@ → controle@ → daf@ → dg@)')
  const apercuAvant = (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${mission.id}&tranche=AVANCE&operateur=MTN`), 'aperçu')).json.data
  egal('avant validation : aucun bénéficiaire payable', apercuAvant.nb_lignes, 0)

  const recap = statut('récapitulatif des avances établi', await post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'AVANCE' }), 200)
  const etatAvance = recap.json.data
  egal('état des avances : BROUILLON, 4 bénéficiaires, 805 000', [etatAvance.statut, etatAvance.nb_beneficiaires, etatAvance.montant_total], ['BROUILLON', 4, 805000])
  egal('part de l\'avance de la mission : 70 %', etatAvance.part_avance, 70)
  controle('PDF brouillon de l\'état téléchargeable', estPdf(await get('gestion', `/missions/${mission.id}/etat/brouillon?tranche=AVANCE`)))
  refus('état des reliquats refusé tant que les avances ne sont pas validées',
    await post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'RELIQUAT' }),
    "L'état des avances doit être validé par le circuit (contrôle, visa DAF, Directeur Général) avant d'établir l'état des reliquats.")

  const notifControle = (await exiger(get('controle', '/notifications'), 'notifications')).json
  controle('controle@ est notifié après le récapitulatif', notifControle.meta.non_lues > 0, notifControle.meta)

  egal('contrôle (controle@)', (await exiger(post('controle', `/etats/${etatAvance.id}/controler`), 'contrôle')).json.data.statut, 'CONTROLE')
  egal('visa DAF (daf@)', (await exiger(post('daf', `/etats/${etatAvance.id}/viser`), 'visa')).json.data.statut, 'VISA_DAF')
  refus('validation finale sans numéro refusée',
    await post('dg', `/etats/${etatAvance.id}/approuver`), "Renseignez le numéro de l'état avant la validation finale.")
  egal('numéro de registre saisi (dg@)', (await exiger(put('dg', `/etats/${etatAvance.id}/numero`, { numero: 'EP-2026-0518-A' }), 'numéro')).json.data.numero, 'EP-2026-0518-A')
  const valide = statut('validation finale (dg@)', await post('dg', `/etats/${etatAvance.id}/approuver`), 200)
  egal('état des avances VALIDE, paiement ouvert', [valide.json.data.statut, valide.json.data.paiement_ouvert], ['VALIDE', true])
  for (const a of AGENTS) {
    const om = await omParReference('gestion', a.ref)
    egal(`${a.ref} : OM VALIDE, avance engagée ${ATTENDUS[a.ref].avance}`, [om.statut, om.avance_engagee], ['VALIDE', ATTENDUS[a.ref].avance])
  }
  controle('PDF de l\'état validé téléchargeable', estPdf(await get('gestion', `/missions/${mission.id}/etat/telecharger?tranche=AVANCE`)))
  const notifFinance = (await exiger(get('finance', '/notifications'), 'notifications')).json
  controle('finance@ est notifié (fichiers générables)', notifFinance.meta.non_lues > 0, notifFinance.meta)

  // ── ÉTAPE D ─────────────────────────────────────────────────────────────
  section('Étape D — fichier MTN (finance@)')
  const apercuMtn = (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${mission.id}&tranche=AVANCE&operateur=MTN`), 'aperçu')).json.data
  egal('aperçu MTN AVANCE : 2 bénéficiaires, 406 000', [apercuMtn.nb_lignes, apercuMtn.total], [2, 406000])

  const fichierMtn = statut('génération du fichier MTN', await post('finance', '/paiements/lots/generer', { mission_id: mission.id, tranche: 'AVANCE', operateur: 'MTN' }), 200)
  egal('nom du fichier MTN', nomFichier(fichierMtn), `${SLUG}_AVANCE.txt`)
  const attenduMtn = [
    'HDR,SARALY,2,406000',
    `1,MSISDN,2290197984877,199500,SOUMANOU DJARA SABIROU ${LIBELLE}`,
    `2,MSISDN,2290196695824,206500,ATCHA GABRIEL ${LIBELLE}`,
  ].join(CRLF) + CRLF
  egal('contenu exact du fichier MTN (CRLF, saut de ligne final)', fichierMtn.buffer.toString('latin1'), attenduMtn)
  controle('fichier MTN en ASCII', [...fichierMtn.buffer].every((o) => o < 128))

  const lotMtn = await dernierLot(mission.id, 'AVANCE', 'MTN')
  egal('lot MTN : GENERE, séquence 1, 2 lignes, 406 000', [lotMtn.statut, lotMtn.sequence, lotMtn.nb_lignes, lotMtn.montant_total], ['GENERE', 1, 2, 406000])
  const reTelechargement = await get('finance', `/paiements/lots/${lotMtn.id}/download`)
  egal('re-téléchargement du lot MTN identique', reTelechargement.buffer.toString('latin1'), attenduMtn)

  // ── ÉTAPE E ─────────────────────────────────────────────────────────────
  section('Étape E — fichier Moov (finance@)')
  const apercuMoov = (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${mission.id}&tranche=AVANCE&operateur=MOOV`), 'aperçu')).json.data
  egal('aperçu Moov AVANCE : 2 bénéficiaires, 399 000', [apercuMoov.nb_lignes, apercuMoov.total], [2, 399000])

  const fichierMoov = statut('génération du fichier Moov', await post('finance', '/paiements/lots/generer', { mission_id: mission.id, tranche: 'AVANCE', operateur: 'MOOV' }), 200)
  egal('nom du fichier Moov', nomFichier(fichierMoov), `${SLUG}_AVANCE.xlsx`)
  const xlsx = lireXlsx(fichierMoov.buffer)
  egal('feuille Sheet1', xlsx.nomFeuille, 'Sheet1')
  const cel = (ref) => xlsx.cellules[ref]?.valeur ?? null
  egal('en-tête à 9 colonnes', ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'].map((c) => cel(`${c}1`)),
    ['Receiver Identifier Type', 'IdentifierValue', 'Validation KYC', 'Validation KYC value', 'PayerAccountName', 'AccountName', 'Amount', 'Comment', 'VoucherSecretCode'])
  const lignesMoov = [2, 3].map((r) => ({
    a: cel(`A${r}`), b: cel(`B${r}`), bTexte: xlsx.cellules[`B${r}`]?.type !== 'n', vides: ['C', 'D', 'E', 'F', 'I'].every((c) => !cel(`${c}${r}`)),
    g: cel(`G${r}`), gNum: xlsx.cellules[`G${r}`]?.type === 'n', h: cel(`H${r}`),
  }))
  egal('lignes Moov (MSISDN, 229 + numéro en texte, C-F et I vides, montant numérique, libellé)', lignesMoov, [
    { a: 'MSISDN', b: '2290167267455', bTexte: true, vides: true, g: 199500, gNum: true, h: `MONHOUNVEDO ROUSSELOT ${LIBELLE}` },
    { a: 'MSISDN', b: '2290167752011', bTexte: true, vides: true, g: 199500, gNum: true, h: `ABLESSOU SACRE COEUR ${LIBELLE}` },
  ])
  controle('aucune ligne au-delà des 2 bénéficiaires', cel('A4') === null)
  const lotMoov = await dernierLot(mission.id, 'AVANCE', 'MOOV')
  egal('lot Moov : GENERE, 2 lignes, 399 000', [lotMoov.statut, lotMoov.nb_lignes, lotMoov.montant_total], ['GENERE', 2, 399000])

  // ── ÉTAPE F ─────────────────────────────────────────────────────────────
  section('Étape F — confirmer (finance@)')
  for (const lot of [lotMtn, lotMoov]) {
    egal(`lot ${lot.operateur} confirmé`, (await exiger(post('finance', `/paiements/lots/${lot.id}/confirmer`), 'confirmation')).json.data.statut, 'CONFIRME')
  }
  const om167 = await omParReference('finance', '0055000167')
  const avance167 = om167.versements.find((v) => v.type === 'AVANCE')
  egal('versement AVANCE de SOUMANOU confirmé et horodaté', [avance167.statut, Boolean(avance167.date_paiement)], ['CONFIRME', true])
  egal('après confirmation : plus rien de payable en MTN AVANCE',
    (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${mission.id}&tranche=AVANCE&operateur=MTN`), 'aperçu')).json.data.nb_lignes, 0)
  refus('nouvelle génération MTN AVANCE refusée (anti-double-paiement)',
    await post('finance', '/paiements/lots/generer', { mission_id: mission.id, tranche: 'AVANCE', operateur: 'MTN' }),
    'Ce lot est déjà confirmé et aucun nouveau versement n\'est payable : régénération interdite (anti-double-paiement).')
  refus('confirmer de nouveau un lot confirmé refusé',
    await post('finance', `/paiements/lots/${lotMtn.id}/confirmer`),
    'Ce lot est au statut « CONFIRME » : seul un lot généré peut être confirmé (régénérez-le si nécessaire).')

  // ── Bonus — reliquats ───────────────────────────────────────────────────
  section('Bonus — état des RELIQUATS, séparation des tâches, fichiers reliquat')
  const etatReliquat = statut('récapitulatif des reliquats établi', await post('gestion', `/missions/${mission.id}/etat/recapituler`, { tranche: 'RELIQUAT' }), 200).json.data
  egal('état des reliquats : BROUILLON, 4 bénéficiaires, 345 000', [etatReliquat.statut, etatReliquat.nb_beneficiaires, etatReliquat.montant_total], ['BROUILLON', 4, 345000])
  // Séparation des tâches : même le super administrateur ne peut enchaîner deux signatures.
  egal('contrôle des reliquats par superadmin@', (await exiger(post('superadmin', `/etats/${etatReliquat.id}/controler`), 'contrôle')).json.data.statut, 'CONTROLE')
  refus('séparation des tâches : qui a contrôlé ne vise pas',
    await post('superadmin', `/etats/${etatReliquat.id}/viser`), 'Vous avez contrôlé cet état : le visa DAF revient à une autre personne.')
  egal('visa DAF des reliquats (daf@)', (await exiger(post('daf', `/etats/${etatReliquat.id}/viser`), 'visa')).json.data.statut, 'VISA_DAF')
  await exiger(put('dg', `/etats/${etatReliquat.id}/numero`, { numero: 'EP-2026-0518-R' }), 'numéro')
  refus('séparation des tâches : qui a contrôlé ne valide pas',
    await post('superadmin', `/etats/${etatReliquat.id}/approuver`),
    'Vous êtes déjà intervenu sur cet état (contrôle ou visa DAF) : la validation finale revient à une autre personne.')
  egal('validation des reliquats (dg@)', (await exiger(post('dg', `/etats/${etatReliquat.id}/approuver`), 'validation')).json.data.statut, 'VALIDE')

  for (const [op, total] of [['MTN', 174000], ['MOOV', 171000]]) {
    const ap = (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${mission.id}&tranche=RELIQUAT&operateur=${op}`), 'aperçu')).json.data
    egal(`aperçu ${op} RELIQUAT : 2 bénéficiaires, ${total}`, [ap.nb_lignes, ap.total], [2, total])
    const f = statut(`génération ${op} RELIQUAT`, await post('finance', '/paiements/lots/generer', { mission_id: mission.id, tranche: 'RELIQUAT', operateur: op }), 200)
    egal(`nom du fichier ${op} RELIQUAT`, nomFichier(f), `${SLUG}_RELIQUAT.${op === 'MTN' ? 'txt' : 'xlsx'}`)
    if (op === 'MTN') {
      egal('contenu exact du fichier MTN RELIQUAT', f.buffer.toString('latin1'), [
        'HDR,SARALY,2,174000',
        `1,MSISDN,2290197984877,85500,SOUMANOU DJARA SABIROU ${LIBELLE}`,
        `2,MSISDN,2290196695824,88500,ATCHA GABRIEL ${LIBELLE}`,
      ].join(CRLF) + CRLF)
    }
    const lot = await dernierLot(mission.id, 'RELIQUAT', op)
    egal(`lot ${op} RELIQUAT confirmé`, (await exiger(post('finance', `/paiements/lots/${lot.id}/confirmer`), 'confirmation')).json.data.statut, 'CONFIRME')
  }
  for (const a of AGENTS) egal(`${a.ref} : OM SOLDE`, (await omParReference('gestion', a.ref)).statut, 'SOLDE')
  controle('état général consolidé (avances + reliquats) téléchargeable', estPdf(await get('gestion', `/missions/${mission.id}/etat/final`)))

  // ── §7 — avenant ────────────────────────────────────────────────────────
  section('§7 — avenant : rallonge à 8 j puis réduction à 3 j (trop-perçu)')
  // Bénéficiaire d'illustration : 6 jours « journée complète » (270 000 = 189 000 + 81 000).
  const soumanou = agents['0055000167']
  const missionAvenant = statut('mission d\'illustration retranscrite (6 j journée complète)', await post('gestion', '/missions', {
    objet: 'Avenant — mission d\'illustration', destination: 'BOHICON', date_depart: '2026-06-01', date_retour: '2026-06-06',
    prise_en_charge_tiers: false,
    ordres: [{ reference_semo: '0055000170', agent_id: soumanou.id, role: 'PARTICIPANT', type_prise_en_charge: 'JOURNEE_COMPLETE' }],
  }), 201).json.data
  let om = await omParReference('gestion', '0055000170')
  egal('point de départ : 6 j, 270 000 / 189 000 / 81 000', [om.nb_jours, om.montant_total, om.avance, om.reliquat], [6, 270000, 189000, 81000])

  const etatIllu = (await exiger(post('gestion', `/missions/${missionAvenant.id}/etat/recapituler`, { tranche: 'AVANCE' }), 'récapitulatif')).json.data
  await circuit(etatIllu.id, 'EP-2026-0601-A', 'état des avances (illustration)')
  await exiger(post('finance', '/paiements/lots/generer', { mission_id: missionAvenant.id, tranche: 'AVANCE', operateur: 'MTN' }), 'génération')
  const lotIllu = await dernierLot(missionAvenant.id, 'AVANCE', 'MTN')
  egal('avance 189 000 dans le fichier MTN', lotIllu.montant_total, 189000)

  const motifRallonge = 'Mission prolongée de deux jours (avenant n° 1)'
  refus('amendement refusé tant que le lot attend son dépôt',
    await post('gestion', `/ordres/${om.id}/amender`, { nb_jours: 8, motif: motifRallonge }),
    'Un fichier de paiement contenant cet ordre de mission est en attente de dépôt : confirmez le lot ou passez-le en erreur avant d\'amender.')
  await exiger(post('finance', `/paiements/lots/${lotIllu.id}/confirmer`), 'confirmation')
  refus('motif d\'amendement trop court refusé', await post('gestion', `/ordres/${om.id}/amender`, { nb_jours: 8, motif: 'court' }), 'Le champ motif doit contenir au moins 10 caractères.', { contient: true })

  // CAS A — rallonge
  statut('CAS A : rallonge à 8 jours', await post('gestion', `/ordres/${om.id}/amender`, { nb_jours: 8, motif: motifRallonge }), 200)
  om = await omParReference('gestion', '0055000170')
  egal('total 360 000, avance engagée 189 000, reliquat 171 000', [om.montant_total, om.avance_engagee, om.reliquat], [360000, 189000, 171000])
  egal('versement RELIQUAT 171 000 en attente', ['montant', 'statut'].map((k) => om.versements.find((v) => v.type === 'RELIQUAT')[k]), [171000, 'EN_ATTENTE'])
  egal('journal : 6 j → 8 j, delta +90 000', [om.amendements[0].nb_jours_avant, om.amendements[0].nb_jours_apres, om.amendements[0].delta_montant], [6, 8, 90000])
  const pdfOm = await exiger(get('gestion', `/ordres/${om.id}/pdf`), 'PDF de l\'OM')
  controle('PDF de l\'OM amendé régénéré (clé de cache -AMD1)', /AMD1/.test(pdfOm.json.data.fichier), pdfOm.json.data)
  controle('PDF de l\'OM amendé téléchargeable', estPdf(await get('gestion', `/ordres/${om.id}/pdf/download`)))

  // CAS B — réduction forte → trop-perçu
  statut('CAS B : réduction à 3 jours', await post('gestion', `/ordres/${om.id}/amender`, { nb_jours: 3, motif: 'Mission écourtée à trois jours (avenant n° 2)' }), 200)
  om = await omParReference('gestion', '0055000170')
  egal('total 135 000, reliquat −54 000 (trop-perçu)', [om.montant_total, om.avance_engagee, om.reliquat], [135000, 189000, -54000])
  egal('versement RELIQUAT −54 000 A_RECOUVRER', ['montant', 'statut'].map((k) => om.versements.find((v) => v.type === 'RELIQUAT')[k]), [-54000, 'A_RECOUVRER'])
  egal('trop-perçu hors du périmètre payable',
    (await exiger(get('finance', `/paiements/lots/previsualiser?mission_id=${missionAvenant.id}&tranche=RELIQUAT&operateur=MTN`), 'aperçu')).json.data.nb_lignes, 0)

  const regul = (await exiger(get('finance', `/paiements/regularisations?mission_id=${missionAvenant.id}`), 'régularisations')).json
  egal('Régularisations : à recouvrer 54 000', [regul.data.length, regul.data[0]?.montant_a_recouvrer, regul.data[0]?.statut, regul.total_a_recouvrer], [1, 54000, 'A_RECOUVRER', 54000])
  const versementTp = regul.data[0].versement_id
  controle('document de retenue sur salaire (PDF)', estPdf(await get('finance', `/paiements/regularisations/${versementTp}/document`)))

  const etatTp = statut('état des reliquats de la mission d\'illustration', await post('gestion', `/missions/${missionAvenant.id}/etat/recapituler`, { tranche: 'RELIQUAT' }), 200).json.data
  egal('état des reliquats arrêté au montant payable : 0', etatTp.montant_total, 0)

  egal('« Marquer recouvré » : RECOUVRE', (await exiger(post('finance', `/paiements/regularisations/${versementTp}/recouvrer`), 'recouvrement')).json.data.statut, 'RECOUVRE')
  egal('OM soldé (avance payée, trop-perçu recouvré)', (await omParReference('gestion', '0055000170')).statut, 'SOLDE')
  refus('nouvel amendement refusé après recouvrement (avenant formel)',
    await post('gestion', `/ordres/${om.id}/amender`, { nb_jours: 5, motif: 'Nouvelle modification de la durée' }),
    'Le trop-perçu de cet ordre de mission a déjà été recouvré (retenue appliquée) : un nouvel amendement relève d\'un avenant formel (hors périmètre).')

  terminer('Rejeu du scénario de démonstration')
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
