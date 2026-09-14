# -*- coding: utf-8 -*-
"""
Génère la Documentation Technique et Fonctionnelle ANaTT (.docx).

Usage :
    python docs/generer_documentation_technique.py
Dépendance :
    pip install python-docx

Le contenu reflète l'IMPLÉMENTATION RÉELLE de l'application (périmètre, rôles RBAC
par permission, workflows, statuts, barème, formats de fichiers MTN/Moov, API).
À régénérer après toute évolution technique ou fonctionnelle majeure.

Sources de vérité : CLAUDE.md, backend/routes/api.php, app/Support/Permissions,
seeders (Bareme/Zone/User), générateurs MTN/Moov, frontend/src/router & utils/permissions.
"""
import os
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

ICI = os.path.dirname(os.path.abspath(__file__))
LOGO = os.path.join(ICI, "logoannat.png")
SORTIE = os.path.join(ICI, "ANaTT_Documentation_Technique_Fonctionnelle.docx")

# Couleurs de la charte ANaTT
VERT = RGBColor(0x1D, 0x9E, 0x4F)
VERT_FONCE = RGBColor(0x0A, 0x3D, 0x1F)
OR = RGBColor(0xB8, 0x89, 0x1A)
ROUGE = RGBColor(0xCE, 0x11, 0x26)
GRIS = RGBColor(0x4B, 0x55, 0x63)
BLANC = RGBColor(0xFF, 0xFF, 0xFF)

doc = Document()

# ---- Styles de base ----
normal = doc.styles["Normal"]
normal.font.name = "Calibri"
normal.font.size = Pt(11)
normal.paragraph_format.space_after = Pt(6)

for i, taille in [(1, 18), (2, 14), (3, 12)]:
    s = doc.styles[f"Heading {i}"]
    s.font.name = "Calibri"
    s.font.size = Pt(taille)
    s.font.bold = True
    s.font.color.rgb = VERT if i == 1 else (VERT_FONCE if i == 2 else OR)


def shade(cell, hexa):
    tcPr = cell._tc.get_or_add_tcPr()
    sh = OxmlElement("w:shd")
    sh.set(qn("w:val"), "clear")
    sh.set(qn("w:fill"), hexa)
    tcPr.append(sh)


def set_cell_text(cell, texte, bold=False, color=None, size=10, align=None):
    cell.text = ""
    p = cell.paragraphs[0]
    if align:
        p.alignment = align
    run = p.add_run(texte)
    run.bold = bold
    run.font.size = Pt(size)
    if color:
        run.font.color.rgb = color


def flag_bar():
    """Barre tricolore (vert / or / rouge) sur toute la largeur."""
    t = doc.add_table(rows=1, cols=3)
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for cell, couleur in zip(t.rows[0].cells, ["1D9E4F", "D4A843", "CE1126"]):
        shade(cell, couleur)
        cell.paragraphs[0].add_run(" ").font.size = Pt(2)
    for cell in t.rows[0].cells:
        cell.width = Cm(5.6)


def h1(t): doc.add_heading(t, level=1)
def h2(t): doc.add_heading(t, level=2)
def h3(t): doc.add_heading(t, level=3)


def para(t, bold=False, italic=False, color=None):
    p = doc.add_paragraph()
    r = p.add_run(t)
    r.bold = bold
    r.italic = italic
    if color:
        r.font.color.rgb = color
    return p


def bullet(t):
    doc.add_paragraph(t, style="List Bullet")


def etape(t):
    doc.add_paragraph(t, style="List Number")


def code(t):
    """Bloc de code / monospace sur fond clair."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = tbl.rows[0].cells[0]
    shade(cell, "0A3D1F")
    cell.width = Cm(16.8)
    cell.text = ""
    for i, ligne in enumerate(t.split("\n")):
        p = cell.paragraphs[0] if i == 0 else cell.add_paragraph()
        r = p.add_run(ligne)
        r.font.name = "Consolas"
        r.font.size = Pt(9)
        r.font.color.rgb = BLANC
    doc.add_paragraph()


def encadre(titre, t, fond="EDFAF2", couleur_titre=VERT_FONCE):
    """Petit encadré coloré (note / attention)."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = tbl.rows[0].cells[0]
    shade(cell, fond)
    cell.width = Cm(16.8)
    p = cell.paragraphs[0]
    r = p.add_run(titre + " : ")
    r.bold = True
    r.font.color.rgb = couleur_titre
    r.font.size = Pt(10)
    r2 = p.add_run(t)
    r2.font.size = Pt(10)
    doc.add_paragraph()


def tableau(entetes, lignes, largeurs=None):
    t = doc.add_table(rows=1, cols=len(entetes))
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.style = "Table Grid"
    for j, e in enumerate(entetes):
        c = t.rows[0].cells[j]
        shade(c, "1D9E4F")
        set_cell_text(c, e, bold=True, color=BLANC, size=10)
    for ligne in lignes:
        cells = t.add_row().cells
        for j, val in enumerate(ligne):
            set_cell_text(cells[j], str(val), size=9.5)
    if largeurs:
        for row in t.rows:
            for j, w in enumerate(largeurs):
                row.cells[j].width = Cm(w)
    doc.add_paragraph()
    return t


def toc():
    """Champ Sommaire Word (clic droit → « Mettre à jour les champs »)."""
    p = doc.add_paragraph()
    run = p.add_run()
    fldChar = OxmlElement("w:fldChar"); fldChar.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText"); instr.set(qn("xml:space"), "preserve")
    instr.text = 'TOC \\o "1-3" \\h \\z \\u'
    fld2 = OxmlElement("w:fldChar"); fld2.set(qn("w:fldCharType"), "separate")
    t = OxmlElement("w:t"); t.text = "Faites un clic droit ici puis « Mettre à jour les champs » pour générer le sommaire."
    fld3 = OxmlElement("w:fldChar"); fld3.set(qn("w:fldCharType"), "end")
    for el in (fldChar, instr, fld2, t, fld3):
        run._r.append(el)


# =========================================================================
# PAGE DE GARDE
# =========================================================================
flag_bar()
doc.add_paragraph()
if os.path.exists(LOGO):
    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(LOGO, width=Cm(6))
doc.add_paragraph()

t = doc.add_paragraph(); t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run("Documentation Technique\net Fonctionnelle"); r.bold = True; r.font.size = Pt(28); r.font.color.rgb = VERT

s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run("Système de Gestion des Ordres de Mission\net des Paiements Per Diem (Mobile Money MTN & Moov)")
r.font.size = Pt(14); r.font.color.rgb = GRIS

s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run("ANaTT — Agence Nationale des Transports Terrestres\nRépublique du Bénin")
r.font.size = Pt(12); r.font.color.rgb = GRIS

for _ in range(5):
    doc.add_paragraph()
m = doc.add_paragraph(); m.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = m.add_run("Version 1.2.0  •  Août 2026"); r.bold = True; r.font.color.rgb = OR
m = doc.add_paragraph(); m.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = m.add_run("Document interne — Usage restreint"); r.italic = True; r.font.size = Pt(10); r.font.color.rgb = GRIS
doc.add_page_break()

