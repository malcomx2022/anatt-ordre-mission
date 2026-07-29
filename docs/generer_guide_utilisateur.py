# -*- coding: utf-8 -*-
"""
Génère le Guide Utilisateur ANaTT (.docx) à partir du contenu ci-dessous.

Usage :
    python docs/generer_guide_utilisateur.py
Dépendance :
    pip install python-docx

Le contenu reflète l'implémentation réelle de l'application (rôles, workflows,
barème, statuts). À régénérer après toute évolution fonctionnelle majeure.
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
SORTIE = os.path.join(ICI, "Guide-Utilisateur-ANaTT.docx")

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


def encadre(titre, t):
    """Petit encadré coloré (note / attention)."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = tbl.rows[0].cells[0]
    shade(cell, "EDFAF2")
    cell.width = Cm(16.8)
    p = cell.paragraphs[0]
    r = p.add_run(titre + " : ")
    r.bold = True
    r.font.color.rgb = VERT_FONCE
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
            set_cell_text(cells[j], str(val), size=10)
    if largeurs:
        for row in t.rows:
            for j, w in enumerate(largeurs):
                row.cells[j].width = Cm(w)
    doc.add_paragraph()
    return t


def toc():
    """Champ Sommaire Word (se met à jour à l'ouverture : F9 / Mettre à jour les champs)."""
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
r = t.add_run("Guide Utilisateur"); r.bold = True; r.font.size = Pt(30); r.font.color.rgb = VERT

s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run("Système de Gestion des Ordres de Mission"); r.font.size = Pt(15); r.font.color.rgb = GRIS

s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run("ANaTT — Agence Nationale des Transports Terrestres\nRépublique du Bénin")
r.font.size = Pt(12); r.font.color.rgb = GRIS

for _ in range(6):
    doc.add_paragraph()
m = doc.add_paragraph(); m.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = m.add_run("Version 1.1.0  •  Juillet 2026"); r.bold = True; r.font.color.rgb = OR
m = doc.add_paragraph(); m.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = m.add_run("Document destiné aux utilisateurs de l'application"); r.italic = True; r.font.size = Pt(10); r.font.color.rgb = GRIS
doc.add_page_break()

# =========================================================================
# SOMMAIRE
# =========================================================================
h1("Sommaire")
toc()
doc.add_page_break()

# =========================================================================
# 1. PRÉSENTATION
# =========================================================================
h1("1. Présentation de l'application")
para("L'application ANaTT de gestion des ordres de mission permet de retranscrire les ordres de "
     "mission (OM) émis par SEMO, de calculer les indemnités (per diem) selon le barème officiel, "
     "puis de générer les fichiers de paiement Mobile Money à déposer sur les plateformes MTN et Moov.")
encadre("Périmètre important",
        "L'application NE crée PAS les ordres de mission (c'est SEMO qui les émet) et NE réalise PAS "
        "les paiements (effectués sur les plateformes MTN / Moov). Elle RETRANSCRIT les OM puis GÉNÈRE "
        "les fichiers de paiement. La confirmation d'un paiement est manuelle, après dépôt réussi.")

h2("1.1 Cycle de vie en bref")
para("Le parcours complet d'un dossier suit toujours la même séquence :")
etape("SEMO émet les ordres de mission (hors application).")
etape("Un gestionnaire retranscrit les OM dans l'application, regroupés par mission.")
etape("Le système calcule le per diem (catégorie × zone × type de prise en charge × nombre de jours).")
etape("Le gestionnaire établit le récapitulatif : un état de paiement (brouillon) est produit pour la mission.")
etape("Le contrôleur contrôle l'état ; le DAF y appose son visa ; le Directeur Général le valide en dernier.")
etape("La validation du DG arrête l'état et rend payables tous les ordres de mission de la mission.")
etape("Un agent financier génère les fichiers de paiement : d'abord l'AVANCE, puis le RELIQUAT (.txt MTN ou .xlsx Moov).")
etape("Chaque fichier est déposé manuellement sur la plateforme de l'opérateur, puis confirmé dans l'application.")
encadre("Ce qui est validé, c'est l'état de la mission",
        "La validation ne se fait pas ordre par ordre. Chaque mission possède UN état de paiement "
        "récapitulatif ; c'est lui qui passe le circuit contrôle → visa DAF → validation DG. Valider "
        "l'état valide d'un coup tous les OM de la mission. Les OM, eux, conservent le statut sous lequel "
        "ils ont été retranscrits depuis SEMO jusqu'à cette validation finale.")

