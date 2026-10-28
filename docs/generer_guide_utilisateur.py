# -*- coding: utf-8 -*-
"""
Génère le Guide Utilisateur ANaTT (.docx) à partir du contenu ci-dessous.

Usage :
    python docs/generer_guide_utilisateur.py
Dépendance :
    pip install python-docx

Le contenu reflète l'implémentation réelle de l'application (rôles, workflows,
barème, statuts). Les figures sont les captures de l'application réelle
(docs/ecrans/, produites par docs/capturer_ecrans.cjs). À régénérer après toute
évolution fonctionnelle majeure.
"""
import os
from datetime import date
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


# Numérotation des étapes : chaque titre ouvre une nouvelle procédure, dont les
# étapes repartent à 1 (sinon « List Number » continue sur tout le document).
_liste_etapes = None


def _titre(t, niveau):
    global _liste_etapes
    _liste_etapes = None
    doc.add_heading(t, level=niveau)


def h1(t): _titre(t, 1)
def h2(t): _titre(t, 2)
def h3(t): _titre(t, 3)


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
    global _liste_etapes
    p = doc.add_paragraph(t, style="List Number")
    if _liste_etapes is None:
        # Nouvelle instance de la liste numérotée du style, redémarrée à 1.
        numbering = doc.part.numbering_part.element
        num_style = doc.styles["List Number"].element.pPr.numPr.numId.val
        abstrait = numbering.num_having_numId(num_style).abstractNumId.val
        num = numbering.add_num(abstrait)
        num.add_lvlOverride(ilvl=0).add_startOverride(1)
        _liste_etapes = num.numId
    numPr = p._p.get_or_add_pPr().get_or_add_numPr()
    numPr.get_or_add_ilvl().val = 0
    numPr.get_or_add_numId().val = _liste_etapes


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


ECRANS = os.path.join(ICI, "ecrans")


def capture(nom, legende):
    """Capture de l'écran réel (docs/ecrans, produite par capturer_ecrans.cjs), avec sa légende."""
    chemin = os.path.join(ECRANS, f"{nom}.jpg")
    if not os.path.exists(chemin):
        print(f"  capture absente, ignorée : {nom}.jpg")
        return
    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(chemin, width=Cm(16))
    leg = doc.add_paragraph(); leg.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = leg.add_run(f"Figure — {legende}"); run.italic = True; run.font.size = Pt(9); run.font.color.rgb = GRIS


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
        # Largeurs fixes : sur les cellules (Word) ET sur la grille du tableau,
        # que LibreOffice lit seule — sinon les colonnes y restent égales.
        t.autofit = False
        for j, w in enumerate(largeurs):
            t.columns[j].width = Cm(w)
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
r = m.add_run("Version 1.4.0  •  Octobre 2026"); r.bold = True; r.font.color.rgb = OR
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
# NOUVEAUTÉS
# =========================================================================
h1("Nouveautés de la version 1.4 (octobre 2026)")
para("Cette version renforce la sûreté des paiements et complète plusieurs écrans. Les changements "
     "visibles pour les utilisateurs sont résumés ci-dessous, avec le chapitre où ils sont détaillés.")
tableau(
    ["Domaine", "Ce qui change", "Chapitre"],
    [
        ["État des reliquats", "Il est signé au montant réellement payé (somme des reliquats positifs). "
         "Les trop-perçus figurent à part sur le PDF, hors total.", "7.3"],
        ["Lots complémentaires", "Un versement devenu payable après la confirmation d'un lot part dans un "
         "nouveau lot « complémentaire » ; le lot confirmé n'est jamais réécrit.", "9.3"],
        ["Lot en erreur", "Les versements d'un lot signalé en erreur redeviennent en attente et reprennent le "
         "numéro actuel de l'agent : corrigez la fiche agent, puis régénérez.", "9.3"],
        ["Gros lots", "La génération en arrière-plan affiche sa progression et télécharge le fichier "
         "automatiquement à la fin.", "9.1"],
        ["Amendement", "Refusé tant qu'un fichier contenant l'OM attend son dépôt.", "6.3"],
        ["OM sans état de paiement", "Boutons « Valider » et « Rejeter » sur la fiche de l'OM (reprise de "
         "données). Un OM rejeté voit ses versements annulés.", "6.2"],
        ["Retranscription", "Un même agent ne peut figurer qu'une fois dans une mission.", "6.1"],
        ["Per diem", "Sans jour facturé, le transport reste dû (seule l'exonération met tout à 0).", "8"],
        ["Liste des OM", "Le filtre « opérateur » porte sur l'opérateur du paiement.", "6.2"],
        ["Sauvegardes", "Téléchargement et restauration d'une sauvegarde, avec sauvegarde de sécurité "
         "automatique avant restauration. La liste est classée de la plus récente à la plus ancienne.", "13.3"],
        ["Droits d'accès", "La séparation ordonnateur / payeur est aussi vérifiée pour chaque membre d'un "
         "profil modifié.", "2.2"],
        ["PDF de l'OM", "Il reprend les signatures de l'état des avances qui couvre l'OM (même code de "
         "vérification que l'état), au fur et à mesure du circuit.", "6.2"],
        ["PDF d'un OM amendé", "Il est régénéré après chaque amendement et indique l'avance engagée, le "
         "reliquat ajusté ou le trop-perçu, avec le rappel du dernier amendement.", "6.3"],
        ["Fiche de l'OM", "Nouveau lien « PDF de l'OM » en haut de la fiche : le PDF officiel reste "
         "accessible une fois l'OM validé.", "6.2"],
        ["Restauration", "Les droits d'accès sauvegardés reprennent effet immédiatement ; les réglages de "
         "« Paramètres » et les paraphes ne font pas partie de la sauvegarde.", "13.3"],
        ["OM soldé", "Un OM passe SOLDÉ quand tout ce qui lui est dû est réglé : versements confirmés, ou "
         "trop-perçu recouvré. Une tranche à 0 (part d'avance de 0 ou 100 %) ne le retient pas, et un OM "
         "exonéré est soldé dès sa validation.", "9.2, 9.5"],
        ["Amendement d'un OM soldé", "« Amender » reste proposé sur un OM soldé dont le reliquat n'est ni payé ni "
         "recouvré : une rallonge le rouvre, une réduction à zéro le solde.", "6.3"],
        ["Lot complémentaire", "Sur l'écran de génération, le bouton devient « Générer le lot complémentaire » "
         "dès qu'un lot confirmé laisse des bénéficiaires à payer.", "9.3"],
        ["Notifications", "Elles suivent les droits réels : un compte reçoit la cloche et l'e-mail dès qu'il "
         "détient la permission de l'étape (rôle personnalisé ou droit accordé à titre individuel compris) ; "
         "un droit retiré n'est plus notifié. Seuls les comptes actifs sont prévenus.", "7.2"],
        ["Heure du Bénin", "Les dates et heures enregistrées (paiements, signatures, journal) sont à l'heure "
         "du Bénin. Un paiement confirmé le 1er du mois juste après minuit compte dans ce mois.", "11"],
        ["Échec d'une génération", "Après une erreur, l'écran de génération recharge le lot de lui-même : "
         "plus besoin de recharger la page.", "9.1"],
        ["Fiche et aperçu de l'OM amendé", "Ils affichent les mêmes libellés que le PDF officiel : « Avance "
         "engagée », « Reliquat (ajusté par amendement) » ou « Trop-perçu ».", "6.3"],
        ["Configuration e-mail", "Le serveur SMTP et l'expéditeur saisis à l'écran sont appliqués à l'envoi "
         "(sinon le .env du serveur) ; bouton « Envoyer un e-mail de test ». L'écran présente aussi les "
         "notifications réellement envoyées, avec leurs destinataires.", "13.2"],
        ["Réglages de Paramètres", "Tous appliqués : opérateurs MTN / Moov, notifications par type, durée de "
         "session, tentatives de connexion, HTTPS forcé, journal des connexions échouées, mode maintenance, "
         "nom de l'application.", "13.2"],
        ["Erreur passagère", "Un incident momentané du serveur (trop de requêtes, erreur, coupure réseau) "
         "au rechargement d'une page ne vous déconnecte plus : l'écran d'erreur s'affiche et « Réessayer » "
         "vous ramène à la page demandée, toujours connecté.", "3.4"],
        ["Saisie d'une mission", "Un même agent ajouté deux fois est signalé dès la saisie (« Agent déjà "
         "présent dans cette mission »).", "6.1"],
        ["Part de l'avance d'une mission", "L'écran d'un état de paiement (« Avances (70 %) » / « Reliquats "
         "(30 %) ») et le choix de la tranche à la génération affichent la part avec laquelle les OM de la "
         "mission ont été retranscrits, et non le réglage du jour : changer la part dans « Paramètres » "
         "ne modifie ni les montants ni les pourcentages d'une mission déjà saisie.", "7.1, 8, 9.1"],
        ["Fonction imprimée", "Sans fonction saisie dans « Signatures », l'état imprime celle du compte du "
         "signataire, puis le libellé du poste.", "7.4"],
        ["Menu", "Libellés accentués : « États de paiement », « Générer MTN », « Générer Moov », "
         "« Régularisations », « Barèmes », « Paramètres », « Matrice des permissions ».", "—"],
    ],
    largeurs=[3.6, 11.0, 2.2],
)
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
para("Le parcours complet d'un dossier suit toujours la même séquence. Le paiement se fait en DEUX "
     "temps — d'abord les avances, puis les reliquats — et chaque tranche a son propre état de paiement "
     "et son propre circuit de validation :")
