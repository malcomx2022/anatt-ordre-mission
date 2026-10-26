# -*- coding: utf-8 -*-
"""
Annote les maquettes HTML d'origine (docs/00_galerie_index.html … 41_page_maintenance.html).

Usage :
    python docs/annoter_maquettes.py

Les maquettes datent d'août 2026, d'avant la retranscription SEMO et le circuit par état de
paiement. Elles ne sont pas redessinées : chacune reçoit un encart d'état (écran réel, écarts
connus), et la galerie un tableau de correspondance avec les écrans de l'application.

Le script est idempotent : il remplace les blocs balisés « annotation-maquette » à chaque passage.
Source de vérité des écrans : frontend/src/router/index.ts et components/shared/navigation.ts.
Confrontation au code : 26/10/2026.
"""
import html
import os
import re

ICI = os.path.dirname(os.path.abspath(__file__))
DATE = "26/10/2026"

CONFORME = "conforme"
ECARTS = "ecarts"
OBSOLETE = "obsolete"

STATUTS = {
    CONFORME: ("Fidèle dans l'esprit", "#1D9E4F", "#EDFAF2"),
    ECARTS: ("Écarts avec l'écran réel", "#8B6914", "#FBF3E0"),
    OBSOLETE: ("Obsolète : fonction absente de l'application", "#CE1126", "#FDECEE"),
}

