# -*- coding: utf-8 -*-
"""
Génère les pages d'écran de docs/ (00_galerie_index.html, 01_login.html … 53_moov.html) à partir des
CAPTURES de l'application réelle (docs/ecrans/*.jpg, produites par docs/capturer_ecrans.cjs).

Usage :
    node docs/capturer_ecrans.cjs      # API + SPA démarrés sur les données de démonstration
    python3 docs/generer_maquettes.py

Refonte du 26/10/2026 : les maquettes dessinées en août 2026 (avant la retranscription SEMO et le
circuit par état de paiement) sont remplacées par les écrans réels. Les anciens noms de fichiers
sont conservés pour ne casser aucun lien ; une ancienne maquette dont la fonction n'existe pas
renvoie vers l'écran qui la remplace. Les maquettes d'origine restent dans l'historique git.

Charte appliquée (CLAUDE.md §4) : Sora pour les titres, DM Sans pour le texte, logo ANaTT local,
flag-bar avant chaque pied de page, card-hover sur les cartes cliquables, icônes SVG en ligne.
"""
import html
import json
import os

ICI = os.path.dirname(os.path.abspath(__file__))
LOGO = "../frontend/src/assets/logo-anatt.png"

# Profils utilisés pour les captures (comptes de démonstration).
PROFILS = {
    None: "Non connecté",
    "superadmin@anatt.bj": "Super administrateur",
    "gestion@anatt.bj": "Gestionnaire OM",
    "finance@anatt.bj": "Agent financier",
    "demo.controleur@anatt.bj": "Contrôleur (circuit de démonstration)",
}

MODULES = [
    ("auth", "Authentification"),
    ("dash", "Tableau de bord"),
    ("pers", "Personnel"),
    ("om", "Missions et ordres de mission"),
    ("etat", "États de paiement"),
    ("pay", "Paiements"),
    ("taux", "Barème"),
    ("rap", "Rapports"),
    ("aud", "Audit"),
    ("adm", "Administration"),
    ("sys", "Pages système"),
]