# =========================================================================
# SOMMAIRE
# =========================================================================
h1("Sommaire")
toc()
doc.add_page_break()

# =========================================================================
# 1. INTRODUCTION ET CONTEXTE
# =========================================================================
h1("1. Introduction et contexte")

h2("1.1 Présentation du projet")
para("L'application ANaTT de gestion des ordres de mission est une application web qui assure le suivi "
     "des ordres de mission (OM) émis par SEMO et la génération des fichiers de paiement Mobile Money "
     "(MTN, Moov) correspondant aux indemnités de mission (per diem). Elle dématérialise la chaîne qui "
     "va de la retranscription des OM jusqu'à la production des fichiers de virement à déposer sur les "
     "plateformes des opérateurs.")

encadre("Règle d'or — périmètre",
        "L'application NE crée PAS les ordres de mission (c'est SEMO qui les émet) et NE réalise PAS les "
        "paiements (effectués sur les plateformes MTN / Moov). Elle RETRANSCRIT les OM puis GÉNÈRE les "
        "fichiers de paiement. Conséquences directes : aucune API SEMO, aucune API de décaissement, et "
        "aucun réimport de fichier résultat opérateur (la confirmation des paiements est manuelle).")

h2("1.2 Périmètre fonctionnel")
para("Le système couvre les domaines suivants :")
bullet("Retranscription des ordres de mission émis par SEMO, regroupés par mission (événement).")
bullet("Référentiel des agents bénéficiaires (identité, catégorie, téléphone et opérateur Mobile Money).")
bullet("Calcul automatique des indemnités (per diem) selon le barème officiel ANaTT.")
bullet("Circuit de validation de l'état de paiement de la mission en trois niveaux (contrôleur, DAF, Directeur Général).")
bullet("Génération des fichiers de virement Mobile Money (.txt MTN / .xlsx Moov) et confirmation manuelle.")
bullet("Génération de documents officiels (OM en PDF) et d'états/rapports (Excel, PDF).")
bullet("Audit et traçabilité complète des actions sensibles.")

h2("1.3 Acteurs et profils utilisateurs")
para("L'autorisation repose sur 8 profils (rôles), avec une attribution par CLÉ DE PERMISSION (et non "
     "par appartenance hiérarchique). Le circuit de validation porte sur l'état de paiement de la mission "
     "et enchaîne trois signataires : contrôleur, DAF, puis Directeur Général. Les OM proviennent de SEMO.")
tableau(
    ["Profil", "Responsabilités principales"],
    [
        ["SUPER_ADMIN", "Accès total (passe toute garde). Seul à gérer les super administrateurs et les paramètres système. Créé à l'installation."],
        ["ADMIN", "Utilisateurs (hors super admins), référentiels, barème, audit. Supervise sans exécuter les paiements."],
        ["GESTIONNAIRE_OM", "Retranscrit et gère les missions, les OM et les agents ; établit le récapitulatif de l'état."],
        ["AGENT_FINANCIER", "Génère les fichiers MTN/Moov et confirme les paiements."],
        ["CONTROLEUR", "Étape 1 : contrôle de conformité de l'état de paiement ; consulte paiements et audit."],
        ["DAF", "Étape 2 : appose le visa « Vu » sur l'état (Directeur de l'Administration et des Finances)."],
        ["DG", "Étape 3 : valide définitivement l'état, ce qui ouvre le paiement (Directeur Général)."],
        ["LECTEUR", "Lecture seule (tableau de bord, OM, rapports)."],
    ],
    largeurs=[4.0, 12.8],
)
para("Séparation ordonnateur / payeur : le DAF et le DG autorisent, l'agent financier exécute. Ni le DAF "
     "ni le DG ne détiennent paiements.generer/confirmer ; une même personne ne peut porter le visa DAF et "
     "la validation finale d'un même état.", italic=True, color=GRIS)

h2("1.4 Contraintes et exigences")
bullet("Authentification obligatoire par tokens Laravel Sanctum (Bearer).")
bullet("Autorisation fine par permission (middleware permission:, Gates, Gate::before pour le super admin).")
bullet("Référence SEMO unique par OM (clé naturelle anti-doublon).")
bullet("Idempotence des paiements : un versement confirmé n'est jamais réinclus dans un nouveau fichier.")
bullet("Journalisation (audit log) de toutes les actions sensibles.")
bullet("Formats de fichiers MTN/Moov strictement conformes aux gabarits validés par les opérateurs.")
bullet("Export PDF des OM conforme aux standards administratifs (en-tête officiel ANaTT).")

# =========================================================================
# 2. ARCHITECTURE TECHNIQUE
# =========================================================================
h1("2. Architecture technique")

h2("2.1 Vue d'ensemble")
para("Le système suit une architecture en couches découplées : une SPA (frontend) consomme une API REST "
     "(backend) protégée par tokens. Cette séparation facilite la maintenance, les tests et le déploiement.")
encadre("Architecture",
        "SPA Vue 3 (frontend) → API REST Laravel 11 (backend) → base MySQL. Communication HTTP/HTTPS avec "
        "tokens Bearer (Sanctum). Files de travaux (queue) pour les générations volumineuses (fichiers de "
        "paiement, états). Déploiement : WampServer ou Docker Compose (dev) ; serveur Linux + Nginx (prod).",
        fond="EDFAF2")

h2("2.2 Stack technologique")
h3("2.2.1 Frontend (interface utilisateur)")
tableau(
    ["Technologie", "Version", "Usage"],
    [
        ["Vue.js", "3.x (Composition API)", "Framework JavaScript principal"],
        ["TypeScript", "5.x", "Typage statique strict"],
        ["Vite", "5.x", "Build et serveur de développement"],
        ["Tailwind CSS", "3.4+", "Framework CSS utilitaire (charte ANaTT)"],
        ["Pinia", "2.x", "Gestion de l'état global (stores)"],
        ["Vue Router", "4.x", "Navigation SPA + guards d'authentification/permissions"],
        ["Axios", "1.x", "Client HTTP avec intercepteurs (token Bearer)"],
        ["Chart.js", "4.x", "Graphiques du tableau de bord et des rapports"],
        ["VuePDF", "—", "Aperçu PDF dans le navigateur"],
        ["Vitest", "—", "Tests unitaires et d'intégration"],
    ],
    largeurs=[4.6, 4.0, 8.2],
)
h3("2.2.2 Backend (API et logique métier)")
tableau(
    ["Technologie", "Version", "Usage"],
    [
        ["Laravel", "11.x", "Framework PHP principal"],
        ["PHP", "8.3+", "Langage backend"],
        ["Laravel Sanctum", "—", "Authentification API par tokens"],
        ["MySQL", "8.0", "Base de données (SQLite en tests)"],
        ["Redis", "7.x (optionnel)", "Cache et files (driver « database » utilisable sans Redis)"],
        ["DomPDF", "—", "Génération des PDF (ordres de mission)"],
        ["Maatwebsite Excel / PhpSpreadsheet", "3.1", "Import agents et génération .xlsx Moov / exports"],
        ["Pest PHP", "—", "Tests (BDD/PHPUnit)"],
    ],
    largeurs=[6.0, 3.4, 7.4],
)