etape("SEMO émet les ordres de mission (hors application).")
etape("Un gestionnaire retranscrit les OM dans l'application, regroupés par mission.")
etape("Le système calcule le per diem (catégorie × zone × type de prise en charge × nombre de jours).")
etape("Le gestionnaire établit le récapitulatif de l'ÉTAT DES AVANCES (brouillon).")
etape("Contrôleur → visa DAF → validation DG sur l'état des avances.")
etape("La validation du DG arrête l'état des avances, rend les OM VALIDE et ouvre les fichiers d'AVANCE.")
etape("L'agent financier génère et dépose les fichiers d'avance (.txt MTN / .xlsx Moov), puis les confirme.")
etape("Une fois l'état des avances validé, le gestionnaire établit l'ÉTAT DES RELIQUATS, qui suit le même circuit.")
etape("La validation de l'état des reliquats ouvre les fichiers de RELIQUAT, générés, déposés puis confirmés.")
encadre("Ce qui est validé, c'est l'état — et il y en a deux",
        "La validation ne se fait pas ordre par ordre. Chaque mission possède DEUX états de paiement : "
        "l'état des AVANCES puis l'état des RELIQUATS. Chacun passe le circuit contrôle → visa DAF → "
        "validation DG et ouvre les fichiers de SA tranche. L'état des reliquats ne peut être établi "
        "qu'une fois l'état des avances validé. Les OM, eux, conservent le statut sous lequel ils ont été "
        "retranscrits depuis SEMO jusqu'à la validation de l'état des avances, qui les rend payables.")

h2("1.2 Notions clés")
bullet("Mission : un événement (atelier, supervision…) qui regroupe plusieurs ordres de mission.")
bullet("Ordre de mission (OM) : 1 enregistrement = 1 bénéficiaire. Sa référence SEMO est unique.")
bullet("État de paiement : le document récapitulatif d'une TRANCHE d'une mission (deux par mission : "
       "l'état des avances puis l'état des reliquats), numéroté à la main, qui porte le circuit de "
       "validation et les signatures du DAF et du Directeur Général.")
bullet("CAV (conducteur de véhicule administratif) : bénéficiaire à part entière, payé selon SA catégorie.")
bullet("Per diem : indemnité journalière, répartie en AVANCE (70 % par défaut) et RELIQUAT (le reste). "
       "La part de l'avance est paramétrable par le SUPER_ADMIN.")
bullet("Versement : une tranche (AVANCE ou RELIQUAT) d'un OM, pour un opérateur donné.")
bullet("Lot de paiement : 1 mission × 1 tranche × 1 opérateur = 1 fichier généré. Un lot "
       "« complémentaire » peut s'y ajouter pour les versements devenus payables après sa confirmation.")
bullet("Amendement (avenant) : ajustement des jours facturés d'un OM après paiement de l'avance ; "
       "l'avance est figée et seul le reliquat absorbe l'écart (voir chapitre 6.3).")