h2("1.2 Notions clés")
bullet("Mission : un événement (atelier, supervision…) qui regroupe plusieurs ordres de mission.")
bullet("Ordre de mission (OM) : 1 enregistrement = 1 bénéficiaire. Sa référence SEMO est unique.")
bullet("État de paiement : le document récapitulatif d'une mission (un seul par mission), numéroté à la "
       "main, qui porte le circuit de validation et les signatures du DAF et du Directeur Général.")
bullet("CAV (conducteur de véhicule administratif) : bénéficiaire à part entière, payé selon SA catégorie.")
bullet("Per diem : indemnité journalière, répartie en AVANCE (70 %) et RELIQUAT (30 %).")
bullet("Versement : une tranche (AVANCE ou RELIQUAT) d'un OM, pour un opérateur donné.")
bullet("Lot de paiement : 1 mission × 1 tranche × 1 opérateur = 1 fichier généré.")

# =========================================================================
# 2. RÔLES ET PERMISSIONS
# =========================================================================
h1("2. Rôles et accès")
para("Chaque utilisateur possède un profil qui détermine ce qu'il peut voir et faire. Le menu et les "
     "boutons s'adaptent automatiquement aux droits de l'utilisateur connecté.")
tableau(
    ["Profil", "Rôle principal"],
    [
        ["SUPER_ADMIN", "Accès total. Seul à gérer les super administrateurs et les paramètres système."],
        ["ADMIN", "Utilisateurs (hors super admins), référentiels, barème, audit. Supervise sans exécuter les paiements."],
        ["GESTIONNAIRE_OM", "Retranscrit et gère les missions, les OM et les agents ; établit le récapitulatif de l'état."],
        ["AGENT_FINANCIER", "Génère les fichiers MTN/Moov et confirme les paiements."],
        ["CONTROLEUR", "Étape 1 du circuit : contrôle de conformité de l'état de paiement ; consulte paiements et audit."],
        ["DAF", "Étape 2 : appose le visa « Vu » sur l'état de paiement (Directeur de l'Administration et des Finances)."],
        ["DG", "Étape 3 et dernière : valide définitivement l'état, ce qui ouvre le paiement (Directeur Général)."],
        ["LECTEUR", "Lecture seule (tableau de bord, OM, rapports)."],
    ],
    largeurs=[4.2, 12.6],
)
encadre("Séparation des tâches",
        "Le DAF et le DG autorisent, l'agent financier exécute : ni le DAF ni le DG ne génèrent ni ne "
        "confirment les paiements. Une même personne ne peut pas apposer à la fois le visa DAF et la "
        "validation finale du même état.")

h2("2.1 Matrice détaillée des permissions")
para("Légende : ✔ = autorisé. Le SUPER_ADMIN dispose implicitement de toutes les permissions.")
P = ["ADMIN", "GEST.", "FINANC.", "CONTR.", "DAF", "DG", "LECT."]
def row(nom, a, g, f, c, daf, dg, l):
    conv = lambda x: "✔" if x else ""
    return [nom, conv(a), conv(g), conv(f), conv(c), conv(daf), conv(dg), conv(l)]
tableau(
    ["Permission"] + P,
    [
        #      ADMIN GEST FIN CONTR DAF DG LECT
        row("Tableau de bord",        1, 1, 1, 1, 1, 1, 1),
        row("Personnel — consulter",  1, 1, 1, 1, 1, 1, 0),
        row("Personnel — gérer",      1, 1, 0, 0, 0, 0, 0),
        row("OM — consulter",         1, 1, 1, 1, 1, 1, 1),
        row("OM — retranscrire",      0, 1, 0, 0, 0, 0, 0),
        row("État — contrôler",       0, 0, 0, 1, 0, 0, 0),
        row("État — viser (DAF)",     0, 0, 0, 0, 1, 0, 0),
        row("État — valider (DG)",    0, 0, 0, 0, 0, 1, 0),
        row("État — numéroter",       0, 0, 0, 1, 1, 1, 0),
        row("État — rejeter",         0, 0, 0, 1, 1, 1, 0),
        row("Paiements — consulter",  1, 1, 1, 1, 1, 1, 0),
        row("Paiements — générer",    0, 0, 1, 0, 0, 0, 0),
        row("Paiements — confirmer",  0, 0, 1, 0, 0, 0, 0),
        row("Barème — consulter",     1, 1, 1, 1, 1, 1, 0),
        row("Barème — modifier",      1, 0, 0, 0, 0, 0, 0),
        row("Rapports — consulter",   1, 1, 1, 1, 1, 1, 1),
        row("Audit — consulter",      1, 0, 0, 1, 1, 1, 0),
        row("Utilisateurs — gérer",   1, 0, 0, 0, 0, 0, 0),
    ],
    largeurs=[4.6] + [1.6] * 7,
)

