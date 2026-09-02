<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Document de retenue {{ $reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }

        .doc-title { text-align: center; margin: 18px 0 6px; }
        .doc-title h1 { font-size: 17px; color: #0F5C2E; text-transform: uppercase; letter-spacing: 2px; }
        .doc-title .sub { font-size: 11px; color: #6b7280; margin-top: 3px; }
        .doc-title .ref { font-size: 12px; color: #1D9E4F; font-weight: bold; margin-top: 4px; }

        .badge { display: inline-block; font-size: 9px; font-weight: bold; padding: 3px 10px; border-radius: 10px; }
        .badge-recouvrer { background: #FDECEC; color: #B91C1C; }
        .badge-recouvre { background: #EDFAF2; color: #14713A; }

        .section { margin: 14px 0; }
        .section-title { font-size: 11px; font-weight: bold; color: #0F5C2E; text-transform: uppercase; letter-spacing: 1px; padding: 6px 10px; background: #EDFAF2; border-left: 3px solid #1D9E4F; margin-bottom: 8px; }

        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 5px 10px; vertical-align: top; width: 50%; }
        .info-grid .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .value { font-size: 11px; font-weight: bold; color: #1f2937; }

        .split-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .split-table th { font-size: 9px; text-transform: uppercase; color: #6b7280; padding: 8px 10px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .split-table td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        .split-table .num { text-align: right; }
        .split-table .total-row td { font-weight: bold; background: #FDECEC; color: #B91C1C; border-top: 2px solid #CE1126; }

        .amount-box { background: #FDECEC; border: 1px solid #F5C2C2; border-radius: 4px; padding: 12px; text-align: center; margin: 12px 0; }
        .amount-box .label { font-size: 10px; color: #B91C1C; margin-bottom: 3px; }
        .amount-box .amount { font-size: 22px; font-weight: bold; color: #991B1B; }
        .amount-box .details { font-size: 9px; color: #6b7280; margin-top: 3px; }

        .note { font-size: 9.5px; color: #6b7280; font-style: italic; margin-top: 6px; }

        .signatures { width: 100%; margin-top: 34px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding: 8px 10px 18px; }
        .sig-line { border-top: 1px solid #9ca3af; margin: 34px 10px 6px; }
        .sig-role { font-size: 8.5px; color: #6b7280; text-transform: uppercase; line-height: 1.35; }

        .footer { margin-top: 26px; }
        .footer .gen { font-size: 8px; color: #9ca3af; text-align: center; margin-top: 6px; }
        .flag-bar { height: 4px; background: linear-gradient(90deg, #1D9E4F 0%, #1D9E4F 33%, #D4A843 33%, #D4A843 66%, #CE1126 66%, #CE1126 100%); }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    <div class="doc-title">
        <h1>Document de retenue sur salaire</h1>
        <div class="sub">Régularisation d'un trop-perçu sur ordre de mission</div>
        <div class="ref">
            {{ $reference }}
            @if($recouvre)
                <span class="badge badge-recouvre">Recouvré le {{ $date_recouvrement }}</span>
            @else
                <span class="badge badge-recouvrer">À recouvrer</span>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Bénéficiaire</div>
        <table class="info-grid">
            <tr>
                <td><div class="label">Nom & prénoms</div><div class="value">{{ $beneficiaire }}</div></td>
                <td><div class="label">Matricule</div><div class="value">{{ $matricule ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Grade</div><div class="value">{{ $grade ?: '—' }}</div></td>
                <td><div class="label">Catégorie</div><div class="value">{{ $categorie }}</div></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Mission concernée</div>
        <table class="info-grid">
            <tr>
                <td><div class="label">Référence SEMO</div><div class="value">{{ $reference_semo }}</div></td>
                <td><div class="label">Destination</div><div class="value">{{ $destination ?: '—' }}</div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="label">Objet</div><div class="value">{{ $objet ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Période initiale</div><div class="value">{{ $date_depart ?? '—' }} → {{ $date_retour ?? '—' }}</div></td>
                <td><div class="label">Avance déjà engagée</div><div class="value">{{ $avance_engagee }} FCFA</div></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Origine du trop-perçu</div>
        @if($a_amendement)
            <table class="split-table">
                <thead>
                    <tr><th>Amendement</th><th class="num">Jours</th><th class="num">Montant dû</th></tr>
                </thead>
                <tbody>
                    <tr><td>Avant amendement</td><td class="num">{{ $nb_jours_avant }}</td><td class="num">{{ $montant_avant }}</td></tr>
                    <tr><td>Après amendement</td><td class="num">{{ $nb_jours_apres }}</td><td class="num">{{ $montant_apres }}</td></tr>
                    <tr class="total-row"><td>Avance engagée (figée)</td><td class="num">—</td><td class="num">{{ $avance_engagee }}</td></tr>
                </tbody>
            </table>
            <div class="note">
                Motif de l'amendement : « {{ $motif }} »
                @if($amende_par) — {{ $amende_par }}@endif
                @if($amende_le) le {{ $amende_le }}@endif.
            </div>
        @else
            <div class="note">
                Trop-perçu constaté : l'avance engagée ({{ $avance_engagee }} FCFA) dépasse le
                montant dû recalculé ({{ $montant_total }} FCFA).
            </div>
        @endif
    </div>

    <div class="amount-box">
        <div class="label">Montant à récupérer par retenue sur salaire</div>
        <div class="amount">{{ $trop_percu }} FCFA</div>
        <div class="details">Avance engagée {{ $avance_engagee }} − montant dû {{ $montant_total }} = trop-perçu</div>
    </div>

    <div class="note">
        Ce document ne réalise aucune retenue et n'ordonne aucun décaissement : il formalise, à
        l'attention du service en charge de la paie, le montant à recouvrer. La retenue effective
        relève de la procédure RH ; son application est ensuite constatée dans l'application
        (statut « recouvré »).
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-role">{{ $libelle_daf }}</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-role">Le service de la paie (RH)<br>Retenue appliquée le ……… / ……… / …………</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <div class="flag-bar"></div>
        <div class="gen">Document généré le {{ $date_generation }} — ANaTT, Agence Nationale des Transports Terrestres, République du Bénin.</div>
    </div>
</body>
</html>