# (fichier, statut, écran réel « route — vue », écarts)
MAQUETTES = [
    ("01_login.html", CONFORME, "/login — auth/LoginView.vue",
     "Message d'échec neutre « Identifiants incorrects. » ; verrou de changement de mot de passe (423) après création ou réinitialisation."),
    ("02_mot_de_passe_oublie.html", CONFORME, "/mot-de-passe-oublie — auth/ForgotPasswordView.vue", ""),
    ("03_reinitialisation_mdp.html", CONFORME, "/reinitialiser/:token — auth/ResetPasswordView.vue", ""),
    ("04_dashboard.html", ECARTS, "/ — dashboard/DashboardView.vue",
     "Indicateurs réels : OM en cours, OM validés, paiements du mois ; menu réel (États de paiement, Générer MTN / Moov, Régularisations…)."),
    ("05_personnel_liste.html", ECARTS, "/agents — agents/AgentListView.vue",
     "Un agent n'est jamais supprimé : il est désactivé. Cartes de comptage servies par GET /api/agents/stats."),
    ("06_formulaire_personnel.html", ECARTS, "/agents/nouveau — agents/AgentCreateView.vue",
     "Pas de brouillon. Catégorie d'indice déduite de l'indice ou du salaire ; téléphone national à 10 chiffres et opérateur cohérents."),
    ("07_detail_personnel.html", ECARTS, "/agents/:id — agents/AgentDetailView.vue",
     "Fiche et historique des ordres de mission ; pas d'onglet « Paiements » dédié."),
    ("08_modification_personnel.html", CONFORME, "/agents/:id/modifier — agents/AgentEditView.vue",
     "La catégorie est recalculée dès que l'indice ou le salaire change."),
    ("09_import_personnel.html", ECARTS, "/agents/import — agents/AgentImportView.vue",
     "Import « tout ou rien » : une seule ligne en erreur annule l'import ; on ne peut pas ignorer les erreurs."),
    ("10_rapport_import.html", ECARTS, "/agents/import — agents/AgentImportView.vue",
     "Pas d'écran séparé : le rapport d'erreurs ligne à ligne s'affiche sur l'écran d'import."),
    ("11_liste_ordres_mission.html", ECARTS, "/ordres — missions/MissionListView.vue",
     "1 OM = 1 bénéficiaire, identifié par sa référence SEMO ; aucun OM ne se supprime. Filtres statut, zone, opérateur du versement."),
    ("12_om_creation_etape1.html", ECARTS, "/ordres/nouveau — missions/MissionCreateView.vue",
     "L'OM n'est pas créé : il est RETRANSCRIT depuis SEMO (menu « Retranscrire »). Pas de brouillon ; zone déduite de la destination."),
    ("13_om_creation_etape2.html", ECARTS, "/ordres/nouveau — missions/MissionCreateView.vue",
     "Chaque bénéficiaire (participant ou CAV) a son propre OM et sa référence SEMO ; un agent ne figure qu'une fois par mission."),
    ("14_om_creation_etape3.html", ECARTS, "/ordres/nouveau — missions/MissionCreateView.vue",
     "Pas de « Soumettre pour validation » ni de brouillon : l'enregistrement crée la mission et ses OM au statut IMPORTE. La validation se fait sur l'état de paiement."),
    ("15_detail_om.html", ECARTS, "/ordres/:id — missions/MissionDetailView.vue",
     "Un seul bénéficiaire par OM ; pas de « Dupliquer ». Liens « Historique » et « PDF de l'OM », bouton « Amender » (avenant), versements AVANCE / RELIQUAT."),
    ("16_modification_om.html", OBSOLETE, "/ordres/:id/modifier — redirige vers la fiche",
     "Un OM ne se modifie pas : seul l'amendement (avenant, motif obligatoire) ajuste ses jours facturés."),
    ("17_validation_om.html", OBSOLETE, "/missions/:missionId/etat — etats/EtatDetailView.vue",
     "La validation porte sur l'état de paiement de la mission, par tranche : contrôle, visa DAF, validation DG. Pas de « Mettre en attente »."),
    ("18_liste_om_attente.html", OBSOLETE, "/etats — États de paiement (file de travail)",
     "Plus de file d'OM à valider : chaque signataire retrouve les états qui l'attendent dans « États de paiement »."),
    ("19_generation_fichiers.html", ECARTS, "/paiements/mtn et /paiements/moov — PaiementMtnView.vue / PaiementMoovView.vue (composant PaiementGeneration.vue)",
     "Un fichier = 1 mission × 1 tranche × 1 opérateur ; aperçu automatique, pas de sélection manuelle ni d'« Exclure doublons » (un versement confirmé n'est jamais réinclus). Lot complémentaire après confirmation."),
    ("20_lots_paiement.html", CONFORME, "/paiements — paiements/PaiementListView.vue",
     "Statuts GENERE / CONFIRME / ERREUR ; badge « complémentaire n° N »."),
    ("21_detail_lot.html", ECARTS, "/paiements/mtn et /paiements/moov — PaiementGeneration.vue",
     "Pas d'écran de détail de lot : l'aperçu des bénéficiaires et le téléchargement se trouvent sur l'écran de génération et dans la liste des lots."),
    ("22_reconciliation.html", OBSOLETE, "/paiements/reconciliation — écran d'information",
     "Aucun réimport de fichier opérateur ni rapprochement automatique (règle d'or) : la confirmation d'un lot est manuelle."),
    ("23_consultation_baremes.html", ECARTS, "/taux — taux/TauxListView.vue",
     "Barème officiel catégorie d'indice × zone (INTERIEUR, EXT_ZONE_I, II = III) × type de prise en charge."),
    ("24_modification_baremes.html", ECARTS, "/taux/configurer — taux/TauxEditView.vue",
     "Seul le taux journalier se modifie ; les zones II et III restent synchronisées."),
    ("25_rapports.html", CONFORME, "/rapports — rapports/RapportDashboardView.vue", ""),
    ("26_rapport_mensuel.html", CONFORME, "/rapports/mensuel — rapports/RapportMensuelView.vue",
     "Alimenté par les versements confirmés (date de paiement)."),
    ("27_rapport_personnel.html", CONFORME, "/rapports/agents — rapports/RapportAgentView.vue", ""),
    ("28_export_rapports.html", ECARTS, "/rapports/export — rapports/RapportExportView.vue",
     "Export synchrone Excel ou PDF par dimension (agent, opérateur, zone, imputation) et période."),
    ("29_journal_audit.html", CONFORME, "/audit — audit/AuditListView.vue",
     "Filtres par action, entité et dates."),
    ("30_detail_action_audit.html", CONFORME, "/audit/:id — audit/AuditDetailView.vue", ""),
    ("31_gestion_utilisateurs.html", CONFORME, "/admin/utilisateurs — admin/UserManagementView.vue",
     "Modification du rôle en modale (sessions révoquées) ; permissions par utilisateur."),
    ("32_formulaire_utilisateur.html", ECARTS, "/admin/utilisateurs/nouveau — admin/UserCreateView.vue",
     "Pas de brouillon : mot de passe temporaire, changement imposé à la première connexion."),
    ("33_gestion_roles.html", ECARTS, "/admin/roles — admin/RolesView.vue (« Matrice des permissions »)",
     "Écriture réservée au SUPER_ADMIN ; profils système non renommables ; séparation ordonnateur / payeur contrôlée."),
    ("34_parametres.html", ECARTS, "/admin/parametres — admin/SettingsView.vue",
     "L'interrupteur « Double validation » n'existe plus. Réglages réels : part de l'avance, exercice (année, mois de début), messagerie, sauvegardes."),
    ("35_profil_utilisateur.html", CONFORME, "/mon-profil — agents/AgentProfileView.vue",
     "Changement de mot de passe : /changer-mot-de-passe."),
    ("36_import_releve.html", OBSOLETE, "aucun",
     "L'application n'importe aucun relevé opérateur (règle d'or) : les fichiers sont déposés à la main, puis confirmés."),
    ("37_marquage_lot_paye.html", OBSOLETE, "/paiements/mtn et /paiements/moov — « Confirmer le paiement »",
     "Pas d'écran dédié ni de reçus : le lot se confirme sur l'écran de génération, après dépôt réussi."),
    ("38_page_404.html", CONFORME, "/:pathMatch(.*)* — system/NotFoundView.vue", ""),
    ("39_page_403.html", CONFORME, "/403 — system/ForbiddenView.vue", ""),
    ("40_page_500.html", CONFORME, "/500 — system/ServerErrorView.vue", ""),
    ("41_page_maintenance.html", CONFORME, "/maintenance — system/MaintenanceView.vue", ""),
]