# (page html, capture, module, titre, vue, description, ancienne maquette remplacée par un autre écran)
ECRANS = [
    ("01_login.html", "01_login", "auth", "Connexion", "auth/LoginView.vue",
     "E-mail et mot de passe. Un échec affiche un message neutre ; un compte créé ou réinitialisé doit changer son mot de passe à la connexion suivante.", None),
    ("02_mot_de_passe_oublie.html", "02_mot_de_passe_oublie", "auth", "Mot de passe oublié", "auth/ForgotPasswordView.vue",
     "Envoi d'un lien de réinitialisation pointant vers le SPA.", None),
    ("03_reinitialisation_mdp.html", "03_reinitialisation_mdp", "auth", "Réinitialisation du mot de passe", "auth/ResetPasswordView.vue",
     "Nouveau mot de passe et confirmation, avec les règles de complexité. La réinitialisation révoque les sessions ouvertes.", None),
    ("04_dashboard.html", "04_dashboard", "dash", "Tableau de bord", "dashboard/DashboardView.vue",
     "Ordres de mission actifs, versements confirmés et montant payé du mois, paiements par opérateur, derniers OM, actions rapides selon les droits.", None),
    ("05_personnel_liste.html", "05_personnel_liste", "pers", "Personnel", "agents/AgentListView.vue",
     "Référentiel des bénéficiaires : catégorie d'indice, opérateur et téléphone (source des fichiers de paiement). Un agent est désactivé, jamais supprimé.", None),
    ("06_formulaire_personnel.html", "06_formulaire_personnel", "pers", "Nouvel agent", "agents/AgentCreateView.vue",
     "La catégorie se déduit de l'indice ou du salaire brut ; le numéro (10 chiffres, préfixe 01) doit correspondre à l'opérateur.", None),
    ("07_detail_personnel.html", "07_detail_personnel", "pers", "Fiche agent", "agents/AgentDetailView.vue",
     "Identité, catégorie, coordonnées Mobile Money et ordres de mission de l'agent.", None),
    ("08_modification_personnel.html", "08_modification_personnel", "pers", "Modifier l'agent", "agents/AgentEditView.vue",
     "La catégorie est recalculée dès que l'indice ou le salaire change.", None),
    ("09_import_personnel.html", "09_import_personnel", "pers", "Import du personnel", "agents/AgentImportView.vue",
     "Import .xlsx / .csv « tout ou rien » : une ligne en erreur annule l'import et le rapport ligne à ligne s'affiche sur cet écran.", None),
    ("10_rapport_import.html", "09_import_personnel", "pers", "Rapport d'import", "agents/AgentImportView.vue",
     "Pas d'écran séparé : le rapport d'erreurs ligne à ligne s'affiche sur l'écran d'import.", "09_import_personnel.html"),
    ("11_liste_ordres_mission.html", "11_liste_ordres_mission", "om", "Ordres de mission", "missions/MissionListView.vue",
     "1 OM = 1 bénéficiaire, identifié par sa référence SEMO. Filtres statut, zone, opérateur du versement et période.", None),
    ("12_om_creation_etape1.html", "12_om_creation", "om", "Retranscription d'une mission", "missions/MissionCreateView.vue",
     "Les OM ne sont pas créés : ils sont RETRANSCRITS depuis SEMO. Étape 1 : contexte de la mission ; la zone se déduit de la destination.", None),
    ("13_om_creation_etape2.html", "12_om_creation", "om", "Retranscription — bénéficiaires", "missions/MissionCreateView.vue",
     "Étape 2 du même assistant : un OM par bénéficiaire (participant ou CAV) avec sa référence SEMO ; un agent ne figure qu'une fois par mission. Per diem calculé en temps réel.", "12_om_creation_etape1.html"),
    ("14_om_creation_etape3.html", "12_om_creation", "om", "Retranscription — récapitulatif", "missions/MissionCreateView.vue",
     "Étape 3 : vérification puis enregistrement de la mission et de ses OM au statut IMPORTE. La validation se fait ensuite sur l'état de paiement.", "12_om_creation_etape1.html"),
    ("15_detail_om.html", "15_detail_om", "om", "Fiche d'un ordre de mission", "missions/MissionDetailView.vue",
     "Mission, bénéficiaire, indemnité et versements AVANCE / RELIQUAT. Ici un OM amendé après paiement de l'avance : le reliquat devient un trop-perçu. Liens « Historique », « PDF de l'OM », bouton « Amender ».", None),
    ("16_modification_om.html", "15_detail_om", "om", "Modification d'un OM", "missions/MissionDetailView.vue",
     "Un OM ne se modifie pas : /ordres/:id/modifier redirige vers la fiche, et seul l'amendement (avenant, motif obligatoire) ajuste ses jours facturés.", "15_detail_om.html"),
    ("17_validation_om.html", "17_etat_paiement", "etat", "État de paiement d'une mission", "etats/EtatDetailView.vue",
     "La validation porte sur l'état de paiement, par tranche : contrôle, visa du DAF, validation du DG, avec numéro de registre et codes de vérification. Ici l'état des avances validé.", None),
    ("18_liste_om_attente.html", "18_file_etats", "etat", "États de paiement (file de travail)", "etats/EtatListView.vue",
     "Chaque signataire retrouve les états qui attendent son intervention ; filtres statut, tranche et recherche.", None),
    ("19_generation_fichiers.html", "19_generation_mtn", "pay", "Génération du fichier MTN", "paiements/PaiementMtnView.vue (PaiementGeneration.vue)",
     "Un fichier = 1 mission × 1 tranche × 1 opérateur. Aperçu des bénéficiaires éligibles, puis génération (.txt), dépôt manuel, confirmation ou passage en erreur.", None),
    ("20_lots_paiement.html", "20_lots_paiement", "pay", "Lots de paiement", "paiements/PaiementListView.vue",
     "Statuts GENERE / CONFIRME / ERREUR, re-téléchargement des fichiers, badge « complémentaire n° N ».", None),
    ("21_detail_lot.html", "19_generation_mtn", "pay", "Détail d'un lot", "paiements/PaiementMtnView.vue",
     "Pas d'écran de détail : l'aperçu des bénéficiaires et les actions du lot se trouvent sur l'écran de génération.", "19_generation_fichiers.html"),
    ("22_reconciliation.html", "22_reconciliation", "pay", "Confirmation des paiements", "paiements/PaiementReconciliationView.vue",
     "Écran d'information : aucun réimport de fichier opérateur ni rapprochement automatique ; la confirmation d'un lot est manuelle.", None),
    ("23_consultation_baremes.html", "23_consultation_baremes", "taux", "Barème des indemnités", "taux/TauxListView.vue",
     "Taux journaliers par catégorie d'indice, zone (Intérieur, Extérieur I, II = III) et type de prise en charge.", None),
    ("24_modification_baremes.html", "24_modification_baremes", "taux", "Modifier le barème", "taux/TauxEditView.vue",
     "Seul le taux journalier se modifie ; les zones II et III restent synchronisées.", None),
    ("25_rapports.html", "25_rapports", "rap", "Rapports et états", "rapports/RapportDashboardView.vue",
     "Synthèse des versements confirmés du mois et accès aux états.", None),
    ("26_rapport_mensuel.html", "26_rapport_mensuel", "rap", "État mensuel", "rapports/RapportMensuelView.vue",
     "Versements confirmés du mois (date de paiement, heure du Bénin), par opérateur et par tranche, avec le pipeline par statut.", None),
    ("27_rapport_personnel.html", "27_rapport_personnel", "rap", "États par dimension", "rapports/RapportAgentView.vue",
     "Par agent, opérateur, zone ou imputation budgétaire, sur une période.", None),
    ("28_export_rapports.html", "28_export_rapports", "rap", "Export personnalisé", "rapports/RapportExportView.vue",
     "Export Excel ou PDF par dimension et période.", None),
    ("29_journal_audit.html", "29_journal_audit", "aud", "Journal d'audit", "audit/AuditListView.vue",
     "Qui, quoi, quand, adresse IP ; filtres par action, entité et dates.", None),
    ("30_detail_action_audit.html", "30_detail_action_audit", "aud", "Détail d'une action", "audit/AuditDetailView.vue",
     "Données enregistrées avec l'action, telles qu'au moment de l'action.", None),
    ("31_gestion_utilisateurs.html", "31_gestion_utilisateurs", "adm", "Utilisateurs", "admin/UserManagementView.vue",
     "Comptes, rôles, statut, réinitialisation du mot de passe ; la modification du rôle révoque les sessions.", None),
    ("32_formulaire_utilisateur.html", "32_formulaire_utilisateur", "adm", "Nouvel utilisateur", "admin/UserCreateView.vue",
     "Mot de passe temporaire, changement imposé à la première connexion.", None),
    ("33_gestion_roles.html", "33_gestion_roles", "adm", "Matrice des permissions", "admin/RolesView.vue",
     "Permissions par profil, éditables à chaud par le super administrateur ; séparation ordonnateur / payeur contrôlée côté serveur.", None),
    ("34_parametres.html", "34_parametres", "adm", "Paramètres du système", "admin/SettingsView.vue",
     "Part de l'avance, exercice (année, mois de début), messagerie, sauvegardes. Le fuseau (heure du Bénin) est réglé sur le serveur.", None),
    ("35_profil_utilisateur.html", "35_profil_utilisateur", "adm", "Mon profil", "agents/AgentProfileView.vue",
     "Identité et permissions effectives du compte connecté.", None),
    ("36_import_releve.html", "22_reconciliation", "pay", "Import d'un relevé opérateur", "paiements/PaiementReconciliationView.vue",
     "Fonction absente (règle d'or) : l'application n'importe aucun relevé ; les fichiers sont déposés à la main, puis confirmés.", "22_reconciliation.html"),
    ("37_marquage_lot_paye.html", "19_generation_mtn", "pay", "Confirmer un lot payé", "paiements/PaiementMtnView.vue",
     "Pas d'écran dédié : le lot se confirme sur l'écran de génération (« Confirmer le paiement »), après dépôt réussi.", "19_generation_fichiers.html"),
    ("38_page_404.html", "38_page_404", "sys", "Page introuvable (404)", "system/NotFoundView.vue", "", None),
    ("39_page_403.html", "39_page_403", "sys", "Accès interdit (403)", "system/ForbiddenView.vue", "", None),
    ("40_page_500.html", "40_page_500", "sys", "Erreur serveur (500)", "system/ServerErrorView.vue", "", None),
    ("41_page_maintenance.html", "41_page_maintenance", "sys", "Maintenance", "system/MaintenanceView.vue",
     "Vérification automatique de la disponibilité de l'API.", None),
    # Écrans sans maquette d'origine
    ("42_missions.html", "42_missions", "om", "Missions", "missions/MissionsIndexView.vue",
     "Chaque mission avec ses OM et l'avancement de ses deux états (avances, reliquats).", None),
    ("43_pdf_om.html", "43_pdf_om", "om", "Aperçu du PDF de l'OM", "missions/MissionPdfView.vue",
     "Aperçu imprimable ; le PDF officiel, généré par le serveur, reprend les signatures de l'état des avances.", None),
    ("44_historique_om.html", "44_historique_om", "om", "Historique d'un OM", "missions/MissionHistoryView.vue",
     "Journal d'audit de l'OM : retranscription, amendements, validation.", None),
    ("45_paiements_statuts.html", "45_paiements_statuts", "pay", "Statuts des versements", "paiements/PaiementStatutView.vue",
     "Synthèse des versements par opérateur et statut.", None),
    ("46_paiements_historique.html", "46_paiements_historique", "pay", "Historique des paiements", "paiements/PaiementHistoriqueView.vue",
     "Versements confirmés par période.", None),
    ("47_regularisations.html", "47_regularisations", "pay", "Régularisations", "paiements/PaiementRegularisationView.vue",
     "Trop-perçus nés d'un amendement : document de retenue sur salaire pour la RH, puis constat du recouvrement.", None),
    ("48_rapport_trop_percus.html", "48_rapport_trop_percus", "rap", "Trop-perçus", "rapports/RapportTropPercusView.vue",
     "Montants à recouvrer et recouvrés, par opérateur.", None),
    ("49_permissions_utilisateur.html", "49_permissions_utilisateur", "adm", "Permissions d'un utilisateur", "admin/UserPermissionsView.vue",
     "Droits accordés ou retirés à une personne, au-delà de son profil ; les notifications suivent ces droits effectifs.", None),
    ("50_signatures.html", "50_signatures", "adm", "Signatures", "admin/SignaturesView.vue",
     "Identités et paraphes des titulaires des postes du circuit, imprimés sur les états.", None),
    ("51_config_email.html", "51_config_email", "adm", "Configuration e-mail", "admin/EmailConfigView.vue",
     "Serveur SMTP et expéditeur appliqués à l'envoi (vide : le .env du serveur) ; e-mail de test ; notifications envoyées.", None),
    ("52_sauvegardes.html", "52_sauvegardes", "adm", "Sauvegardes", "admin/BackupView.vue",
     "Création, téléchargement et restauration (confirmation par recopie du nom, sauvegarde de sécurité préalable).", None),
    ("53_moov.html", "53_moov", "pay", "Génération du fichier Moov", "paiements/PaiementMoovView.vue (PaiementGeneration.vue)",
     "Même parcours que MTN, fichier .xlsx (numéro en texte, montant numérique).", None),
]