h2("2.3 Structure du projet")
code("anatt-ordre-mission/\n"
     "  frontend/                Application Vue 3 (42 écrans)\n"
     "    src/views/             Pages par module (auth/, agents/, missions/, paiements/...)\n"
     "    src/components/shared/  Composants réutilisables (AppTable, AppModal, AppBadge...)\n"
     "    src/stores/            Pinia (auth, missions, notifications)\n"
     "    src/composables/       Logique réutilisable (useApi, useAuth, useMissions...)\n"
     "    src/utils/permissions.ts  Miroir RBAC frontend (guards + directive v-permission)\n"
     "  backend/                 API Laravel 11\n"
     "    app/Http/Controllers/Api/  Contrôleurs REST\n"
     "    app/Models/            Modèles Eloquent\n"
     "    app/Services/          Logique métier (calcul, lots, barème, rapports, audit)\n"
     "    app/Services/Paiement/  Générateurs MTN (.txt) et Moov (.xlsx)\n"
     "    app/Support/Permissions  Source unique des clés de permission (RBAC)\n"
     "    app/Jobs/              Tâches asynchrones (génération de lots, états)\n"
     "  docs/                    Documentation (.docx + scripts générateurs)\n"
     "  CLAUDE.md                Contexte projet")

h2("2.4 Modèles de données")
h3("2.4.1 Entités principales")
tableau(
    ["Entité", "Table SQL", "Description"],
    [
        ["User", "users", "Compte de connexion (email, password, rôle, actif)."],
        ["Agent", "agents", "Bénéficiaire (matricule, catégorie, téléphone, opérateur MM)."],
        ["Mission", "missions", "Événement regroupant N ordres de mission."],
        ["OrdreMission", "ordres_mission", "1 enregistrement = 1 bénéficiaire (réf. SEMO unique)."],
        ["EtatPaiement", "etats_paiement", "État récapitulatif d'une mission (1 par mission) : circuit + signatures."],
        ["Versement", "versements", "Une tranche (AVANCE / RELIQUAT) d'un OM."],
        ["LotPaiement", "lots_paiement", "1 mission × 1 tranche × 1 opérateur = 1 fichier."],
        ["Bareme", "baremes", "Taux journalier par catégorie × zone × type."],
        ["ZoneGeographique", "zones_geographiques", "Référentiel des 4 zones (intérieur / extérieur)."],
        ["AuditLog", "audit_logs", "Journal horodaté des actions sensibles."],
    ],
    largeurs=[3.6, 4.0, 9.2],
)
h3("2.4.2 Champs clés — OrdreMission")
tableau(
    ["Champ", "Type", "Valeurs / règle"],
    [
        ["reference_semo", "string", "Clé naturelle UNIQUE (anti-doublon)."],
        ["role", "enum", "PARTICIPANT | CAV (le CAV est payé selon SA catégorie)."],
        ["categorie", "enum", "INDICE_SUP_800 | INDICE_400_799 | INDICE_INF_400 (reprise de SEMO)."],
        ["zone_code", "enum", "INTERIEUR | EXT_ZONE_I | EXT_ZONE_II | EXT_ZONE_III."],
        ["type_prise_en_charge", "enum", "UN_REPAS | DEUX_REPAS | JOURNEE_COMPLETE | LOGE_NOURRI | LOGE_SEUL | NOURRI_SEUL."],
        ["nb_jours", "int", "Décompte inclusif (règle des 12 h)."],
        ["montant_total", "int", "(per diem + transport) = taux journalier × nb_jours + transport (FCFA)."],
        ["avance / reliquat", "int", "avance = round(total × partAvance/100), 70 % par défaut (part paramétrable) ; reliquat = total − avance."],
        ["statut", "enum", "IMPORTE (retranscrit) → VALIDE (état des avances validé par le DG) → SOLDE. VERIFIE / VISA_DAF (circuit par OM en option), REJETE / ANNULE possibles."],
    ],
    largeurs=[4.2, 2.4, 10.2],
)
para("Le statut de l'OM ne transite pas par le circuit : il reste IMPORTE tout au long de la validation "
     "de l'état, et ne passe VALIDE (payable) qu'à la validation finale du DG.", italic=True, color=GRIS)

# =========================================================================
# 3. DESCRIPTION FONCTIONNELLE — MODULES
# =========================================================================
h1("3. Description fonctionnelle — modules")
para("L'application est organisée en modules fonctionnels. Chaque écran est "
     "accessible selon les permissions du profil connecté ; le menu s'adapte automatiquement.")
tableau(
    ["Module", "Profils concernés", "Fonction principale"],
    [
        ["Authentification", "Tous", "Connexion, mot de passe oublié, réinitialisation."],
        ["Tableau de bord", "Tous (vues filtrées)", "KPIs, graphiques, accès rapides."],
        ["Personnel (agents)", "GESTIONNAIRE_OM, ADMIN", "Référentiel agents, import, profil."],
        ["Ordres de mission", "Tous (selon droits)", "Retranscription, suivi, PDF de l'OM."],
        ["États de paiement", "GESTIONNAIRE_OM, CONTROLEUR, DAF, DG", "Récapitulatif, circuit de validation, PDF brouillon/final."],
        ["Paiements", "AGENT_FINANCIER", "Génération fichiers MTN/Moov, confirmation."],
        ["Barème per diem", "Tous (lecture) / ADMIN (édition)", "Taux par catégorie/zone/type."],
        ["Rapports", "Tous (rapports.consulter)", "États, exports Excel/PDF."],
        ["Audit", "ADMIN, CONTROLEUR, DAF, DG", "Journal d'activité système."],
        ["Administration", "ADMIN / SUPER_ADMIN", "Utilisateurs, rôles, signatures, paramètres, e-mail, sauvegardes."],
        ["Pages système", "Système", "403, 404, 500, maintenance."],
    ],
    largeurs=[4.2, 5.4, 7.2],
)

h2("3.1 Module Authentification")
para("Accès sécurisé par tokens Sanctum. Un changement de mot de passe peut être imposé à la première "
     "connexion (création/réinitialisation administrative) : l'accès aux autres écrans est alors bloqué.")
bullet("AUTH-01 — Connexion : email + mot de passe (message d'erreur neutre anti-énumération).")
bullet("AUTH-02 — Mot de passe oublié : envoi d'un lien de réinitialisation.")
bullet("AUTH-03 — Réinitialisation : nouveau mot de passe via token.")

h2("3.2 Module Tableau de bord")
para("DASH-01 — KPIs (OM en cours, validés, paiements du mois), graphiques et actions rapides, filtrés "
     "selon les droits de l'utilisateur.")