# =========================================================================
# 3. PREMIERS PAS
# =========================================================================
h1("3. Premiers pas")
h2("3.1 Connexion")
etape("Ouvrez l'application dans votre navigateur.")
etape("Saisissez votre adresse e-mail professionnelle et votre mot de passe.")
etape("Cliquez sur « Se connecter ». Vous arrivez sur le tableau de bord.")
encadre("Sécurité",
        "Pour votre protection, le message d'erreur en cas d'échec est volontairement identique "
        "(« Identifiants incorrects. ») quelle que soit la cause. Après plusieurs tentatives infructueuses, "
        "la connexion est temporairement bloquée.")

h2("3.2 Mot de passe oublié")
etape("Sur l'écran de connexion, cliquez sur « Mot de passe oublié ? ».")
etape("Saisissez votre e-mail. Un lien de réinitialisation vous est envoyé (si le compte existe).")
etape("Suivez le lien reçu, puis définissez un nouveau mot de passe.")

h2("3.3 Changement de mot de passe obligatoire")
para("À la création de votre compte ou après une réinitialisation par un administrateur, l'application "
     "vous demande de définir un nouveau mot de passe à votre première connexion. Tant que ce changement "
     "n'est pas effectué, l'accès aux autres écrans est bloqué.")
etape("Saisissez votre mot de passe actuel (temporaire).")
etape("Choisissez un nouveau mot de passe : au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles.")
etape("Confirmez. Vous accédez ensuite normalement à l'application.")

# =========================================================================
# 4. TABLEAU DE BORD
# =========================================================================
h1("4. Tableau de bord")
para("À la connexion, le tableau de bord présente une vue d'ensemble : indicateurs clés (OM en cours, "
     "OM validés, paiements du mois), graphiques et accès rapides aux principales actions. Les indicateurs "
     "affichés dépendent de vos droits.")

# =========================================================================
# 5. PERSONNEL / AGENTS
# =========================================================================
h1("5. Gestion du personnel (agents)")
para("Le référentiel des agents centralise les bénéficiaires. C'est lui qui fournit le téléphone et "
     "l'opérateur (MTN / Moov) utilisés pour le paiement — jamais l'ordre de mission.")

h2("5.1 Consulter la liste")
bullet("Recherche par nom, matricule ou IFU.")
bullet("Filtres par catégorie (ORDRE_1/2/3), par opérateur et par statut actif/inactif.")
bullet("Pagination et accès à la fiche détaillée d'un agent.")

h2("5.2 Créer ou modifier un agent")
para("Réservé aux profils disposant de « Personnel — gérer » (GESTIONNAIRE_OM, ADMIN).")
etape("Cliquez sur « Nouvel agent » (ou « Modifier » depuis une fiche).")
etape("Renseignez l'identité, le matricule, l'indice ou le salaire brut (la catégorie en est déduite).")
etape("Saisissez le téléphone (10 chiffres, commençant par 01) et l'opérateur cohérent avec le numéro.")
etape("Enregistrez. La catégorie ORDRE_1/2/3 est calculée automatiquement.")

h2("5.3 Importer des agents (Excel / CSV)")
etape("Ouvrez « Import » dans le module Personnel.")
etape("Déposez votre fichier Excel/CSV et lancez la prévisualisation.")
etape("Consultez le rapport de contrôle : chaque ligne est vérifiée (unicité du matricule et du téléphone, cohérence numéro/opérateur).")
etape("Validez l'import.")
encadre("À savoir",
        "L'import est « tout ou rien » : à la moindre erreur sur une ligne, aucun agent n'est créé "
        "(annulation complète). Corrigez le fichier puis relancez.")