bullet("Trop-perçu (régularisation) : reliquat devenu négatif après un amendement à la baisse ; il est "
       "récupéré par retenue sur salaire, jamais par Mobile Money (voir chapitre 9.5).")

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
        "confirment les paiements. Les trois signataires d'un même état doivent être TROIS personnes "
        "distinctes : celui qui a contrôlé ne peut pas viser ni valider, et celui qui a visé (DAF) ne "
        "peut pas valider (DG). Un compte cumulant plusieurs droits (ex. super administrateur) est donc "
        "refusé s'il tente d'enchaîner deux étapes du même dossier.")

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
encadre("Cette matrice est le défaut — elle est personnalisable",
        "Le tableau ci-dessus est la configuration par défaut. Depuis la version 1.3, le SUPER_ADMIN peut "
        "l'ajuster à chaud (voir chapitre 13.2), y compris créer des profils sur mesure. Les droits réels "
        "d'un utilisateur peuvent donc différer de ce défaut.")

h2("2.2 RBAC flexible : ajuster les droits sans redéploiement")
para("Les autorisations reposent sur des clés de permission (ex. « Paiements — générer »). Ce qui est "
     "attribué à chaque profil est modifiable par le SUPER_ADMIN, sur trois niveaux cumulables :")
bullet("Par profil : cocher / décocher les permissions d'un rôle dans la Matrice des permissions ; "
       "réinitialisable au défaut à tout moment.")
bullet("Par utilisateur : accorder une permission supplémentaire à une personne précise, ou lui en "
       "retirer une que son profil donnerait (droits effectifs = profil + accordées − retirées).")
bullet("Rôles personnalisés : créer de nouveaux profils au-delà des huit profils système (eux, non "
       "supprimables), avec leur propre jeu de permissions, puis y affecter des utilisateurs.")
encadre("Garde-fous",
        "Le SUPER_ADMIN garde toujours l'accès total et n'est pas modifiable. Certaines clés sensibles "
        "(gestion des super admins, des paramètres, de la matrice elle-même) lui restent exclusives. Enfin, "
        "la séparation ordonnateur / payeur est vérifiée à chaque modification : un profil ou un compte qui "
        "valide l'état (DG) ne peut pas aussi générer ou confirmer les paiements. Cette règle est vérifiée "
        "pour chaque membre d'un profil modifié, droits individuels compris : la modification est refusée "
        "et les comptes concernés sont nommés. Toute modification est tracée au journal d'audit.")
capture("33_gestion_roles", "matrice des permissions")
para("Les nouveaux droits d'un utilisateur prennent effet à sa connexion (ou à son prochain "
     "rafraîchissement). La lecture de la matrice est ouverte à l'ADMIN ; sa modification est réservée "
     "au SUPER_ADMIN.")

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

capture("01_login", "écran de connexion")

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

h2("3.4 Erreur momentanée du serveur, maintenance")
para("Si le serveur ne répond pas correctement au moment où vous ouvrez ou rechargez une page (trop de "
     "requêtes en peu de temps, erreur passagère, coupure du réseau), l'application affiche l'écran "
     "« Erreur serveur » au lieu de la page demandée. Votre session est conservée : vous n'avez pas à "
     "vous reconnecter.")
etape("Patientez quelques instants (une minute au plus après « Trop de tentatives »).")
etape("Cliquez sur « Réessayer » : l'application vous ramène à la page que vous vouliez ouvrir.")
para("Pendant une mise à jour ou lorsque le super administrateur active le mode maintenance, l'écran "
     "« Maintenance en cours » s'affiche ; il revient de lui-même à votre page dès que le service reprend. "
     "Seule une session réellement expirée (durée de session dépassée, compte désactivé, mot de passe "
     "réinitialisé) vous renvoie à l'écran de connexion.")

# =========================================================================
# 4. TABLEAU DE BORD
# =========================================================================
h1("4. Tableau de bord")
para("À la connexion, le tableau de bord présente une vue d'ensemble : indicateurs clés (ordres de "
     "mission actifs — hors rejetés/annulés, versements confirmés et montant payé du mois, opérateurs "
     "actifs), graphiques et accès rapides aux principales actions. Les indicateurs affichés dépendent "
     "de vos droits.")

capture("04_dashboard", "tableau de bord (profil super administrateur)")

# =========================================================================
# 5. PERSONNEL / AGENTS
# =========================================================================
h1("5. Gestion du personnel (agents)")
para("Le référentiel des agents centralise les bénéficiaires. C'est lui qui fournit le téléphone et "
     "l'opérateur (MTN / Moov) utilisés pour le paiement — jamais l'ordre de mission.")

h2("5.1 Consulter la liste")
capture("05_personnel_liste", "liste du personnel")
bullet("Recherche par nom, matricule ou IFU.")
bullet("Filtres par catégorie d'indice (≥ 800 / 400-799 / < 400), par opérateur et par statut actif/inactif.")
bullet("Pagination et accès à la fiche détaillée d'un agent.")

h2("5.2 Créer ou modifier un agent")
para("Réservé aux profils disposant de « Personnel — gérer » (GESTIONNAIRE_OM, ADMIN).")
etape("Cliquez sur « Nouvel agent » (ou « Modifier » depuis une fiche).")
etape("Renseignez l'identité, le matricule, l'indice ou le salaire brut (la catégorie en est déduite).")
etape("Saisissez le téléphone (10 chiffres, commençant par 01) et l'opérateur cohérent avec le numéro.")
etape("Enregistrez. La catégorie d'indice (≥ 800 / 400-799 / < 400) est calculée automatiquement.")

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
capture("12_om_creation", "assistant de retranscription, étape 1")
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
bullet("Un même agent ne peut figurer qu'une fois dans une mission : un OM par bénéficiaire. Un agent "
       "ajouté deux fois est signalé sur sa fiche (« Agent déjà présent dans cette mission ») et bloque "
       "le passage au récapitulatif.")
h3("Étape 3 — Récapitulatif")
bullet("Vérifiez les totaux (avance / reliquat) puis enregistrez.")
para("À l'enregistrement, chaque OM est créé au statut IMPORTE, accompagné de ses versements AVANCE et "
     "RELIQUAT (sauf exonération). Les OM conservent ce statut jusqu'à la validation finale de l'état "
     "de paiement de la mission.")

h2("6.2 Consulter un ordre de mission")
bullet("Liste filtrable par statut, zone, opérateur, agent, dates ; recherche par référence SEMO. "
       "Le filtre « opérateur » porte sur l'opérateur du paiement (celui des versements), même si l'agent "
       "a changé d'opérateur depuis.")