# Écrans réels sans maquette : (route, vue, rôle)
SANS_MAQUETTE = [
    ("/missions", "missions/MissionsIndexView.vue", "Missions et avancement de leurs deux états"),
    ("/etats", "etats/EtatListView.vue", "File de travail des états de paiement (contrôle, visa, validation)"),
    ("/missions/:missionId/etat", "etats/EtatDetailView.vue", "État des avances / des reliquats : circuit, numéro, PDF"),
    ("/ordres/:id/pdf", "missions/MissionPdfView.vue", "Aperçu et téléchargement du PDF officiel de l'OM"),
    ("/ordres/:id/historique", "missions/MissionHistoryView.vue", "Journal d'audit de l'OM"),
    ("/paiements/statuts", "paiements/PaiementStatutView.vue", "Synthèse des versements par opérateur et statut"),
    ("/paiements/historique", "paiements/PaiementHistoriqueView.vue", "Versements confirmés par période"),
    ("/paiements/regularisations", "paiements/PaiementRegularisationView.vue", "Trop-perçus : document de retenue, constat du recouvrement"),
    ("/rapports/trop-percus", "rapports/RapportTropPercusView.vue", "Synthèse des trop-perçus à recouvrer / recouvrés"),
    ("/admin/utilisateurs/:id/permissions", "admin/UserPermissionsView.vue", "Droits accordés ou retirés à un utilisateur"),
    ("/admin/signatures", "admin/SignaturesView.vue", "Identités et paraphes des signataires du circuit"),
    ("/admin/email", "admin/EmailConfigView.vue", "Configuration SMTP"),
    ("/admin/sauvegardes", "admin/BackupView.vue", "Sauvegardes : création, téléchargement, restauration"),
    ("/changer-mot-de-passe", "auth/ForcePasswordChangeView.vue", "Changement de mot de passe imposé"),
]

ECART_COMMUN = ("Écart commun à toutes les maquettes : elles n'appliquent pas la charte actuelle "
                "(police Plus Jakarta Sans au lieu de Sora / DM Sans, vert #009639 au lieu de #1D9E4F, "
                "icônes Font Awesome au lieu de SVG en ligne).")

DEBUT, FIN = "<!-- annotation-maquette:debut -->", "<!-- annotation-maquette:fin -->"
ICONE = ('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:none;margin-top:2px">'
         '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>')


def retirer(s):
    return re.sub(re.escape(DEBUT) + r".*?" + re.escape(FIN) + r"\n?", "", s, flags=re.S)


def encart(statut, ecran, ecarts):
    libelle, couleur, fond = STATUTS[statut]
    e = html.escape
    corps = f"<div><strong>Écran réel :</strong> {e(ecran)}</div>"
    if ecarts:
        corps += f"<div style=\"margin-top:4px\"><strong>Écarts :</strong> {e(ecarts)}</div>"
    return (
        f"{DEBUT}\n"
        f"<aside role=\"note\" aria-label=\"État de la maquette\" style=\"position:fixed;right:16px;bottom:16px;"
        f"z-index:9999;max-width:380px;background:{fond};color:#1f2937;border:1px solid {couleur};"
        f"border-left:4px solid {couleur};border-radius:12px;padding:12px 14px;"
        f"box-shadow:0 8px 24px rgba(0,0,0,.12);font:12px/1.45 'DM Sans',system-ui,sans-serif\">"
        f"<div style=\"display:flex;gap:8px;color:{couleur}\">{ICONE}"
        f"<div style=\"font-family:'Sora','DM Sans',sans-serif;font-weight:700\">Maquette d'origine (août 2026) — "
        f"{e(libelle)}</div></div>"
        f"<div style=\"margin-top:6px\">{corps}</div>"
        f"<div style=\"margin-top:6px;color:#6b7280\">Non redessinée ; confrontée au code le {DATE}. "
        f"<a href=\"00_galerie_index.html#correspondance\" style=\"color:{couleur}\">Correspondance complète</a>.</div>"
        f"</aside>\n{FIN}\n"
    )


def inserer_apres_body(s, bloc):
    m = re.search(r"<body[^>]*>\n?", s)
    if not m:
        raise SystemExit("balise <body> introuvable")
    return s[:m.end()] + bloc + s[m.end():]