# =========================================================================
# 6. ORDRES DE MISSION
# =========================================================================
h1("6. Ordres de mission")
para("C'est le cœur de l'application. Les OM sont retranscrits depuis SEMO ; ils ne sont jamais saisis "
     "ni modifiés individuellement à la main après coup.")

h2("6.1 Retranscrire une mission (assistant en 3 étapes)")
para("Réservé au GESTIONNAIRE_OM (permission « OM — retranscrire »). Une mission regroupe plusieurs "
     "bénéficiaires (participants + CAV).")
h3("Étape 1 — Contexte de la mission")
bullet("Objet, destination, zone (déduite de la destination), dates de départ et de retour.")
bullet("Indiquez s'il y a prise en charge par un tiers (exonération : aucun frais alloué).")
h3("Étape 2 — Bénéficiaires")
bullet("Ajoutez chaque bénéficiaire (participant ou CAV) avec sa référence SEMO unique et son type de prise en charge.")
bullet("Répartition détaillée (facultative) : cochez « Répartition détaillée par type de repas » pour "
       "saisir le nombre de jours par type (ex. 1 jour « un repas » + 6 jours « journée complète »), "
       "comme sur l'état de paiement officiel. Sinon, un seul type × le nombre de jours suffit.")
bullet("Transport (facultatif) : renseignez un montant de transport ; il s'ajoute au total et entre "
       "dans la répartition avance / reliquat.")
bullet("Le per diem (per diem + transport = total, puis avance / reliquat) se calcule en temps réel "
       "pour chaque bénéficiaire.")
bullet("Les doublons de référence SEMO sont détectés immédiatement.")
h3("Étape 3 — Récapitulatif")
bullet("Vérifiez les totaux (avance / reliquat) puis enregistrez.")
para("À l'enregistrement, chaque OM est créé au statut IMPORTE, accompagné de ses versements AVANCE et "
     "RELIQUAT (sauf exonération). Les OM conservent ce statut jusqu'à la validation finale de l'état "
     "de paiement de la mission.")

h2("6.2 Consulter un ordre de mission")
bullet("Liste filtrable par statut, zone, opérateur, agent, dates ; recherche par référence SEMO.")
bullet("La fiche détaillée affiche la mission, le bénéficiaire, les montants et les versements.")
bullet("La fiche renvoie vers l'état de paiement de la mission : c'est là que se fait la validation, "
       "pas sur l'OM lui-même.")
bullet("Téléchargement du brouillon de l'OM et consultation de son journal d'audit.")

para("La validation des ordres de mission ne se fait pas un par un : elle se joue sur l'état de paiement "
     "de la mission (chapitre 7). Le présent chapitre s'arrête donc à la retranscription et à la "
     "consultation.")

# =========================================================================
# 7. ÉTAT DE PAIEMENT (CIRCUIT DE VALIDATION)
# =========================================================================
h1("7. État de paiement et circuit de validation")
para("Chaque mission possède un seul état de paiement : le document récapitulatif qui liste tous ses "
     "bénéficiaires, leurs montants (avance et reliquat) et le total général arrêté en toutes lettres. "
     "C'est cet état — et non les OM pris séparément — qui suit le circuit de validation.")

h2("7.1 Établir le récapitulatif (brouillon)")
para("Acteur : GESTIONNAIRE_OM. Depuis la mission, cliquez sur « Établir le récapitulatif ».")
bullet("L'application agrège les OM de la mission et produit l'état au statut BROUILLON.")
bullet("Le brouillon est téléchargeable en PDF : il porte le filigrane « BROUILLON — NON VALIDE » et "
       "ne comporte aucune signature. C'est l'épreuve que l'on relit avant d'engager le circuit.")
bullet("Tant qu'aucune signature n'est apposée, « Rafraîchir » recalcule l'état si les OM ont changé.")

h2("7.2 Le circuit en trois étapes")
para("L'état passe successivement entre trois mains. Chacun retrouve les états qui l'attendent dans "
     "l'écran « États de paiement », ouvert d'emblée sur sa propre file.")