bullet("La fiche détaillée affiche la mission, le bénéficiaire, les montants et les versements.")
bullet("La fiche renvoie vers l'état de paiement de la mission : c'est là que se fait la validation, "
       "pas sur l'OM lui-même.")
bullet("En haut de la fiche, « Historique » ouvre le journal d'audit de l'OM et « PDF de l'OM » ouvre "
       "l'aperçu du PDF officiel, avec le bouton « Télécharger PDF ». Ce lien reste disponible une fois "
       "l'OM validé. Avant validation, « Télécharger le brouillon » fournit en plus une épreuve filigranée.")
bullet("Le PDF de l'OM reprend les signatures de l'état des avances de sa mission, au fur et à mesure "
       "du circuit (contrôle, puis visa DAF, puis validation DG), avec le même code de vérification que "
       "l'état. Un OM rejeté n'en porte aucune, et un brouillon jamais. Le document de référence reste "
       "l'état de paiement.")

capture("15_detail_om", "fiche d'un OM amendé après paiement de l'avance (trop-perçu)")
para("La validation des ordres de mission ne se fait pas un par un : elle se joue sur l'état de paiement "
     "de la mission (chapitre 7).")
encadre("Exception : mission sans état de paiement (reprise de données)",
        "Pour une mission dont aucun état de paiement n'a été établi, la fiche d'un OM au statut IMPORTE "
        "propose les boutons « Valider » (CONTROLEUR) et « Rejeter » (motif d'au moins 10 caractères). "
        "Dès qu'un état existe pour la mission, ces boutons disparaissent : la validation passe alors "
        "obligatoirement par l'état. Un OM rejeté voit ses versements en attente ANNULÉS : il ne sera "
        "jamais payé. Un OM exonéré (prise en charge par un tiers) validé ainsi passe directement SOLDÉ.")

h2("6.3 Amender un ordre de mission (avenant)")
para("Il arrive qu'une mission soit rallongée ou écourtée après le paiement de l'avance. L'application "
     "permet alors d'amender un OM : ajuster son nombre de jours facturés (ou sa répartition détaillée) "
     "sans repasser par toute la retranscription. Réservé au GESTIONNAIRE_OM.")
etape("Depuis la fiche de l'OM, cliquez sur « Amender ».")
etape("Saisissez le nouveau nombre de jours (ou la nouvelle répartition) et un motif obligatoire (≥ 10 caractères).")
etape("Validez : le nouveau montant total est recalculé et le reliquat ajusté.")
encadre("L'avance est figée, seul le reliquat bouge",
        "Une fois l'état des avances validé, l'avance payée est gelée. Un amendement ne touche donc jamais "
        "l'avance : c'est le reliquat qui absorbe tout l'écart (reliquat = montant total − avance figée). "
        "Le reliquat peut ainsi augmenter (rallonge) ou diminuer, voire devenir négatif (réduction) : on "
        "parle alors de trop-perçu (voir chapitre 9.5).")
bullet("Chaque amendement est historisé (jours et montants avant / après, écart, motif, auteur, date) et "
       "consultable sur la fiche de l'OM, le plus récent en tête ; il est tracé au journal d'audit.")
bullet("L'amendement est refusé si l'OM est rejeté ou annulé, si un état de tranche est en cours de "
       "signature (contrôle / visa en cours), si l'état des reliquats est déjà validé, si le reliquat "
       "de l'OM a déjà été payé, ou si un trop-perçu de l'OM a déjà été recouvré. Ces derniers cas relèvent "
       "d'un avenant formel, hors application. Un amendement qui ramène le reliquat à zéro, l'avance étant "
       "payée, solde l'OM ; une rallonge sur un OM soldé (avance versée à 100 %) le rouvre.")
bullet("Il est aussi refusé tant qu'un fichier de paiement contenant l'OM attend son dépôt (lot GÉNÉRÉ) : "
       "confirmez d'abord le lot, ou signalez-le en erreur, puis amendez.")
bullet("Mission sans état de paiement : si l'avance a déjà été confirmée, elle est figée au moment de "
       "l'amendement, exactement comme après la validation d'un état des avances.")
bullet("La fiche de l'OM, l'aperçu « PDF de l'OM » et le PDF officiel (régénéré après l'amendement) n'affichent plus "
       "« Avance (70 %) / Reliquat (30 %) » mais « Avance engagée », puis « Reliquat (ajusté par "
       "amendement) » ou, en cas de réduction forte, « Trop-perçu (à recouvrer par retenue sur salaire) » "
       "avec un montant négatif et la mention « Montant total dû ». Un encadré rappelle l'amendement : "
       "date, nombre de jours avant et après, motif.")

# =========================================================================
# 7. ÉTAT DE PAIEMENT (CIRCUIT DE VALIDATION)
# =========================================================================
h1("7. États de paiement et circuit de validation")
para("Le paiement d'une mission se fait en DEUX temps : d'abord les AVANCES, puis les RELIQUATS. À chaque "
     "tranche correspond un état de paiement : le document récapitulatif qui liste tous les bénéficiaires, "
     "les montants de la tranche et le total arrêté en toutes lettres. Ce sont ces états — et non les OM "
     "pris séparément — qui suivent le circuit de validation. L'écran « États de paiement » présente, pour "
     "chaque mission, ses deux onglets : Avances et Reliquats.")
encadre("Les reliquats viennent après les avances",
        "L'état des RELIQUATS ne peut être établi qu'une fois l'état des AVANCES entièrement validé par le "
        "Directeur Général. Tant que ce n'est pas le cas, l'onglet Reliquats reste verrouillé.")

h2("7.1 Établir le récapitulatif (brouillon)")
para("Acteur : GESTIONNAIRE_OM. Depuis la mission, choisissez la tranche (Avances par défaut) puis cliquez "
     "sur « Établir le récapitulatif ».")
bullet("L'application agrège les OM de la mission et produit l'état de la tranche au statut BROUILLON.")
bullet("Le brouillon est téléchargeable en PDF : il porte le filigrane « BROUILLON — NON VALIDE » et "
       "ne comporte aucune signature. C'est l'épreuve que l'on relit avant d'engager le circuit.")
bullet("Tant qu'aucune signature n'est apposée, « Rafraîchir » recalcule l'état si les OM ont changé.")
bullet("La tranche Reliquats n'est proposée qu'après validation de l'état des avances.")
bullet("Les onglets « Avances (… %) » et « Reliquats (… %) » indiquent la part de l'avance de la mission, "
       "celle avec laquelle ses OM ont été retranscrits : elle ne change pas si la part est modifiée "
       "ensuite dans « Paramètres ».")