CSS = """
:root{--anatt:#1D9E4F;--anatt-600:#1A8C48;--anatt-50:#EDFAF2;--gold:#D4A843;--red:#CE1126;--ink:#111827;--muted:#6B7280}
*{box-sizing:border-box}body{margin:0;background:#F9FAFB;color:var(--ink);font:15px/1.55 'DM Sans',system-ui,sans-serif}
h1,h2,h3,.display,a.btn,.badge,label{font-family:'Sora','DM Sans',sans-serif}
a{color:var(--anatt-600)}
header{background:#fff;border-bottom:1px solid #E5E7EB}
.wrap{max-width:1200px;margin:0 auto;padding:0 24px}
.top{display:flex;align-items:center;justify-content:space-between;gap:16px;height:68px}
.top img{height:36px}.top nav a{margin-left:16px;font-family:'Sora',sans-serif;font-weight:600;font-size:13px;text-decoration:none}
main{padding:32px 0 48px}
.fil{font-size:12px;color:var(--muted);margin-bottom:6px}
h1{font-size:26px;font-weight:800;margin:0 0 8px}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 20px}
.badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;background:var(--anatt-50);color:var(--anatt-600)}
.badge.gold{background:#FBF3E0;color:#8B6914}.badge.blue{background:#EFF6FF;color:#2563EB}
.note{background:#FBF3E0;border:1px solid #EBCF8A;border-left:4px solid var(--gold);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:14px}
.capture{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:12px;box-shadow:0 16px 40px -10px rgba(0,0,0,.08)}
.capture img{display:block;width:100%;height:auto;border-radius:8px}
.pied-nav{display:flex;justify-content:space-between;gap:12px;margin-top:24px}
a.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:12px;border:2px solid var(--anatt);color:var(--anatt-600);font-weight:600;font-size:13px;text-decoration:none;transition:all .2s}
a.btn:hover{background:var(--anatt);color:#fff}
.flag-bar{height:3px;background:linear-gradient(90deg,#1D9E4F 0%,#1D9E4F 33%,#D4A843 33%,#D4A843 66%,#CE1126 66%,#CE1126 100%)}
footer{background:#fff;color:var(--muted);font-size:12px;padding:16px 0}
.grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.carte{display:block;background:#fff;border:1px solid #F3F4F6;border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;position:relative}
.carte img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;object-position:top;border-bottom:1px solid #F3F4F6}
.carte .corps{padding:12px 14px}.carte h3{font-size:14px;margin:0 0 4px}.carte p{margin:0;font-size:12px;color:var(--muted)}
.carte .liseré{position:absolute;top:0;left:0;right:0;height:4px;background:var(--anatt);transform:scaleX(0);transform-origin:left;transition:transform .5s}
.carte:hover .liseré{transform:scaleX(1)}
.card-hover{transition:all .3s cubic-bezier(.4,0,.2,1)}.card-hover:hover{transform:translateY(-4px);box-shadow:0 16px 40px -10px rgba(0,0,0,.08)}
section.module{margin-top:36px}section.module h2{font-size:18px;font-weight:800;margin:0 0 14px}
@media (max-width:640px){.top nav{display:none}h1{font-size:21px}}
"""