tableau(
    ["Étape", "Acteur", "Action", "Nouveau statut"],
    [
        ["1", "Contrôleur", "Contrôle de conformité de l'état.", "CONTRÔLÉ"],
        ["2", "DAF", "Appose le visa « Vu ».", "VISÉ DAF"],
        ["3", "Directeur Général", "Valide définitivement (numéro requis).", "VALIDÉ"],
    ],
    largeurs=[1.6, 3.2, 8.0, 4.0],
)
bullet("Le numéro de l'état est saisi manuellement, suivant le registre de l'agence. Il est requis "
       "avant la validation finale du DG, puis figé.")
bullet("À chaque étape, la signature du titulaire (nom, prénoms, fonction et paraphe scanné) est apposée "
       "sur le document, avec un code de vérification.")
bullet("La validation finale du DG arrête l'état et fait passer tous les OM de la mission au statut "
       "VALIDE : ils deviennent alors payables.")
encadre("Rejet possible à chaque étape",
        "Le contrôleur, le DAF ou le DG peuvent rejeter l'état avec un motif (≥ 10 caractères). Le rejet "
        "efface les signatures déjà apposées et renvoie l'état en correction ; les ordres de mission "
        "gardent leur statut. Le gestionnaire peut alors refaire le récapitulatif.")

h2("7.3 L'état de paiement définitif")
para("Une fois l'état VALIDÉ, son PDF définitif est téléchargeable : sans filigrane, avec les deux "
     "signatures (DAF puis Directeur Général) et le total général arrêté en toutes lettres. C'est le "
     "document officiel de la mission. Il est édité au format paysage.")

h2("7.4 Signatures des titulaires (paramétrage)")
para("Réservé au SUPER_ADMIN, écran « Signatures ». Pour le contrôleur, le DAF et le DG, on y enregistre "
     "les nom et prénoms, la fonction imprimée et l'image de la signature manuscrite scannée.")
encadre("Titulaire en poste ≠ compte connecté",
        "Les identités imprimées sur l'état sont celles des titulaires en poste, indépendamment du compte "
        "qui effectue la validation (utile en cas d'intérim). Le compte réellement utilisé reste, lui, "
        "tracé au journal d'audit. Sans signature enregistrée, l'état sort sans paraphe.")

# =========================================================================
# 8. CALCUL DU PER DIEM
# =========================================================================
h1("8. Calcul du per diem")
para("Le montant est calculé automatiquement par l'application :")
para("Per diem = somme, pour chaque type de prise en charge, de (taux journalier × nombre de jours). "
     "Montant total = per diem + transport éventuel.", bold=True)
bullet("Nombre de jours : décompte inclusif des dates (règle des 12 h : une période ≥ 12 h compte pour un jour entier).")
bullet("Répartition détaillée : un même bénéficiaire peut cumuler plusieurs types (ex. 1 jour « un repas » "
       "à 15 000 + 6 jours « journée complète » à 45 000 = 285 000), comme sur l'état de paiement officiel.")
bullet("Transport : montant facultatif ajouté au total avant la répartition avance / reliquat.")
bullet("Avance = arrondi de (montant total × 70 %).")
bullet("Reliquat = montant total − avance, soit 30 %.")
bullet("Exonération (prise en charge par un tiers) : tous les montants sont à 0, aucun versement n'est créé.")
para("Les 4 zones sont : INTERIEUR, EXT_ZONE_I, EXT_ZONE_II et EXT_ZONE_III (les zones II et III ont des "
     "taux identiques). Les types « repas » (un repas, deux repas) ne s'appliquent qu'à l'intérieur ; les "
     "types d'hébergement (logé/nourri…) ne s'appliquent qu'à l'extérieur.")

# =========================================================================
# 9. PAIEMENTS
# =========================================================================
h1("9. Paiements")
para("L'application GÉNÈRE des fichiers de paiement ; elle ne décaisse pas. Réservé à l'AGENT_FINANCIER.")
encadre("Préalable",
        "Les fichiers ne peuvent être générés qu'une fois l'état de paiement de la mission VALIDÉ par le "
        "Directeur Général (ses OM passent alors au statut VALIDE). Un versement déjà CONFIRMÉ n'est jamais "
        "réinclus dans un nouveau fichier (protection anti-double-paiement).")

h2("8.1 Générer un fichier de paiement (MTN ou Moov)")
etape("Ouvrez « Paiements MTN » ou « Paiements Moov ».")
etape("Sélectionnez la mission, la tranche (AVANCE ou RELIQUAT) et l'opérateur.")
etape("Cliquez sur « Prévisualiser » : la liste des bénéficiaires éligibles s'affiche.")
etape("Cliquez sur « Générer » : le fichier (.txt pour MTN, .xlsx pour Moov) est téléchargé. Le lot passe au statut GÉNÉRÉ.")
para("Pour un volume important, la génération se fait en arrière-plan ; un suivi vous informe lorsque le "
     "fichier est prêt.")