capture("18_file_etats", "file de travail des états de paiement (profil contrôleur)")

h2("7.2 Le circuit en trois étapes")
para("Chaque état (avances, puis reliquats) passe successivement entre trois mains. Le circuit est donc "
     "parcouru DEUX fois : une fois pour l'état des avances, une fois pour l'état des reliquats. Chacun "
     "retrouve les états qui l'attendent dans l'écran « États de paiement », ouvert d'emblée sur sa "
     "propre file.")
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
bullet("Validation de l'état des AVANCES : elle arrête l'état, fait passer les OM de la mission au "
       "statut VALIDE (un OM rejeté auparavant reste rejeté), fige l'avance de chacun et ouvre les "
       "fichiers d'avance.")
bullet("Validation de l'état des RELIQUATS : elle ouvre les fichiers de reliquat (les OM sont déjà VALIDE).")
bullet("Notifications (cloche et e-mail) : à chaque étape, sont prévenus les comptes actifs qui détiennent "
       "la permission de l'étape suivante — contrôler, viser, puis valider ; à la validation finale, ceux qui "
       "génèrent les fichiers de paiement ; en cas de rejet, ceux qui retranscrivent les missions. Un rôle "
       "personnalisé ou un droit accordé à titre individuel est donc notifié ; un droit retiré ne l'est plus. "
       "Le super administrateur n'est pas notifié, et un compte désactivé ne l'est jamais.")
capture("17_etat_paiement", "état des avances validé : numéro, montants et circuit signé")
encadre("Rejet possible à chaque étape",
        "Le contrôleur, le DAF ou le DG peuvent rejeter l'état avec un motif (≥ 10 caractères). Le rejet "
        "efface les signatures déjà apposées et renvoie l'état en correction ; les ordres de mission "
        "gardent leur statut. Le gestionnaire peut alors refaire le récapitulatif.")

h2("7.3 Les états définitifs et l'état général")
para("Une fois un état VALIDÉ, son PDF définitif est téléchargeable : sans filigrane, avec les deux "
     "signatures (DAF puis Directeur Général) et le total de la tranche arrêté en toutes lettres. Il est "
     "édité au format paysage.")
bullet("Il existe donc deux documents définitifs : l'état des avances et l'état des reliquats.")
encadre("L'état des reliquats porte le montant réellement payé",
        "Le total de l'état des reliquats est la somme des reliquats POSITIFS : c'est exactement ce que les "
        "fichiers de paiement verseront. Un trop-perçu (reliquat négatif après amendement) ne diminue pas ce "
        "total ; il apparaît sur la ligne du bénéficiaire et dans une mention distincte « trop-perçus hors "
        "total, à recouvrer par retenue sur salaire » (voir chapitre 9.5).")
bullet("Une fois les DEUX états validés, un « état général » consolidé est disponible : il réunit, par "
       "bénéficiaire, l'avance et le reliquat, avec le total général de la mission arrêté en toutes lettres. "
       "C'est le document de clôture de la mission.")

h2("7.4 Signatures des titulaires (paramétrage)")
para("Réservé au SUPER_ADMIN, écran « Signatures ». Pour le contrôleur, le DAF et le DG, on y enregistre "
     "les nom et prénoms, la fonction imprimée et l'image de la signature manuscrite scannée.")
encadre("Titulaire en poste ≠ compte connecté",
        "Les identités imprimées sur l'état sont celles des titulaires en poste, indépendamment du compte "
        "qui effectue la validation (utile en cas d'intérim). Le compte réellement utilisé reste, lui, "
        "tracé au journal d'audit. Sans signature enregistrée, l'état sort sans paraphe.")
bullet("Si l'écran « Signatures » ne renseigne pas un poste, l'état imprime le nom du compte qui signe ; "
       "pour la fonction, celle enregistrée sur ce compte, à défaut le libellé du poste (« Le Directeur "
       "Général »…). Nom et fonction sont figés au moment de la signature.")

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
bullet("Transport : montant facultatif ajouté au total avant la répartition avance / reliquat. Il reste "
       "dû même si aucun jour n'est facturé (per diem à 0).")
bullet("Avance = arrondi de (montant total × part de l'avance). La part est de 70 % par défaut et se "
       "règle dans les Paramètres (SUPER_ADMIN) ; elle s'applique aux OM retranscrits ensuite. Une mission "
       "déjà saisie garde ses montants, et ses écrans (fiche et PDF de l'OM, état de paiement, génération "
       "des fichiers) affichent la part avec laquelle elle a été retranscrite.")
bullet("Reliquat = montant total − avance (30 % par défaut ; le reliquat absorbe l'arrondi).")
bullet("Exonération (prise en charge par un tiers) : tous les montants sont à 0, transport compris, et "
       "aucun versement n'est créé.")
para("Les 4 zones sont : INTERIEUR, EXT_ZONE_I, EXT_ZONE_II et EXT_ZONE_III (les zones II et III ont des "
     "taux identiques). Les types « repas » (un repas, deux repas) ne s'appliquent qu'à l'intérieur ; les "
     "types d'hébergement (logé/nourri…) ne s'appliquent qu'à l'extérieur.")

# =========================================================================
# 9. PAIEMENTS
# =========================================================================
h1("9. Paiements")
para("L'application GÉNÈRE des fichiers de paiement ; elle ne décaisse pas. Réservé à l'AGENT_FINANCIER.")
encadre("Préalable — éligibilité par tranche",
        "Un versement n'est payable que si l'état de SA tranche est VALIDÉ par le Directeur Général : la "
        "validation de l'état des avances ouvre les fichiers d'avance, celle de l'état des reliquats ouvre "
        "les fichiers de reliquat. Un versement déjà CONFIRMÉ n'est jamais réinclus dans un nouveau fichier "
        "(protection anti-double-paiement).")