def tableau_galerie():
    e = html.escape
    lignes = []
    for fichier, statut, ecran, ecarts in MAQUETTES:
        libelle, couleur, fond = STATUTS[statut]
        lignes.append(
            f"<tr style=\"border-top:1px solid #f3f4f6\"><td style=\"padding:8px\"><a href=\"{fichier}\" "
            f"style=\"color:#2563EB\">{e(fichier)}</a></td><td style=\"padding:8px\"><span style=\"background:{fond};"
            f"color:{couleur};border-radius:999px;padding:2px 8px;font-weight:700;font-size:11px;white-space:nowrap\">{e(libelle)}</span></td>"
            f"<td style=\"padding:8px\">{e(ecran)}</td><td style=\"padding:8px;color:#4b5563\">{e(ecarts) or '—'}</td></tr>"
        )
    absents = "".join(
        f"<tr style=\"border-top:1px solid #f3f4f6\"><td style=\"padding:8px\"><code>{e(r)}</code></td>"
        f"<td style=\"padding:8px\">{e(v)}</td><td style=\"padding:8px;color:#4b5563\">{e(d)}</td></tr>"
        for r, v, d in SANS_MAQUETTE
    )
    n = {k: sum(1 for m in MAQUETTES if m[1] == k) for k in STATUTS}
    return (
        f"{DEBUT}\n"
        f"<section id=\"correspondance\" style=\"background:#fff;border:1px solid #e5e7eb;border-radius:16px;"
        f"padding:20px;margin-bottom:32px;font:13px/1.5 'DM Sans',system-ui,sans-serif;color:#1f2937\">"
        f"<h2 style=\"font-family:'Sora','DM Sans',sans-serif;font-size:16px;font-weight:800;margin:0 0 6px\">"
        f"Correspondance avec l'application (au {DATE})</h2>"
        f"<p style=\"margin:0 0 6px\">Ces maquettes datent d'août 2026, avant la retranscription des OM SEMO et le "
        f"circuit de validation par état de paiement. Elles ne sont <strong>pas redessinées</strong> : l'application "
        f"fait foi. Bilan : {n[CONFORME]} fidèles dans l'esprit, {n[ECARTS]} avec écarts, {n[OBSOLETE]} obsolètes, "
        f"{len(SANS_MAQUETTE)} écrans réels sans maquette.</p>"
        f"<p style=\"margin:0 0 12px;color:#6b7280\">{e(ECART_COMMUN)} Référence des écrans : "
        f"<code>frontend/src/router/index.ts</code>, registre de <code>CLAUDE.md</code> §5. Encarts régénérés par "
        f"<code>docs/annoter_maquettes.py</code>.</p>"
        f"<div style=\"overflow-x:auto\"><table style=\"width:100%;border-collapse:collapse;font-size:12px\">"
        f"<thead><tr style=\"text-align:left;color:#6b7280\"><th style=\"padding:8px\">Maquette</th>"
        f"<th style=\"padding:8px\">État</th><th style=\"padding:8px\">Écran réel</th><th style=\"padding:8px\">Écarts</th>"
        f"</tr></thead><tbody>{''.join(lignes)}</tbody></table></div>"
        f"<h3 style=\"font-family:'Sora','DM Sans',sans-serif;font-size:14px;font-weight:800;margin:18px 0 6px\">"
        f"Écrans réels sans maquette</h3>"
        f"<div style=\"overflow-x:auto\"><table style=\"width:100%;border-collapse:collapse;font-size:12px\">"
        f"<thead><tr style=\"text-align:left;color:#6b7280\"><th style=\"padding:8px\">Route</th>"
        f"<th style=\"padding:8px\">Vue</th><th style=\"padding:8px\">Rôle</th></tr></thead><tbody>{absents}</tbody>"
        f"</table></div></section>\n{FIN}\n"
    )


def main():
    for fichier, statut, ecran, ecarts in MAQUETTES:
        chemin = os.path.join(ICI, fichier)
        s = retirer(open(chemin, encoding="utf-8").read())
        open(chemin, "w", encoding="utf-8").write(inserer_apres_body(s, encart(statut, ecran, ecarts)))

    chemin = os.path.join(ICI, "00_galerie_index.html")
    s = retirer(open(chemin, encoding="utf-8").read())
    m = re.search(r"<main[^>]*>\n?", s)
    if not m:
        raise SystemExit("balise <main> introuvable dans la galerie")
    s = s[:m.end()] + tableau_galerie() + s[m.end():]
    s = s.replace("41/41 complétés", "Maquettes d'origine — non tenues à jour")
    open(chemin, "w", encoding="utf-8").write(s)
    print(f"{len(MAQUETTES)} maquettes annotées ; galerie mise à jour.")


if __name__ == "__main__":
    main()