h2("8.2 Déposer puis confirmer")
etape("Déposez le fichier généré sur la plateforme de l'opérateur (hors application).")
etape("Une fois le dépôt réussi, revenez dans l'application et cliquez sur « Confirmer » pour le lot.")
etape("Le lot et ses versements passent au statut CONFIRMÉ et sont horodatés. L'OM dont tous les versements sont confirmés passe au statut SOLDÉ.")

h2("8.3 Régénérer ou signaler une erreur")
bullet("Tant qu'un lot n'est pas confirmé, vous pouvez le régénérer.")
bullet("En cas de problème de dépôt, marquez le lot « en erreur » : cela réautorise une nouvelle génération.")
bullet("Un lot confirmé est définitif : il ne peut plus être régénéré.")

h2("8.4 Suivi des paiements")
bullet("« Statuts » : synthèse des versements par opérateur et par statut.")
bullet("« Historique » : versements confirmés, filtrables par période.")
bullet("« Réconciliation » : rappel du modèle manuel (aucun réimport de fichier opérateur).")

# =========================================================================
# 10. BARÈME
# =========================================================================
h1("10. Barème per diem")
bullet("Consultation : accessible à tous les profils internes (« Barème — consulter »).")
bullet("Modification des taux : réservée à l'ADMIN (« Barème — modifier »).")
para("Le détail complet des taux figure en Annexe B.")

# =========================================================================
# 11. RAPPORTS
# =========================================================================
h1("11. Rapports et états")
para("Les états sont alimentés par les versements confirmés.")
bullet("Rapport mensuel : synthèse par opérateur, par tranche et par statut.")
bullet("Par agent, par opérateur, par zone, par imputation budgétaire.")
bullet("Export Excel ou PDF (immédiat ou en arrière-plan pour les gros volumes).")

# =========================================================================
# 12. AUDIT
# =========================================================================
h1("12. Journal d'audit")
para("Le journal trace les actions sensibles : qui a fait quoi, quand, et depuis quelle adresse IP "
     "(retranscription, validation/rejet, génération et confirmation de paiement, gestion des comptes…). "
     "Accessible à l'ADMIN et au CONTROLEUR. Chaque entrée peut être consultée en détail.")

# =========================================================================
# 13. ADMINISTRATION
# =========================================================================
h1("13. Administration")
para("Module réservé aux administrateurs.")
h2("13.1 Gestion des utilisateurs")
bullet("Créer, modifier, activer/désactiver, supprimer des comptes ; réinitialiser un mot de passe.")
bullet("Toute action sur un super administrateur est réservée à un super administrateur.")
bullet("Le dernier super administrateur actif ne peut être ni rétrogradé, ni désactivé, ni supprimé.")
bullet("On ne peut pas désactiver ou supprimer son propre compte.")
encadre("Sécurité des sessions",
        "Lorsqu'un compte est désactivé, supprimé, change de rôle ou voit son mot de passe réinitialisé, "
        "ses sessions actives sont immédiatement révoquées.")
h2("13.2 Autres écrans d'administration")
bullet("Rôles : matrice des permissions par profil (consultation).")
bullet("Signatures : identités et paraphes scannés du contrôleur, du DAF et du Directeur Général, "
       "imprimés sur l'état de paiement (voir chapitre 7.4).")
bullet("Paramètres : configuration générale de l'application.")
bullet("Configuration e-mail (SMTP) : le mot de passe est stocké chiffré et n'est jamais réaffiché en clair.")
bullet("Sauvegardes : création et gestion des sauvegardes de la base de données.")

# =========================================================================
# ANNEXES
# =========================================================================
doc.add_page_break()
h1("Annexe A — Statuts")
h2("État de paiement (un par mission)")
tableau(["Statut", "Signification"],
        [["BROUILLON", "Récapitulatif établi, épreuve téléchargeable ; en attente du contrôle."],
         ["CONTROLE", "Contrôlé par le contrôleur ; en attente du visa du DAF."],
         ["VISA_DAF", "Visé par le DAF ; en attente de la validation du Directeur Général."],
         ["VALIDE", "Validé par le DG — verrouillé ; les OM deviennent payables."],
         ["REJETE", "Rejeté (avec motif) ; le récapitulatif peut être refait."]],
        largeurs=[4.0, 12.8])