h2("9.1 Générer un fichier de paiement (MTN ou Moov)")
etape("Dans le menu Paiements, ouvrez « Générer MTN » ou « Générer Moov ».")
etape("Sélectionnez la mission et la tranche (AVANCE ou RELIQUAT) : l'« Aperçu des bénéficiaires » éligibles s'affiche aussitôt. Une fois la mission choisie, la tranche est libellée avec la part de cette mission (par exemple « Avance (60 %) » pour une mission retranscrite à 60 %).")
etape("Cliquez sur « Générer le fichier.txt » (MTN) ou « Générer le fichier.xlsx » (Moov) : le fichier est téléchargé et le lot passe au statut GÉNÉRÉ.")
para("Pour un volume important (plus de 50 bénéficiaires), la génération peut se faire en arrière-plan : "
     "un bandeau de suivi s'affiche et le fichier se télécharge automatiquement dès qu'il est prêt. Restez "
     "sur l'écran pendant le traitement ; le lot reste ensuite re-téléchargeable depuis la liste des lots. "
     "Sur l'hébergement de production, le traitement se fait d'un seul tenant : si une erreur s'affiche, "
     "l'écran recharge le lot de lui-même. Un lot précédemment généré a pu passer en erreur : « Confirmer "
     "le paiement » n'est alors plus proposé et « Régénérer » reste disponible.")
capture("19_generation_mtn", "génération MTN : aperçu des bénéficiaires et lot généré, en attente de dépôt")

h2("9.2 Déposer puis confirmer")
etape("Déposez le fichier généré sur la plateforme de l'opérateur (hors application).")
etape("Une fois le dépôt réussi, revenez sur l'écran de génération et cliquez sur « Confirmer le paiement ».")
etape("Le lot et ses versements passent au statut CONFIRMÉ et sont horodatés. L'OM dont tout ce qui est dû est réglé passe au statut SOLDÉ (une tranche à 0 ne compte pas).")

h2("9.3 Régénérer ou signaler une erreur")
bullet("Tant qu'un lot n'est pas confirmé, vous pouvez le régénérer (« Régénérer le fichier »).")
bullet("En cas de problème de dépôt, cliquez sur « Marquer en erreur » : cela réautorise une nouvelle génération. "
       "Ses versements redeviennent EN ATTENTE et reprennent le téléphone et l'opérateur ACTUELS de "
       "l'agent : si le dépôt a échoué à cause d'un mauvais numéro, corrigez la fiche de l'agent, puis "
       "régénérez.")
bullet("Seul un lot au statut GÉNÉRÉ peut être confirmé : un lot en erreur doit d'abord être régénéré "
       "(on ne confirme jamais un fichier invalidé), et un lot confirmé est définitif.")
encadre("Lots complémentaires",
        "Un lot confirmé n'est jamais réécrit. Si un versement devient payable après coup (OM validé plus "
        "tard, agent passé d'un opérateur à l'autre, reliquat redevenu positif…), une nouvelle génération "
        "crée un lot COMPLÉMENTAIRE qui ne contient que ces nouveaux bénéficiaires : sur l'écran MTN ou Moov, "
        "l'aperçu les liste et le bouton devient « Générer le lot complémentaire ». Il est signalé par un "
        "badge « complémentaire n° 2, 3… » dans la liste des lots et son fichier porte le suffixe "
        "« _COMPL2 », « _COMPL3 »… Il se dépose et se confirme comme un lot ordinaire.")

h2("9.4 Suivi des paiements")
bullet("« Statuts » : synthèse des versements par opérateur et par statut.")
bullet("« Historique » : versements confirmés (avec le bénéficiaire), filtrables par période.")
bullet("« Réconciliation » : rappel du modèle manuel (aucun réimport de fichier opérateur).")

h2("9.5 Régularisations (trop-perçus)")
para("Quand un OM est amendé à la baisse après paiement de l'avance, son reliquat peut devenir négatif : "
     "l'agent a été trop payé. Ce trop-perçu ne part jamais en Mobile Money ; il est récupéré par retenue "
     "sur salaire, hors application. L'écran « Régularisations » (module Paiements) centralise ces cas.")
bullet("Le versement concerné bascule automatiquement au statut À RECOUVRER et sort du périmètre payable.")
etape("Ouvrez « Régularisations » : la liste des trop-perçus (à recouvrer / recouvrés) s'affiche, filtrable et paginée.")
etape("Cliquez sur « Document » pour télécharger le « document de retenue sur salaire » (PDF) à remettre à la RH ; il porte le montant à retenir, en chiffres et en toutes lettres, et le motif du dernier amendement.")
etape("Une fois la retenue appliquée par la RH, cliquez sur « Marquer recouvré » : le versement passe au statut RECOUVRÉ. Si l'avance de l'OM est payée, l'OM passe SOLDÉ.")
capture("47_regularisations", "régularisations : un trop-perçu à recouvrer")
para("Le rapport « Trop-perçus » (chapitre 11) donne la synthèse des montants à recouvrer et déjà recouvrés. "
     "La consultation est ouverte aux profils disposant de « Paiements — consulter » ; le constat de "
     "recouvrement requiert « Paiements — confirmer ».")

# =========================================================================
# 10. BARÈME
# =========================================================================
h1("10. Barème per diem")
bullet("Consultation : profils disposant de « Barème — consulter » (tous sauf le LECTEUR, par défaut).")
bullet("Modification des taux : réservée à l'ADMIN (« Barème — modifier »).")
para("Le détail complet des taux figure en Annexe B.")

# =========================================================================
# 11. RAPPORTS
# =========================================================================
h1("11. Rapports et états")
para("Les états sont alimentés par les versements confirmés.")
bullet("Rapport mensuel : synthèse par opérateur, par tranche et par statut. Un paiement est rangé dans le "
       "mois de sa confirmation, à l'heure du Bénin.")
bullet("Par agent, par opérateur, par zone, par imputation budgétaire.")
bullet("Trop-perçus : synthèse des régularisations (montants à recouvrer vs déjà recouvrés).")
bullet("Export Excel ou PDF (immédiat ou en arrière-plan pour les gros volumes).")
capture("26_rapport_mensuel", "état mensuel")

# =========================================================================
# 12. AUDIT
# =========================================================================
h1("12. Journal d'audit")
para("Le journal trace les actions sensibles : qui a fait quoi, quand, et depuis quelle adresse IP — "
     "connexions, retranscription des OM, contrôle/visa/validation et rejet des états, génération et "
     "confirmation des paiements, création/modification/suppression des barèmes, gestion des comptes. "
     "Accessible à l'ADMIN et au CONTROLEUR. Chaque entrée peut être consultée en détail.")
bullet("Filtres : par action, par entité (ordre de mission, état, lot, barème, utilisateur, session), "
       "et par plage de dates (Du / Au).")