h2("3.3 Module Personnel (agents)")
para("Réservé en écriture aux profils disposant de « personnel.gerer ». Le référentiel agent fournit le "
     "téléphone et l'opérateur utilisés au paiement (jamais l'OM).")
bullet("PERS-01 — Liste : recherche (nom/matricule/IFU), filtres catégorie/opérateur/statut.")
bullet("PERS-02 — Fiche : informations complètes + historique des missions.")
bullet("PERS-03 — Création : la catégorie d'indice (INDICE_SUP_800 / INDICE_400_799 / INDICE_INF_400) est déduite de l'indice ou du salaire.")
bullet("PERS-04 — Modification ; PERS-05 — Mon profil ; PERS-06 — Import Excel/CSV.")

h2("3.4 Module Ordres de mission")
para("Les OM sont retranscrits depuis SEMO ; ils ne sont ni créés ni modifiés individuellement à la main "
     "(pas de PUT/DELETE unitaire). La validation ne se fait pas sur l'OM mais sur l'état de sa mission.")
bullet("Liste des missions : point d'entrée du circuit (N OM + 1 état par mission).")
bullet("Liste des OM : filtres statut/zone/opérateur/agent/dates, recherche par référence SEMO.")
bullet("Retranscription : assistant mission + N bénéficiaires (participants + CAV), per diem en temps réel.")
bullet("Détail d'un OM : mission, bénéficiaire, montants, versements ; renvoie vers l'état de la mission "
       "(aucune action de circuit sur l'OM). Brouillon PDF de l'OM et historique d'audit.")

h2("3.5 Module États de paiement")
para("Objet central du circuit de validation. DEUX états par mission — l'état des AVANCES puis l'état des "
     "RELIQUATS (unicité mission × tranche) ; chacun est contrôlé, visé par le DAF, puis validé par le "
     "Directeur Général. L'état des reliquats ne peut être établi qu'une fois celui des avances validé.")
bullet("File de travail : liste des états filtrable par statut et par tranche, ouverte sur la file de l'acteur.")
bullet("Détail de l'état : onglets Avances / Reliquats, montants de la tranche, montant en lettres, frise du "
       "circuit, numérotation manuelle, actions (contrôler / viser / valider / rejeter / rafraîchir).")
bullet("Brouillon PDF (filigrané, sans signature) puis état définitif PDF (signatures DAF + DG), paysage A4. "
       "Un « état général » consolidé (avances + reliquats) est disponible une fois les deux états validés.")
bullet("Signatures (SUPER_ADMIN) : identités et paraphes scannés des titulaires imprimés sur l'état.")

h2("3.6 Module Paiements")
para("Génère les fichiers de virement Mobile Money et assure le suivi des versements. Aucun appel aux API "
     "opérateurs, aucun réimport de fichier retour.")
bullet("PAY-01 — Liste des lots (GENERE/CONFIRME/ERREUR) + téléchargement.")
bullet("PAY-02 / PAY-03 — Génération Moov (.xlsx) / MTN (.txt) : mission + tranche → aperçu → fichier → confirmer.")
bullet("PAY-04 — Synthèse des versements par opérateur/statut.")
bullet("PAY-05 — Réconciliation : écran d'information (modèle manuel, aucun réimport).")
bullet("PAY-06 — Historique des versements confirmés (avec bénéficiaire) par période.")

# =========================================================================
# 4. WORKFLOWS DÉTAILLÉS
# =========================================================================
h1("4. Workflows détaillés")
para("Chaque workflow est présenté comme une suite d'étapes (acteur → action → résultat/statut).")

h2("4.1 Retranscription d'une mission et de ses OM")
para("Acteur : GESTIONNAIRE_OM (permission om.retranscrire).")
tableau(
    ["#", "Acteur", "Action", "Résultat / statut"],
    [
        ["1", "Gestionnaire", "Ouvre l'assistant « Nouvel ordre » et saisit la mission (objet, destination, zone déduite, dates, prise en charge tiers).", "Contexte de mission enregistré"],
        ["2", "Gestionnaire", "Ajoute chaque bénéficiaire (participant ou CAV) : référence SEMO, agent, type de prise en charge.", "Per diem calculé en temps réel par bénéficiaire"],
        ["3", "Système", "Contrôle l'unicité de chaque référence SEMO et la cohérence type ↔ zone.", "Doublons et incohérences signalés immédiatement"],
        ["4", "Gestionnaire", "Vérifie le récapitulatif (totaux avance/reliquat) puis enregistre.", "POST /api/missions : mission + N OM créés"],
        ["5", "Système", "Crée les OM au statut IMPORTE et leurs versements AVANCE/RELIQUAT (sauf exonération).", "OM prêts pour contrôle"],
    ],
    largeurs=[0.8, 2.6, 9.0, 4.4],
)

h2("4.2 Circuit de validation de l'état de paiement")
para("L'unité validée est l'état de paiement d'une TRANCHE de la mission, et non les OM pris séparément. "
     "Le circuit ci-dessous est parcouru deux fois : d'abord pour l'état des AVANCES, puis — une fois "
     "celui-ci validé — pour l'état des RELIQUATS. Un seul geste par étape vaut pour tous les bénéficiaires.")
tableau(
    ["#", "Acteur", "Action", "Résultat / statut"],
    [
        ["1", "Gestionnaire", "Établit le récapitulatif de la mission (POST .../etat/recapituler, om.retranscrire).", "État BROUILLON, brouillon PDF téléchargeable"],
        ["2", "Contrôleur", "Contrôle de conformité (POST /api/etats/:id/controler, om.valider).", "État → CONTROLE, signature contrôleur"],
        ["3", "DAF", "Appose le visa « Vu » (POST /api/etats/:id/viser, om.viser).", "État → VISA_DAF, signature DAF"],
        ["4", "DAF ou DG", "Saisit le numéro de l'état (PUT /api/etats/:id/numero, om.numeroter).", "Numéro requis avant la validation finale"],
        ["5", "Directeur Général", "Validation finale (POST /api/etats/:id/approuver, om.approuver).", "État → VALIDE. Avances : OM → VALIDE (payables) ; reliquats : fichiers de reliquat ouverts"],
    ],
    largeurs=[0.8, 2.6, 8.4, 5.0],
)
encadre("Séparation des tâches",
        "Quand elle est active (par défaut), les trois signataires d'un état doivent être distincts : "
        "viser est refusé au contrôleur, et valider est refusé au contrôleur comme au DAF. Un compte "
        "cumulant plusieurs droits (ex. super administrateur) ne peut pas enchaîner deux étapes du même "
        "dossier. Même règle sur le circuit par OM.", fond="FBF3E0", couleur_titre=OR)
para("Rejet (POST /api/etats/:id/rejeter, om.rejeter) : possible aux étapes BROUILLON / CONTROLE / "
     "VISA_DAF avec un motif ≥ 10 caractères. Il efface les signatures amont et remet l'état en "
     "correction ; les OM gardent leur statut. Le récapitulatif peut ensuite être refait (REJETE → BROUILLON).")