h2("Ordre de mission")
tableau(["Statut", "Signification"],
        [["IMPORTE", "Retranscrit depuis SEMO ; statut conservé pendant tout le circuit de l'état."],
         ["VALIDE", "L'état de paiement de sa mission a été validé par le DG — payable."],
         ["REJETE", "Rejeté individuellement (reprise de données ; motif requis)."],
         ["SOLDE", "Tous les versements de l'OM ont été confirmés."]],
        largeurs=[4.0, 12.8])
h2("Versement")
tableau(["Statut", "Signification"],
        [["EN_ATTENTE", "Créé, pas encore inclus dans un fichier."],
         ["GENERE", "Inclus dans un fichier généré, non confirmé."],
         ["CONFIRME", "Paiement confirmé après dépôt."]],
        largeurs=[4.0, 12.8])
h2("Lot de paiement")
tableau(["Statut", "Signification"],
        [["GENERE", "Fichier généré, régénérable."],
         ["CONFIRME", "Paiement confirmé — définitif."],
         ["ERREUR", "Signalé en erreur — régénération autorisée."]],
        largeurs=[4.0, 12.8])

doc.add_page_break()
h1("Annexe B — Barème officiel (FCFA / jour)")
h2("Zone INTERIEUR")
tableau(["Catégorie", "Un repas", "Deux repas", "Journée complète"],
        [["ORDRE_1", "15 000", "30 000", "45 000"],
         ["ORDRE_2", "10 000", "20 000", "35 000"],
         ["ORDRE_3", "8 500", "15 000", "30 000"]],
        largeurs=[4.2, 4.2, 4.2, 4.2])
h2("Zone EXT_ZONE_I")
tableau(["Catégorie", "Journée complète", "Logé+nourri", "Logé seul", "Nourri seul"],
        [["ORDRE_1", "175 000", "60 000", "90 000", "120 000"],
         ["ORDRE_2", "140 000", "50 000", "70 000", "95 000"],
         ["ORDRE_3", "105 000", "35 000", "55 000", "70 000"]],
        largeurs=[3.3, 3.5, 3.3, 3.3, 3.3])
h2("Zones EXT_ZONE_II et EXT_ZONE_III (taux identiques)")
tableau(["Catégorie", "Journée complète", "Logé+nourri", "Logé seul", "Nourri seul"],
        [["ORDRE_1", "105 000", "35 000", "55 000", "70 000"],
         ["ORDRE_2", "85 000", "30 000", "45 000", "60 000"],
         ["ORDRE_3", "65 000", "25 000", "35 000", "45 000"]],
        largeurs=[3.3, 3.5, 3.3, 3.3, 3.3])
para("Note : ces montants sont ceux du barème par défaut et peuvent être ajustés par l'ADMIN dans le "
     "module Barème. Référez-vous à l'écran Barème pour les valeurs en vigueur.", italic=True, color=GRIS)

doc.add_page_break()
h1("Annexe C — Glossaire")
tableau(["Terme", "Définition"],
        [["SEMO", "Service émetteur des ordres de mission (hors application)."],
         ["OM", "Ordre de mission : 1 enregistrement = 1 bénéficiaire."],
         ["CAV", "Conducteur de véhicule administratif, payé selon sa propre catégorie."],
         ["Per diem", "Indemnité journalière de mission."],
         ["Avance / Reliquat", "Tranches du per diem : 70 % puis 30 %."],
         ["Lot", "1 mission × 1 tranche × 1 opérateur = 1 fichier de paiement."],
         ["MSISDN", "Numéro de téléphone au format international (229 + numéro)."],
         ["Exonération", "Prise en charge par un tiers : aucun frais alloué."]],
        largeurs=[4.2, 12.6])

doc.add_paragraph()
flag_bar()
fin = doc.add_paragraph(); fin.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = fin.add_run("© 2026 ANaTT — Agence Nationale des Transports Terrestres — Bénin")
r.font.size = Pt(9); r.font.color.rgb = GRIS

doc.save(SORTIE)
print("Guide généré :", SORTIE)
