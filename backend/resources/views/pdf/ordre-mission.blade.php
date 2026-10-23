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
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding: 8px 10px 18px; }
        .sig-line { border-top: 1px solid #9ca3af; margin: 30px 10px 6px; }
        .sig-role { font-size: 8.5px; color: #6b7280; text-transform: uppercase; line-height: 1.35; }
        .sig-name { font-size: 10px; font-weight: bold; color: #1f2937; margin-top: 2px; }
        .sig-date { font-size: 8px; color: #6b7280; margin-top: 1px; }
        .sig-code { font-size: 7px; color: #9ca3af; letter-spacing: 0.5px; margin-top: 2px; }
        .sig-img { height: 42px; margin-bottom: -6px; }
        .sig-attente { font-size: 8.5px; color: #b45309; font-style: italic; margin-top: 30px; }
        .sig-box { min-height: 72px; }

        /* Le filigrane diagonal du brouillon n'est PAS posé ici : DomPDF ignore
           purement et simplement tout élément portant `transform: rotate()`.
           Il est tracé sur chaque page via l'API canvas (cf. PdfGenerator::apposerFiligrane). */
        .provisoire-bandeau { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C;
            border-radius: 4px; padding: 7px 10px; text-align: center; font-size: 10px;
            font-weight: bold; letter-spacing: 1px; margin-bottom: 12px; }

        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    @if($brouillon)
        <div class="provisoire-bandeau">
            DOCUMENT PROVISOIRE — SANS VALEUR D'AUTORISATION DE PAIEMENT
        </div>
    @endif

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
                    @if(!empty($lignes))
                        @foreach($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne['type'] }} — {{ $zone }}</td>
                                <td style="text-align:right">{{ $ligne['nb_jours'] }}</td>
                                <td style="text-align:right">{{ $ligne['taux'] }} FCFA</td>
                                <td style="text-align:right">{{ $ligne['montant'] }} FCFA</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>Per diem ({{ $type_prise_en_charge }} — {{ $zone }})</td>
                            <td style="text-align:right">{{ $nb_jours }}</td>
                            <td style="text-align:right">{{ $taux_journalier }} FCFA</td>
                            <td style="text-align:right">{{ $per_diem }} FCFA</td>
                        </tr>
                    @endif
                    @if($a_transport)
                        <tr>
                            <td>Transport</td>
                            <td style="text-align:right">—</td>
                            <td style="text-align:right">—</td>
                            <td style="text-align:right">{{ $montant_transport }} FCFA</td>
                        </tr>
                    @endif
                    <tr><td colspan="3">{{ $avance_libelle }}</td><td style="text-align:right">{{ $avance }} FCFA</td></tr>
                    <tr><td colspan="3">{{ $reliquat_libelle }}</td><td style="text-align:right">{{ $reliquat }} FCFA</td></tr>
                    <tr class="total-row"><td colspan="3">Total</td><td style="text-align:right">{{ $montant_total }} FCFA</td></tr>
                </tbody>
            </table>

            @if($amendement)
                <div class="exonere-box">
                    Montants ajustés par amendement du {{ $amendement['date'] }} : {{ $amendement['nb_jours_avant'] }} → {{ $amendement['nb_jours_apres'] }} jour(s)
                    @if($amendement['nombre'] > 1) ({{ $amendement['nombre'] }} amendements) @endif
                    — {{ $amendement['motif'] }}
                </div>
            @endif

            <div class="amount-box">
                <div class="label">{{ $trop_percu ? 'Montant total dû' : 'Montant total à payer' }}</div>
                <div class="amount">{{ $montant_total }} FCFA</div>
                <div class="details">{{ $nb_jours }} jour(s) × {{ $taux_journalier }} FCFA — {{ $categorie }} / {{ $zone }}</div>
            </div>
        @endif
    </div>

    {{-- Circuit de validation en trois étapes, dans l'ordre : le contrôleur
         vérifie, le DAF vise (« Vu »), le Directeur Général valide en dernier
         (verrouillage). Deux rangées de deux blocs : plus lisible que quatre
         colonnes sur A4 portrait. --}}
    @php
        // Titre imprimé : la fonction réelle du signataire (figée à la signature)
        // prime ; à défaut — ou avant signature — on retombe sur le libellé de
        // rôle générique.
        $blocs = [
            ['role' => 'Le bénéficiaire', 'signature' => null, 'nom_fixe' => $beneficiaire, 'attente' => null],
            ['role' => ($verification['fonction'] ?? null) ?: $libelle_controle, 'signature' => $verification, 'nom_fixe' => null, 'attente' => 'En attente de vérification'],
            ['role' => 'Vu<br>'.(($visa_daf['fonction'] ?? null) ?: $libelle_daf), 'signature' => $visa_daf, 'nom_fixe' => null, 'attente' => 'En attente de visa'],
            ['role' => (($valide_dg['fonction'] ?? null) ?: $libelle_dg).'<br>(validation finale)', 'signature' => $valide_dg, 'nom_fixe' => null, 'attente' => 'En attente de validation'],
        ];
    @endphp

    <table class="signatures">
        @foreach(array_chunk($blocs, 2) as $rangee)
            <tr>
                @foreach($rangee as $bloc)
                    <td>
                        <div class="sig-box">
                            @if($bloc['signature'] && $bloc['signature']['image'])
                                <img class="sig-img" src="{{ $bloc['signature']['image'] }}" alt="">
                            @elseif(! $bloc['signature'] && $bloc['attente'])
                                <div class="sig-attente">{{ $bloc['attente'] }}</div>
                            @endif
                            <div class="sig-line"></div>
                        </div>
                        <div class="sig-role">{!! $bloc['role'] !!}</div>
                        @if($bloc['nom_fixe'])
                            <div class="sig-name">{{ $bloc['nom_fixe'] }}</div>
                        @elseif($bloc['signature'])
                            <div class="sig-name">{{ $bloc['signature']['nom'] }}</div>
                            <div class="sig-date">Le {{ $bloc['signature']['date'] }}</div>
                            <div class="sig-code">Vérification : {{ $bloc['signature']['code'] }}</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="flag-bar" style="margin-top:20px"></div>
    <div class="footer">
        @if($brouillon)
            DOCUMENT PROVISOIRE — édité le {{ $date_generation }} | ANaTT — Réf. SEMO {{ $reference_semo }}
        @else
            Document généré le {{ $date_generation }} | ANaTT — Réf. SEMO {{ $reference_semo }}
        @endif
    </div>
</body>
</html>