# =========================================================================
# 13. ADMINISTRATION
# =========================================================================
h1("13. Administration")
para("Module réservé aux administrateurs.")
h2("13.1 Gestion des utilisateurs")
bullet("Créer, modifier, activer/désactiver, supprimer des comptes ; réinitialiser un mot de passe.")
bullet("Modifier le rôle d'un compte existant depuis la liste (action « Modifier le rôle ») : les profils "
       "système et les rôles personnalisés y sont proposés ; le changement révoque aussitôt les sessions "
       "actives de l'utilisateur et est tracé au journal d'audit.")
bullet("Ajuster les permissions d'un utilisateur au-delà de son profil (accorder / retirer une permission ; "
       "voir chapitre 2.2). Réservé au SUPER_ADMIN.")
bullet("Toute action sur un super administrateur est réservée à un super administrateur.")
bullet("Le dernier super administrateur actif ne peut être ni rétrogradé, ni désactivé, ni supprimé.")
bullet("On ne peut pas désactiver ou supprimer son propre compte.")
capture("31_gestion_utilisateurs", "gestion des utilisateurs")
encadre("Sécurité des sessions",
        "Lorsqu'un compte est désactivé, supprimé, change de rôle ou voit son mot de passe réinitialisé, "
        "ses sessions actives sont immédiatement révoquées.")
h2("13.2 Autres écrans d'administration")
bullet("Matrice des permissions : consultation par l'ADMIN ; édition à chaud par le SUPER_ADMIN — cocher / "
       "décocher les droits d'un profil, créer / renommer / supprimer des rôles personnalisés, réinitialiser "
       "un profil au défaut (voir chapitre 2.2).")
bullet("Signatures : identités et paraphes scannés du contrôleur, du DAF et du Directeur Général, "
       "imprimés sur l'état de paiement (voir chapitre 7.4).")
bullet("Paramètres (onglet Général) : nom de l'application (titre de l'onglet du navigateur, et préfixe de "
       "l'objet des e-mails) et part de l'avance (%) appliquée à la répartition avance / reliquat (70 % par "
       "défaut). La part ne vaut que pour les OM retranscrits après la modification : les missions déjà "
       "saisies gardent leurs montants et leur part. Le fuseau horaire (heure du Bénin) y est affiché pour "
       "information : il est réglé sur le serveur.")
para("Tous les réglages de l'écran Paramètres sont appliqués par le serveur dès l'enregistrement (au plus "
     "tard une minute après). Un réglage jamais enregistré garde le comportement par défaut ; une valeur "
     "hors limites est refusée.")
tableau(
    ["Onglet", "Réglage", "Effet"],
    [
        ["Paiements", "MTN / Moov activé", "Un opérateur désactivé n'est plus ouvert à la génération de fichiers : "
         "l'écran de génération l'indique et grise le bouton. L'aperçu reste consultable et les lots déjà "
         "générés restent confirmables."],
        ["Notifications", "Une bascule par notification", "Une notification désactivée n'est plus émise du tout, "
         "ni dans la cloche ni par e-mail."],
        ["Sécurité", "Durée de session (5 à 1440 min)", "Durée de validité d'une connexion ; au-delà, l'utilisateur "
         "doit se reconnecter (480 min par défaut)."],
        ["Sécurité", "Tentatives de connexion (3 à 20 par minute)", "Au-delà, la connexion est momentanément "
         "refusée : « Trop de tentatives en peu de temps » (5 par défaut)."],
        ["Sécurité", "Forcer HTTPS", "L'application refuse toute requête non chiffrée. À n'activer que si "
         "l'adresse de l'application commence déjà par https://."],
        ["Sécurité", "Journaliser les connexions", "Les connexions échouées sont aussi tracées au journal "
         "d'audit (e-mail saisi, motif) ; les connexions réussies le sont toujours."],
        ["Maintenance", "Mode maintenance", "Tous les utilisateurs voient l'écran « Maintenance en cours », sauf "
         "le super administrateur, qui peut continuer à travailler puis lever le mode."],
    ],
    largeurs=[2.6, 4.6, 9.6],
)
bullet("Année fiscale : l'exercice en cours est paramétrable — année (liste dynamique) et mois de début "
       "d'exercice. Par défaut, l'exercice coïncide avec l'année civile (début janvier) ; un mois de début "
       "différent produit un exercice à cheval, libellé « 2026-2027 ». À ce jour, ce réglage n'agit ni sur "
       "les montants, ni sur les rapports, ni sur les documents, qui se filtrent par dates.")
bullet("Configuration e-mail (SMTP) : un serveur renseigné remplace, dès l'e-mail suivant, celui du fichier "
       ".env du serveur (laissé vide, c'est le .env qui s'applique) ; l'adresse et le nom d'expéditeur aussi. "
       "Le bouton « Envoyer un e-mail de test » enregistre puis écrit à votre adresse : un échec affiche le "
       "message du serveur de messagerie. Le mot de passe est stocké chiffré et n'est jamais réaffiché en clair. "
       "L'écran liste les sept notifications envoyées (état à signer, état validé ou rejeté, OM validé ou "
       "rejeté, lot confirmé, trop-perçu créé) avec la permission de leurs destinataires.")
capture("51_config_email", "configuration e-mail : serveur SMTP, expéditeur et e-mail de test")
bullet("Sauvegardes : création, téléchargement, restauration et suppression des sauvegardes de la "
       "base de données (voir chapitre 13.3).")

h2("13.3 Sauvegardes et restauration")
para("Réservé au SUPER_ADMIN, écran « Sauvegardes ». Une sauvegarde automatique est faite chaque nuit "
     "(02:00) par le serveur ; le bouton « Nouvelle sauvegarde » en crée une immédiatement.")
bullet("La liste est classée de la plus récente à la plus ancienne.")
bullet("Télécharger : survolez une sauvegarde et cliquez sur l'icône de téléchargement pour en garder "
       "une copie hors du serveur.")
h3("Restaurer une sauvegarde")
etape("Survolez la sauvegarde voulue et cliquez sur l'icône « Restaurer ».")
etape("Lisez l'avertissement : la base actuelle sera entièrement remplacée.")
etape("Recopiez exactement le nom du fichier pour confirmer, puis cliquez sur « Restaurer ».")
encadre("Une restauration se défait",
        "Juste avant d'écraser la base, l'application crée automatiquement une sauvegarde de l'état "
        "courant. En cas d'erreur, il suffit de restaurer cette sauvegarde de sécurité, la plus récente de "
        "la liste. Toutes les données saisies après la date de la sauvegarde restaurée sont perdues : "
        "prévenez les utilisateurs et choisissez un moment sans activité. L'opération est tracée au "
        "journal d'audit.")