encadre("Deux barrières avant paiement",
        "1) Le statut de l'OM ne passe VALIDE qu'à la validation de l'état des AVANCES (propagation). "
        "2) La génération d'un fichier vérifie en outre que l'état DE SA TRANCHE est VALIDE : la validation "
        "des avances ouvre les fichiers d'avance, celle des reliquats ouvre les fichiers de reliquat. Un "
        "versement dont l'état de tranche n'est pas validé n'apparaît pas parmi les bénéficiaires éligibles.")
para("Circuit OM par OM (reprise de données) : les routes /api/ordres/:id/valider|viser|approuver|rejeter "
     "subsistent pour corriger une mission dépourvue d'état ; elles sont refusées dès qu'un état existe.",
     italic=True, color=GRIS)

h2("4.3 Génération et confirmation des paiements")
para("Acteur : AGENT_FINANCIER (permissions paiements.generer / paiements.confirmer).")
tableau(
    ["#", "Acteur", "Action", "Résultat / statut"],
    [
        ["1", "Agent financier", "Choisit mission + tranche (AVANCE/RELIQUAT) + opérateur, puis « Prévisualiser ».", "Liste des bénéficiaires éligibles (OM VALIDE, non déjà confirmés)"],
        ["2", "Agent financier", "Clique « Générer le fichier ».", ".txt (MTN) ou .xlsx (Moov) produit ; lot → GENERE ; versements → GENERE"],
        ["3", "Système", "Petit lot : téléchargement direct. Gros lot : traitement en file (202 + tracking_id).", "Fichier disponible / suivi de job"],
        ["4", "Agent financier", "Dépose le fichier sur la plateforme de l'opérateur (hors application).", "Virements exécutés côté opérateur"],
        ["5", "Agent financier", "Revient dans l'application et confirme le lot (statut GENERE requis).", "Lot + versements → CONFIRME (horodatés) ; OM soldé → SOLDE"],
        ["6", "Agent financier", "En cas de problème : marque le lot « en erreur », puis régénère.", "Lot → ERREUR → (régénération) → GENERE avant toute confirmation"],
    ],
    largeurs=[0.8, 2.6, 7.6, 5.8],
)
encadre("Idempotence & confirmation",
        "Un versement CONFIRME n'est jamais réinclus dans un nouveau fichier (protection anti-double-paiement). "
        "La régénération n'est possible que tant que le lot n'est pas confirmé. Seul un lot au statut GENERE "
        "peut être confirmé : un lot en ERREUR doit d'abord être régénéré (on ne confirme pas un fichier invalidé).")

h2("4.4 Génération de l'ordre de mission en PDF")
tableau(
    ["#", "Acteur", "Action", "Résultat / statut"],
    [
        ["1", "Utilisateur", "Ouvre la fiche d'un OM et clique « Aperçu/Télécharger PDF ».", "GET /api/ordres/:id/pdf"],
        ["2", "Système", "Génère le PDF via DomPDF (en-tête officiel ANaTT mutualisé, données OM, montants).", "PDF produit côté serveur"],
        ["3", "Utilisateur", "Visualise ou télécharge le document.", "Fichier PDF imprimable"],
    ],
    largeurs=[0.8, 2.6, 8.6, 4.8],
)

h2("4.5 Import en masse des agents (Excel / CSV)")
tableau(
    ["#", "Acteur", "Action", "Résultat / statut"],
    [
        ["1", "Gestionnaire", "Dépose un fichier Excel/CSV et lance la prévisualisation.", "Aperçu des données"],
        ["2", "Système", "Valide chaque ligne (unicité matricule/téléphone, cohérence numéro/opérateur).", "Rapport d'erreurs ligne à ligne"],
        ["3", "Gestionnaire", "Confirme l'import.", "Import « tout ou rien » (rollback complet à la moindre erreur)"],
    ],
    largeurs=[0.8, 2.6, 8.6, 4.8],
)

# =========================================================================
# 5. RÈGLES MÉTIER
# =========================================================================
h1("5. Règles métier")

h2("5.1 Calcul du per diem")
para("Montant total = taux journalier (catégorie × zone × type de prise en charge) × nombre de jours.", bold=True)
bullet("Nombre de jours : décompte inclusif des dates ; règle des 12 h (période ≥ 12 h = jour entier ; retour 0-12 h = rien).")
bullet("Avance = round(montant total × partAvance/100), 70 % par défaut ; Reliquat = montant total − avance. "
       "La part de l'avance est paramétrable (SUPER_ADMIN) et s'applique aux OM retranscrits ensuite.")
bullet("Exonération (prise en charge par un tiers) : tous les montants à 0, aucun versement créé.")
bullet("4 zones : INTERIEUR, EXT_ZONE_I, EXT_ZONE_II, EXT_ZONE_III (II et III ont des taux identiques).")
bullet("Contrainte type ↔ zone : repas (un/deux) ⇒ INTERIEUR uniquement ; hébergement (logé/nourri) ⇒ EXTÉRIEUR uniquement.")

h2("5.2 Catégories d'indice (reprises de SEMO)")
tableau(
    ["Catégorie", "Indice", "Salaire brut (formule de secours : indice = salaire × 12 / 3097)"],
    [
        ["INDICE_SUP_800", "≥ 800", "≥ 372 000 FCFA"],
        ["INDICE_400_799", "400 – 799", "186 000 – 372 000 FCFA"],
        ["INDICE_INF_400", "< 400", "< 186 000 FCFA"],
    ],
    largeurs=[3.2, 3.0, 10.6],
)

h2("5.3 Règles de validation principales")
tableau(
    ["Règle", "Description", "Contrôle"],
    [
        ["Référence SEMO unique", "Chaque OM a une référence SEMO unique (anti-doublon).", "API / BDD"],
        ["Origine SEMO", "Les OM sont retranscrits (statut initial IMPORTE), jamais créés à la main.", "API"],
        ["Circuit par tranche", "Chaque état (avances puis reliquats) passe BROUILLON → CONTROLE → VISA_DAF → VALIDE ; rejet motivé (≥ 10 car.).", "API"],
        ["Reliquats après avances", "L'état des reliquats n'est établi qu'une fois l'état des avances validé (422 sinon).", "API"],
        ["Séparation des tâches", "Trois signataires distincts : contrôleur ≠ DAF ≠ DG (option activée par défaut).", "API"],
        ["Numéro requis", "La validation finale du DG exige un numéro d'état (registre de l'agence).", "API"],
        ["Type ↔ zone", "Repas ⇒ INTERIEUR ; hébergement ⇒ EXTÉRIEUR.", "Formulaire + API"],
        ["Téléphone/opérateur", "Issus du référentiel agent ; cohérence numéro ↔ opérateur.", "API"],
        ["Payable si tranche VALIDE", "Un versement n'est payable que si l'état de SA tranche est validé par le DG.", "Paiement"],
        ["Confirmation d'un lot GENERE", "Seul un lot généré se confirme ; un lot en erreur est régénéré d'abord.", "Paiement"],
        ["Idempotence paiement", "Un versement CONFIRME n'est jamais réinclus.", "Paiement"],
    ],
    largeurs=[3.6, 9.6, 3.6],
)