FLECHE_G = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>'
FLECHE_D = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>'


def gabarit(titre, corps, capture_le):
    return f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{html.escape(titre)} — Écrans ANaTT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<style>{CSS}</style>
</head>
<body>
<!-- Page générée par docs/generer_maquettes.py — ne pas modifier à la main. -->
<header><div class="wrap top"><a href="00_galerie_index.html"><img src="{LOGO}" alt="ANaTT"></a>
<nav><a href="00_galerie_index.html">Galerie des écrans</a></nav></div></header>
<main><div class="wrap">
{corps}
</div></main>
<div class="flag-bar"></div>
<footer><div class="wrap">ANaTT — Agence Nationale des Transports Terrestres, République du Bénin · Écrans capturés sur l'application réelle (données de démonstration) le {capture_le}.</div></footer>
</body>
</html>
"""


def page_ecran(i, ecran, captures, capture_le):
    fichier, capture, module, titre, vue, description, remplace = ecran
    e = html.escape
    info = captures[capture]
    route = info["url"]
    profil = PROFILS.get(info["compte"], info["compte"])
    nom_module = dict(MODULES)[module]
    note = ""
    if remplace:
        note = (f'<div class="note"><strong>Ancienne maquette sans écran propre.</strong> {e(description)} '
                f'Écran correspondant : <a href="{remplace}">{e(remplace)}</a>.</div>')
    texte = "" if remplace else (f"<p>{e(description)}</p>" if description else "")
    prec = ECRANS[i - 1][0] if i > 0 else None
    suiv = ECRANS[i + 1][0] if i + 1 < len(ECRANS) else None
    nav = '<div class="pied-nav">'
    nav += f'<a class="btn" href="{prec}">{FLECHE_G} Écran précédent</a>' if prec else "<span></span>"
    nav += f'<a class="btn" href="{suiv}">Écran suivant {FLECHE_D}</a>' if suiv else "<span></span>"
    nav += "</div>"
    corps = (
        f'<div class="fil"><a href="00_galerie_index.html">Galerie</a> › {e(nom_module)}</div>'
        f"<h1>{e(titre)}</h1>{texte}"
        f'<div class="meta"><span class="badge">{e(route.split("?")[0])}</span>'
        f'<span class="badge blue">{e(vue)}</span><span class="badge gold">Profil : {e(profil)}</span></div>'
        f"{note}"
        f'<div class="capture"><img src="ecrans/{capture}.jpg" alt="Capture de l\'écran « {e(titre)} »" loading="lazy"></div>'
        f"{nav}"
    )
    return gabarit(titre, corps, capture_le)


def galerie(captures, capture_le):
    e = html.escape
    sections = []
    for code, nom in MODULES:
        cartes = []
        for fichier, capture, module, titre, vue, description, remplace in ECRANS:
            if module != code or remplace:
                continue
            cartes.append(
                f'<a class="carte card-hover" href="{fichier}"><span class="liseré"></span>'
                f'<img src="ecrans/{capture}.jpg" alt="" loading="lazy">'
                f'<div class="corps"><h3>{e(titre)}</h3><p>{e(captures[capture]["url"].split("?")[0])}</p></div></a>'
            )
        if cartes:
            sections.append(f'<section class="module"><h2>{e(nom)}</h2><div class="grille">{"".join(cartes)}</div></section>')
    anciennes = "".join(
        f'<li><a href="{f}">{e(f)}</a> → <a href="{r}">{e(r)}</a> — {e(d)}</li>'
        for f, c, m, t, v, d, r in ECRANS if r
    )
    n = sum(1 for x in ECRANS if not x[6])
    corps = (
        "<h1>Galerie des écrans</h1>"
        f"<p>{n} écrans de l'application, capturés sur le build de production avec les données de démonstration "
        f"(mission DEMO-CIRCUIT avancée jusqu'à la validation de l'état des avances, un amendement en trop-perçu et un "
        f"lot MTN généré). Ces pages remplacent les maquettes dessinées en août 2026 ; elles se régénèrent avec "
        f"<code>docs/capturer_ecrans.cjs</code> puis <code>docs/generer_maquettes.py</code>.</p>"
        f'<div class="meta"><span class="badge">Capturé le {e(capture_le)}</span>'
        f'<span class="badge blue">Référence : frontend/src/router/index.ts, CLAUDE.md §5</span></div>'
        + "".join(sections)
        + '<section class="module"><h2>Anciennes maquettes sans écran propre</h2>'
        f"<p>Leur nom de fichier est conservé ; chacune renvoie vers l'écran qui assure la fonction.</p><ul>{anciennes}</ul></section>"
    )
    return gabarit("Galerie des écrans", corps, capture_le)


def main():
    index = json.load(open(os.path.join(ICI, "ecrans", "ecrans.json"), encoding="utf-8"))
    captures = {c["fichier"]: c for c in index["ecrans"]}
    capture_le = index["capture_le"][:10]
    capture_le = f"{capture_le[8:10]}/{capture_le[5:7]}/{capture_le[0:4]}"
    manquantes = sorted({x[1] for x in ECRANS} - set(captures))
    if manquantes:
        raise SystemExit(f"Captures manquantes : {', '.join(manquantes)} (relancer docs/capturer_ecrans.cjs)")

    for i, ecran in enumerate(ECRANS):
        with open(os.path.join(ICI, ecran[0]), "w", encoding="utf-8") as f:
            f.write(page_ecran(i, ecran, captures, capture_le))
    with open(os.path.join(ICI, "00_galerie_index.html"), "w", encoding="utf-8") as f:
        f.write(galerie(captures, capture_le))
    print(f"{len(ECRANS)} pages d'écran et la galerie générées.")


if __name__ == "__main__":
    main()