bullet("Après une restauration, la matrice des droits d'accès de la sauvegarde s'applique immédiatement, "
       "sans autre manipulation.")
encadre("Ce que la sauvegarde ne contient pas",
        "La sauvegarde porte sur la base de données. Les réglages de l'écran « Paramètres » (part de "
        "l'avance, exercice, messagerie), les paraphes de l'écran « Signatures » et les fichiers produits "
        "(PDF, fichiers de paiement) sont conservés sur le serveur, hors de la base : une restauration ne "
        "les modifie pas. Leur copie relève de l'hébergement (dossier « storage » du serveur).")

# =========================================================================
# ANNEXES
# =========================================================================
doc.add_page_break()
h1("Annexe A — Statuts")
h2("État de paiement (un par tranche : avances puis reliquats)")
tableau(["Statut", "Signification"],
        [["BROUILLON", "Récapitulatif établi, épreuve téléchargeable ; en attente du contrôle."],
         ["CONTROLE", "Contrôlé par le contrôleur ; en attente du visa du DAF."],
         ["VISA_DAF", "Visé par le DAF ; en attente de la validation du Directeur Général."],
         ["VALIDE", "Validé par le DG — verrouillé ; ouvre les fichiers de sa tranche."],
         ["REJETE", "Rejeté (avec motif) ; le récapitulatif peut être refait."]],
        largeurs=[4.0, 12.8])
h2("Ordre de mission")
tableau(["Statut", "Signification"],
        [["IMPORTE", "Retranscrit depuis SEMO ; statut conservé jusqu'à la validation de l'état des avances."],
         ["VERIFIE / VISA_DAF", "Valeurs héritées de l'ancien circuit par OM, qui n'existe plus : elles ne sont plus produites."],
         ["VALIDE", "L'état des avances de sa mission a été validé par le DG — payable."],
         ["REJETE", "Rejeté individuellement (reprise de données ; motif requis) ; ses versements sont annulés."],
         ["SOLDE", "Tout ce qui est dû est réglé : versements non nuls confirmés, trop-perçu recouvré, ou OM exonéré validé. Encore amendable si le reliquat n'est ni payé ni recouvré."]],
        largeurs=[4.0, 12.8])
h2("Versement")
tableau(["Statut", "Signification"],
        [["EN_ATTENTE", "Créé, pas encore inclus dans un fichier."],
         ["GENERE", "Inclus dans un fichier généré, non confirmé."],
         ["CONFIRME", "Paiement confirmé après dépôt."],
         ["ANNULE", "Versement d'un OM rejeté : ne sera jamais payé."],
         ["A_RECOUVRER", "Trop-perçu (reliquat négatif après amendement) : à récupérer par retenue sur salaire."],
         ["RECOUVRE", "Trop-perçu régularisé : la retenue sur salaire a été constatée."]],
        largeurs=[4.0, 12.8])
h2("Lot de paiement")
para("Un lot porte aussi un numéro de séquence : 1 pour le lot initial, 2, 3… pour les lots "
     "complémentaires créés après la confirmation du précédent.", italic=True, color=GRIS)
tableau(["Statut", "Signification"],
        [["GENERE", "Fichier généré, régénérable."],
         ["CONFIRME", "Paiement confirmé — définitif."],
         ["ERREUR", "Signalé en erreur — versements remis en attente, régénération autorisée."]],
        largeurs=[4.0, 12.8])

doc.add_page_break()
h1("Annexe B — Barème officiel (FCFA / jour)")
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
h2("Zones EXT_ZONE_II et EXT_ZONE_III (taux identiques)")
tableau(["Catégorie", "Journée complète", "Logé+nourri", "Logé seul", "Nourri seul"],
        [["INDICE_SUP_800", "105 000", "35 000", "55 000", "70 000"],
         ["INDICE_400_799", "85 000", "30 000", "45 000", "60 000"],
         ["INDICE_INF_400", "65 000", "25 000", "35 000", "45 000"]],
        largeurs=[3.3, 3.5, 3.3, 3.3, 3.3])
para("Note : ces montants sont ceux du barème par défaut et peuvent être ajustés par l'ADMIN dans le "
     "module Barème. Les zones EXT_ZONE_II et EXT_ZONE_III étant identiques, modifier un taux de l'une "
     "met automatiquement l'autre à jour. Référez-vous à l'écran Barème pour les valeurs en vigueur.",
     italic=True, color=GRIS)

doc.add_page_break()
h1("Annexe C — Glossaire")
tableau(["Terme", "Définition"],
        [["SEMO", "Service émetteur des ordres de mission (hors application)."],
         ["OM", "Ordre de mission : 1 enregistrement = 1 bénéficiaire."],
         ["CAV", "Conducteur de véhicule administratif, payé selon sa propre catégorie."],
         ["Per diem", "Indemnité journalière de mission."],
         ["Avance / Reliquat", "Tranches du per diem : 70 % puis le reste par défaut (part paramétrable)."],
         ["Lot", "1 mission × 1 tranche × 1 opérateur = 1 fichier de paiement."],
         ["Lot complémentaire", "Nouveau lot pour les versements devenus payables après la confirmation d'un lot."],
         ["MSISDN", "Numéro de téléphone au format international (229 + numéro)."],
         ["Exonération", "Prise en charge par un tiers : aucun frais alloué."],
         ["Amendement (avenant)", "Ajustement des jours facturés d'un OM après paiement de l'avance ; seul le reliquat bouge."],
         ["Trop-perçu", "Reliquat négatif après amendement à la baisse ; récupéré par retenue sur salaire."],
         ["Exercice / année fiscale", "Période budgétaire paramétrable (année + mois de début) ; année civile par défaut."]],
        largeurs=[4.2, 12.6])

doc.add_paragraph()
flag_bar()
fin = doc.add_paragraph(); fin.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = fin.add_run(f"© {date.today().year} ANaTT — Agence Nationale des Transports Terrestres — Bénin")
r.font.size = Pt(9); r.font.color.rgb = GRIS

doc.save(SORTIE)
print("Guide généré :", SORTIE)