# =========================================================================
# 6. SÉCURITÉ ET GESTION DES ACCÈS
# =========================================================================
h1("6. Sécurité et gestion des accès")

h2("6.1 Modèle d'autorisation (RBAC par permission)")
para("L'autorisation s'appuie sur des clés de permission, et non sur la hiérarchie. Les Gates sont définies "
     "dans AuthServiceProvider (source : App\\Support\\Permissions) ; le middleware permission: protège les "
     "routes ; Gate::before accorde au SUPER_ADMIN un accès implicite à tout.")

h2("6.2 Matrice des permissions")
para("Légende : ✔ = autorisé. Le SUPER_ADMIN dispose implicitement de toutes les permissions.")
P = ["ADMIN", "GEST.", "FIN.", "CONTR.", "DAF", "DG", "LECT."]
def row(nom, a, g, f, c, daf, dg, l):
    conv = lambda x: "✔" if x else ""
    return [nom, conv(a), conv(g), conv(f), conv(c), conv(daf), conv(dg), conv(l)]
tableau(
    ["Permission"] + P,
    [
        #                        ADMIN GEST FIN CONTR DAF DG LECT
        row("dashboard.consulter",   1, 1, 1, 1, 1, 1, 1),
        row("personnel.consulter",   1, 1, 1, 1, 1, 1, 0),
        row("personnel.gerer",       1, 1, 0, 0, 0, 0, 0),
        row("om.consulter",          1, 1, 1, 1, 1, 1, 1),
        row("om.retranscrire",       0, 1, 0, 0, 0, 0, 0),
        row("om.valider",            0, 0, 0, 1, 0, 0, 0),
        row("om.viser",              0, 0, 0, 0, 1, 0, 0),
        row("om.approuver",          0, 0, 0, 0, 0, 1, 0),
        row("om.numeroter",          0, 0, 0, 1, 1, 1, 0),
        row("om.rejeter",            0, 0, 0, 1, 1, 1, 0),
        row("paiements.consulter",   1, 1, 1, 1, 1, 1, 0),
        row("paiements.generer",     0, 0, 1, 0, 0, 0, 0),
        row("paiements.confirmer",   0, 0, 1, 0, 0, 0, 0),
        row("baremes.consulter",     1, 1, 1, 1, 1, 1, 0),
        row("baremes.modifier",      1, 0, 0, 0, 0, 0, 0),
        row("rapports.consulter",    1, 1, 1, 1, 1, 1, 1),
        row("audit.consulter",       1, 0, 0, 1, 1, 1, 0),
        row("utilisateurs.gerer",    1, 0, 0, 0, 0, 0, 0),
    ],
    largeurs=[4.8] + [1.55] * 7,
)
para("Permissions réservées au SUPER_ADMIN : superadmins.gerer, parametres.gerer.", italic=True, color=GRIS)

h2("6.3 Mesures de sécurité applicatives")
bullet("Authentification : tokens Laravel Sanctum (Bearer) ; révocation immédiate des sessions à la désactivation, suppression, changement de rôle ou réinitialisation de mot de passe.")
bullet("Mots de passe : hachage bcrypt (12 tours) ; changement imposé (verrou HTTP 423) après création/réinitialisation administrative.")
bullet("Anti-énumération : message de connexion neutre et identique quelle que soit la cause de l'échec.")
bullet("Rate limiting dédié : login, API globale, génération de paiement et exports (throttle).")
bullet("Protection XSS : échappement Vue + Content Security Policy durcie (anti-vol de token).")
bullet("Injection de formule neutralisée dans les exports tableurs ; garde anti-SSRF sur la génération PDF.")
bullet("Secret SMTP chiffré au repos et jamais réaffiché en clair (masque ********).")
bullet("Super admin protégé : il existe toujours ≥ 1 SUPER_ADMIN actif ; le dernier ne peut être supprimé, désactivé ni rétrogradé.")
bullet("Injection SQL : prévenue par l'ORM Eloquent (requêtes préparées).")

h2("6.4 Audit et traçabilité")
para("Les actions sensibles sont enregistrées dans audit_logs :")
tableau(
    ["Champ", "Description"],
    [
        ["user_id", "Auteur de l'action (null pour un événement système)."],
        ["action", "Type d'action : LOGIN ; IMPORT_OM / VERIFY_OM / VALIDATE_OM / VISA_DAF_OM / APPROVE_DG_OM / REJECT_OM ; "
                   "RECAP_ETAT / NUMERO_ETAT / CONTROL_ETAT / VISA_DAF_ETAT / APPROVE_DG_ETAT / REJECT_ETAT ; "
                   "GENERATE_LOT / CONFIRM_LOT / ERROR_LOT ; CREATE/UPDATE/DELETE_BAREME ; *_USER."],
        ["entite / entite_id", "Entité concernée (session, ordre_mission, etat_paiement, lot_paiement, bareme, user) et son id."],
        ["donnees", "Contexte de l'action (JSON) : montants, tranche, ancien/nouveau taux, etc."],
        ["ip_address", "Adresse IP d'origine de la requête."],
        ["created_at", "Horodatage précis (table sans updated_at)."],
    ],
    largeurs=[4.6, 12.2],
)
para("Consultation (ADMIN / CONTROLEUR) : filtres par action, par entité et par plage de dates (Du / Au).",
     italic=True, color=GRIS)

# =========================================================================
# 7. RÉFÉRENCE API
# =========================================================================
h1("7. Référence API")

h2("7.1 Base et authentification")
encadre("Conventions",
        "Préfixe : /api. Authentification : header Authorization: Bearer {token}. Format JSON. "
        "Autorisation par middleware permission:{clé}. Throttling sur login, API, paiement et export.",
        fond="EDFAF2")

h2("7.2 Authentification & compte")
tableau(
    ["Méthode", "URL", "Description"],
    [
        ["POST", "/api/auth/login", "Connexion → { token, user, permissions[] }"],
        ["POST", "/api/auth/logout", "Déconnexion (révoque le token)"],
        ["GET", "/api/auth/me", "Utilisateur connecté"],
        ["GET", "/api/auth/me/permissions", "Permissions effectives (pilote l'UI)"],
        ["POST", "/api/auth/mot-de-passe/changer", "Changement de mot de passe par l'utilisateur"],
        ["POST", "/api/auth/mot-de-passe/email | reset", "Lien de réinitialisation / réinitialisation"],
    ],
    largeurs=[2.0, 7.6, 7.2],
)

