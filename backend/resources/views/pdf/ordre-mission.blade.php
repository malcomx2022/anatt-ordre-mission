<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordre de Mission {{ $reference_semo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }

        .flag-bar { height: 4px; background: linear-gradient(90deg, #1D9E4F 0%, #1D9E4F 33%, #D4A843 33%, #D4A843 66%, #CE1126 66%, #CE1126 100%); margin-bottom: 20px; }

        .doc-title { text-align: center; margin: 16px 0; }
        .doc-title h1 { font-size: 18px; color: #0F5C2E; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 5px; }
        .doc-title .ref { font-size: 13px; color: #1D9E4F; font-weight: bold; }
        .badge { display: inline-block; font-size: 9px; font-weight: bold; padding: 2px 8px; border-radius: 10px; background: #EDFAF2; color: #14713A; }

        .section { margin-bottom: 16px; }
        .section-title { font-size: 11px; font-weight: bold; color: #0F5C2E; text-transform: uppercase; letter-spacing: 1px; padding: 6px 10px; background: #EDFAF2; border-left: 3px solid #1D9E4F; margin-bottom: 8px; }

        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 5px 10px; vertical-align: top; width: 50%; }
        .info-grid .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .value { font-size: 11px; font-weight: bold; color: #1f2937; }

        .split-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .split-table th { font-size: 9px; text-transform: uppercase; color: #6b7280; padding: 8px 10px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .split-table td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        .split-table .total-row td { font-weight: bold; background: #EDFAF2; color: #0F5C2E; border-top: 2px solid #1D9E4F; }

        .amount-box { background: #EDFAF2; border: 1px solid #C5F0D5; border-radius: 4px; padding: 12px; text-align: center; margin: 12px 0; }
        .amount-box .label { font-size: 10px; color: #14713A; margin-bottom: 3px; }
        .amount-box .amount { font-size: 22px; font-weight: bold; color: #0F5C2E; }
        .amount-box .details { font-size: 9px; color: #6b7280; margin-top: 3px; }
        .exonere-box { background: #FBF3E0; border: 1px solid #EBCF8A; color: #6B5010; border-radius: 4px; padding: 10px; text-align: center; font-size: 11px; font-weight: bold; margin: 12px 0; }

        .signatures { width: 100%; margin-top: 28px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding: 10px; }
        .sig-line { border-top: 1px solid #9ca3af; margin: 30px 16px 6px; }
        .sig-role { font-size: 9px; color: #6b7280; text-transform: uppercase; }

        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    <div class="doc-title">
        <h1>Ordre de Mission</h1>
        <div class="ref">Réf. SEMO {{ $reference_semo }}</div>
        <div style="margin-top:6px"><span class="badge">{{ $role }}</span> &nbsp; <span class="badge">{{ $statut }}</span></div>
    </div>

    <div class="section">
        <div class="section-title">Bénéficiaire</div>
        <table class="info-grid">
            <tr>
                <td><div class="label">Nom & prénoms</div><div class="value">{{ $beneficiaire }}</div></td>
                <td><div class="label">Matricule</div><div class="value">{{ $matricule }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Grade</div><div class="value">{{ $grade ?: '—' }}</div></td>
                <td><div class="label">Catégorie d'indice</div><div class="value">{{ $categorie }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Téléphone (Mobile Money)</div><div class="value">{{ $telephone }} ({{ $operateur }})</div></td>
                <td><div class="label">Référence autorisation</div><div class="value">{{ $reference_autorisation ?: '—' }}</div></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Mission</div>
        <table class="info-grid">
            <tr>
                <td><div class="label">Objet / thème</div><div class="value">{{ $objet ?: '—' }}</div></td>
                <td><div class="label">Destination</div><div class="value">{{ $destination ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Zone (barème)</div><div class="value">{{ $zone }}</div></td>
                <td><div class="label">Prise en charge</div><div class="value">{{ $type_prise_en_charge }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Départ</div><div class="value">{{ $date_depart ?: '—' }}</div></td>
                <td><div class="label">Retour</div><div class="value">{{ $date_retour ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Durée</div><div class="value">{{ $nb_jours }} jour(s)</div></td>
                <td><div class="label">Imputation budgétaire</div><div class="value">{{ $imputation ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Moyen de transport</div><div class="value">{{ $moyen_transport ?: '—' }}</div></td>
                <td><div class="label">Conducteur à bord (mention)</div><div class="value">{{ $conducteur_a_bord ?: '—' }}</div></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Indemnité de mission (per diem)</div>

        @if($exonere)
            <div class="exonere-box">Mission exonérée — prise en charge par un organisme tiers (aucun frais alloué).</div>
        @else
            <table class="split-table">
                <thead>
                    <tr><th>Désignation</th><th style="text-align:right">Nb jours</th><th style="text-align:right">Taux journalier</th><th style="text-align:right">Montant</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Per diem ({{ $type_prise_en_charge }} — {{ $zone }})</td>
                        <td style="text-align:right">{{ $nb_jours }}</td>
                        <td style="text-align:right">{{ $taux_journalier }} FCFA</td>
                        <td style="text-align:right">{{ $montant_total }} FCFA</td>
                    </tr>
                    <tr><td colspan="3">Avance (33,33 %)</td><td style="text-align:right">{{ $avance }} FCFA</td></tr>
                    <tr><td colspan="3">Reliquat (66,67 %)</td><td style="text-align:right">{{ $reliquat }} FCFA</td></tr>
                    <tr class="total-row"><td colspan="3">Total</td><td style="text-align:right">{{ $montant_total }} FCFA</td></tr>
                </tbody>
            </table>

            <div class="amount-box">
                <div class="label">Montant total à payer</div>
                <div class="amount">{{ $montant_total }} FCFA</div>
                <div class="details">{{ $nb_jours }} jour(s) × {{ $taux_journalier }} FCFA — Catégorie {{ $categorie }} / {{ $zone }}</div>
            </div>
        @endif
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-role">Le bénéficiaire</div>
                <div class="value">{{ $beneficiaire }}</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-role">L'autorité compétente</div>
            </td>
        </tr>
    </table>

    <div class="flag-bar" style="margin-top:20px"></div>
    <div class="footer">Document généré le {{ $date_generation }} | ANaTT — Réf. SEMO {{ $reference_semo }}</div>
</body>
</html>