h2("7.3 Agents, missions & ordres de mission")
tableau(
    ["Méthode", "URL", "Description"],
    [
        ["GET", "/api/agents", "Liste filtrée (search, catégorie, opérateur, actif)"],
        ["POST", "/api/agents | /import", "Création / import (personnel.gerer)"],
        ["POST", "/api/missions", "Retranscription : mission + N OM (om.retranscrire)"],
        ["GET", "/api/missions", "Liste des missions avec l'avancement de leur état (om.consulter)"],
        ["GET", "/api/ordres | /ordres/:id", "Liste filtrée / détail d'un OM"],
        ["GET", "/api/ordres/:id/pdf | /pdf/download | /pdf/brouillon", "PDF officiel / brouillon de l'OM"],
        ["GET", "/api/ordres/:id/historique", "Journal d'audit de l'OM"],
    ],
    largeurs=[2.0, 7.6, 7.2],
)

h3("7.3.1 État de paiement (circuit de validation)")
tableau(
    ["Méthode", "URL", "Description"],
    [
        ["GET", "/api/etats", "File de travail : états filtrables par statut et tranche (om.consulter)"],
        ["GET", "/api/missions/:id/etat", "États de la mission par tranche : { avance, reliquat } (null si aucun)"],
        ["POST", "/api/missions/:id/etat/recapituler", "Crée / rafraîchit l'état d'une tranche en BROUILLON — body { tranche? } ; reliquat : 422 si avances non validées (om.retranscrire)"],
        ["GET", "/api/missions/:id/etat/brouillon | /telecharger", "PDF de la tranche (?tranche=) — filigrané tant que non validé"],
        ["GET", "/api/missions/:id/etat/final", "État général consolidé (avances + reliquats), une fois les deux validés"],
        ["PUT", "/api/etats/:id/numero", "Numérotation manuelle (om.numeroter)"],
        ["POST", "/api/etats/:id/controler", "BROUILLON → CONTROLE (om.valider)"],
        ["POST", "/api/etats/:id/viser", "CONTROLE → VISA_DAF ; DAF ≠ contrôleur (om.viser)"],
        ["POST", "/api/etats/:id/approuver", "VISA_DAF → VALIDE ; DG ≠ contrôleur/DAF ; ouvre la tranche (om.approuver)"],
        ["POST", "/api/etats/:id/rejeter", "→ REJETE, motif ≥ 10 car. (om.rejeter)"],
    ],
    largeurs=[2.0, 7.8, 7.0],
)
para("Signatures des titulaires (SUPER_ADMIN, parametres.gerer) : GET/POST/DELETE /api/admin/signatures/:poste "
     "(controleur | daf | dg) et identités dans /api/admin/settings.", italic=True, color=GRIS)

h2("7.4 Paiements (génération)")
tableau(
    ["Méthode", "URL", "Description"],
    [
        ["GET", "/api/paiements/lots", "Liste des lots (mission/tranche/opérateur/statut)"],
        ["GET", "/api/paiements/lots/previsualiser", "Bénéficiaires éligibles (état de la tranche VALIDE)"],
        ["POST", "/api/paiements/lots/generer", ".txt MTN / .xlsx Moov ; petit lot direct, gros lot 202 + tracking_id"],
        ["GET", "/api/paiements/lots/:lot/download", "Re-téléchargement du fichier"],
        ["POST", "/api/paiements/lots/:lot/confirmer", "Lot GENERE + versements → CONFIRME"],
        ["POST", "/api/paiements/lots/:lot/erreur", "Lot → ERREUR (régénération autorisée)"],
        ["GET", "/api/paiements/statuts | /historique", "Synthèse / historique confirmés"],
    ],
    largeurs=[2.0, 7.8, 7.0],
)

h2("7.5 Barème, rapports, audit & administration")
tableau(
    ["Méthode", "URL", "Description"],
    [
        ["GET / PUT", "/api/baremes[/:id]", "Lecture (tous) / modification (baremes.modifier) ; édition II ↔ III synchronisée"],
        ["GET", "/api/admin/parametres/part-avance", "Part de l'avance (lecture publique, tout authentifié)"],
        ["GET", "/api/rapports/{mensuel|agents|operateurs|zones|imputations}", "États (rapports.consulter)"],
        ["GET / POST", "/api/rapports/export | export-async", "Export xlsx/pdf (immédiat ou en file)"],
        ["GET", "/api/audit[/:id]", "Journal d'activité (audit.consulter)"],
        ["—", "/api/admin/users...", "Gestion des comptes (utilisateurs.gerer)"],
        ["—", "/api/admin/{settings|backups}", "Paramètres & sauvegardes (parametres.gerer)"],
    ],
    largeurs=[2.4, 8.0, 6.4],
)

h2("7.6 Codes de réponse HTTP")
tableau(
    ["Code", "Signification", "Cas d'usage"],
    [
        ["200 / 201 / 204", "Succès / créé / sans contenu", "GET-PUT / POST création / suppression"],
        ["202 Accepted", "Traitement asynchrone", "Génération de gros lot / export (tracking_id)"],
        ["401 / 403", "Non authentifié / interdit", "Token absent ou expiré / permission insuffisante"],
        ["404", "Introuvable", "Ressource inexistante"],
        ["422", "Erreur de validation", "Champ requis manquant, données invalides"],
        ["423 Locked", "Changement de mot de passe requis", "Compte en must_change_password"],
        ["429", "Trop de requêtes", "Dépassement du rate limiting"],
        ["500", "Erreur serveur", "Erreur interne inattendue"],
    ],
    largeurs=[3.2, 5.2, 8.4],
)

# =========================================================================
# 8. INTÉGRATIONS MOBILE MONEY
# =========================================================================
h1("8. Intégrations Mobile Money (formats de fichiers)")
para("L'application produit des fichiers à déposer manuellement sur les plateformes opérateurs. Le numéro "
     "est normalisé au format international (229 + numéro national à 10 chiffres) ; le libellé est en "
     "majuscules. Un lot = 1 mission × 1 tranche × 1 opérateur.")

h2("8.1 MTN Bénin — fichier .txt")
bullet("Texte ASCII, séparateur VIRGULE, fins de ligne CRLF (\\r\\n), saut de ligne final.")
bullet("En-tête : HDR,{COMPTE},{NB_LIGNES},{MONTANT_TOTAL} (compte en configuration).")
bullet("Lignes de détail : {seq},MSISDN,{229+tel},{montant},{libellé MAJUSCULES}.")
bullet("Contrôle d'intégrité : NB_LIGNES et MONTANT_TOTAL de l'en-tête = détail recalculé.")
code("HDR,SARALY,1,45000\n"
     "1,MSISDN,2290167267455,45000,DADA RICHARD ATELIER REGIONAL")

h2("8.2 Moov Africa — fichier .xlsx")
bullet("Feuille « Sheet1 ». Ligne 1 : en-tête fixe de 9 colonnes.")
bullet("Receiver Identifier Type | IdentifierValue | Validation KYC | Validation KYC value | PayerAccountName | AccountName | Amount | Comment | VoucherSecretCode")
bullet("Par bénéficiaire : A=MSISDN, B=229+téléphone EN TEXTE, C-F vides, G=montant (numérique), H=libellé MAJUSCULES, I vide.")
encadre("Pourquoi le téléphone en texte",
        "La colonne B est forcée en TEXTE pour éviter la notation scientifique et la perte du préfixe ; "
        "le montant (G) reste numérique.")

# =========================================================================
# 9. ENVIRONNEMENTS ET DÉPLOIEMENT
# =========================================================================
h1("9. Environnements et déploiement")

h2("9.1 Environnements")
tableau(
    ["Environnement", "Usage", "Base de données"],
    [
        ["Développement (local)", "WampServer ou Docker Compose — données de test", "MySQL local / SQLite (tests)"],
        ["Préproduction", "Recette et validation utilisateurs", "MySQL préproduction"],
        ["Production", "Usage opérationnel ANaTT", "MySQL production sécurisée"],
    ],
    largeurs=[4.6, 7.0, 5.2],
)

h2("9.2 Variables d'environnement critiques")
tableau(
    ["Variable", "Description"],
    [
        ["APP_ENV / APP_DEBUG / APP_URL", "Environnement, débogage, URL de base de l'API"],
        ["DB_DATABASE / DB_USERNAME / DB_PASSWORD", "Connexion MySQL"],
        ["CACHE_STORE / SESSION_DRIVER / QUEUE_CONNECTION", "« database » pour fonctionner sans Redis"],
        ["SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD", "Super admin initial (mot de passe OBLIGATOIRE en production)"],
        ["MAIL_MAILER / MAIL_HOST / MAIL_FROM_ADDRESS", "Configuration SMTP"],
        ["SANCTUM_STATEFUL_DOMAINS", "Domaines SPA autorisés"],
    ],
    largeurs=[7.2, 9.6],
)
encadre("Configuration runtime du frontend",
        "L'URL de l'API est éditable APRÈS build dans frontend/dist/runtime-config.js "
        "(window.__ANATT_CONFIG__.apiUrl), sans recompiler.")

h2("9.3 Commandes de démarrage")
code("# Backend (Laravel)\n"
     "composer install --no-dev --optimize-autoloader\n"
     "php artisan key:generate\n"
     "php artisan migrate --seed\n"
     "php artisan storage:link\n"
     "php artisan config:cache && php artisan route:cache\n"
     "php artisan queue:work --tries=3   # générations asynchrones (lots, états)\n\n"
     "# Frontend (Vue)\n"
     "cd frontend && npm install && npm run build\n\n"
     "# Docker (alternative dev)\n"
     "docker-compose up -d")

h2("9.4 Comptes de démonstration (local uniquement)")
para("En environnement local/testing, le seeder crée un compte par profil (mot de passe « password ») : "
     "superadmin@, admin@, gestion@, finance@, controle@, lecteur@anatt.bj. Ces comptes ne sont JAMAIS "
     "créés en production.", italic=True, color=GRIS)

# =========================================================================
# ANNEXE — BARÈME
# =========================================================================
doc.add_page_break()
h1("Annexe A — Barème officiel (FCFA / jour)")
h2("Zone INTERIEUR")
tableau(["Catégorie", "Un repas", "Deux repas", "Journée complète"],
        [["INDICE_SUP_800", "15 000", "30 000", "45 000"],
         ["INDICE_400_799", "10 000", "20 000", "35 000"],
         ["INDICE_INF_400", "8 500", "15 000", "30 000"]],
        largeurs=[4.2, 4.2, 4.2, 4.2])
h2("Zone EXT_ZONE_I")
tableau(["Catégorie", "Journée complète", "Logé+nourri", "Logé seul", "Nourri seul"],
        [["INDICE_SUP_800", "175 000", "60 000", "90 000", "120 000"],
         ["INDICE_400_799", "140 000", "50 000", "70 000", "95 000"],
         ["INDICE_INF_400", "105 000", "35 000", "55 000", "70 000"]],
        largeurs=[3.3, 3.5, 3.3, 3.3, 3.3])
h2("Zones EXT_ZONE_II et EXT_ZONE_III (taux identiques, synchronisés à l'édition)")
tableau(["Catégorie", "Journée complète", "Logé+nourri", "Logé seul", "Nourri seul"],
        [["INDICE_SUP_800", "105 000", "35 000", "55 000", "70 000"],
         ["INDICE_400_799", "85 000", "30 000", "45 000", "60 000"],
         ["INDICE_INF_400", "65 000", "25 000", "35 000", "45 000"]],
        largeurs=[3.3, 3.5, 3.3, 3.3, 3.3])
para("Note : barème par défaut, ajustable par l'ADMIN dans le module Barème.", italic=True, color=GRIS)

# =========================================================================
# ANNEXE — GLOSSAIRE
# =========================================================================
doc.add_page_break()
h1("Annexe B — Glossaire")
tableau(["Terme", "Définition"],
        [["ANaTT", "Agence Nationale des Transports Terrestres — République du Bénin."],
         ["SEMO", "Service émetteur des ordres de mission (hors application)."],
         ["OM", "Ordre de mission : 1 enregistrement = 1 bénéficiaire (réf. SEMO unique)."],
         ["Mission", "Événement regroupant N ordres de mission."],
         ["CAV", "Conducteur de véhicule administratif, payé selon sa propre catégorie."],
         ["Per diem", "Indemnité journalière de mission (taux × nb jours)."],
         ["Avance / Reliquat", "Tranches du per diem : 70 % puis 30 %."],
         ["État de paiement", "Document récapitulatif d'une mission (1 par mission) portant le circuit et les signatures."],
         ["DAF", "Directeur de l'Administration et des Finances : 2e signature (visa « Vu »)."],
         ["DG", "Directeur Général : 3e et dernière signature ; sa validation ouvre le paiement."],
         ["Versement", "Une tranche (AVANCE/RELIQUAT) d'un OM pour un opérateur."],
         ["Lot de paiement", "1 mission × 1 tranche × 1 opérateur = 1 fichier généré."],
         ["MSISDN", "Numéro au format international (229 + numéro national)."],
         ["Exonération", "Prise en charge par un tiers : aucun frais alloué."],
         ["RBAC", "Contrôle d'accès par rôle/permission (Sanctum + Gates)."],
         ["Sanctum", "Module Laravel d'authentification API par tokens."],
         ["FCFA", "Franc CFA — monnaie de la République du Bénin."]],
        largeurs=[4.2, 12.6])

doc.add_paragraph()
flag_bar()
fin = doc.add_paragraph(); fin.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = fin.add_run("Document élaboré par la Direction des Systèmes d'Information — ANaTT")
r.font.size = Pt(9); r.font.color.rgb = GRIS
fin2 = doc.add_paragraph(); fin2.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = fin2.add_run("© 2026 ANaTT — Agence Nationale des Transports Terrestres — Bénin")
r.font.size = Pt(9); r.font.color.rgb = GRIS

doc.save(SORTIE)
print("Documentation technique générée :", SORTIE)
