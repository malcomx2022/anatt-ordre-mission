<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordre de Mission {{ $numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }

        .flag-bar { height: 4px; background: linear-gradient(90deg, #1D9E4F 0%, #1D9E4F 33%, #D4A843 33%, #D4A843 66%, #CE1126 66%, #CE1126 100%); margin-bottom: 20px; }

        .header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #1D9E4F; }
        .header-country { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 3px; }
        .header-org { font-size: 14px; font-weight: bold; color: #1D9E4F; margin-bottom: 2px; }
        .header-sub { font-size: 9px; color: #9ca3af; }

        .doc-title { text-align: center; margin: 20px 0; }
        .doc-title h1 { font-size: 18px; color: #0F5C2E; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 5px; }
        .doc-title .ref { font-size: 13px; color: #1D9E4F; font-weight: bold; }

        .section { margin-bottom: 18px; }
        .section-title { font-size: 11px; font-weight: bold; color: #0F5C2E; text-transform: uppercase; letter-spacing: 1px; padding: 6px 10px; background: #EDFAF2; border-left: 3px solid #1D9E4F; margin-bottom: 10px; }

        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 6px 10px; vertical-align: top; }
        .info-grid .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .value { font-size: 11px; font-weight: bold; color: #1f2937; }

        .amount-box { background: #EDFAF2; border: 1px solid #C5F0D5; border-radius: 4px; padding: 15px; text-align: center; margin: 15px 0; }
        .amount-box .label { font-size: 10px; color: #14713A; margin-bottom: 3px; }
        .amount-box .amount { font-size: 22px; font-weight: bold; color: #0F5C2E; }
        .amount-box .details { font-size: 9px; color: #6b7280; margin-top: 3px; }

        .split-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .split-table th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 8px 10px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .split-table td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        .split-table .total-row td { font-weight: bold; background: #EDFAF2; color: #0F5C2E; border-top: 2px solid #1D9E4F; }

        .signatures { width: 100%; margin-top: 30px; }
        .signatures td { width: 33%; text-align: center; vertical-align: top; padding: 10px; }
        .signatures .sig-title { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .signatures .sig-name { font-size: 10px; font-weight: bold; color: #1f2937; }
        .signatures .sig-date { font-size: 9px; color: #9ca3af; }
        .signatures .sig-line { border-top: 1px solid #d1d5db; margin: 30px 20px 5px 20px; }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; padding: 10px; font-size: 8px; color: #9ca3af; }
        .footer-bar { height: 3px; background: linear-gradient(90deg, #1D9E4F 0%, #1D9E4F 33%, #D4A843 33%, #D4A843 66%, #CE1126 66%, #CE1126 100%); margin-bottom: 8px; }

        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 60px; color: rgba(29, 158, 79, 0.06); font-weight: bold; letter-spacing: 10px; z-index: -1; }
    </style>
</head>
<body>
    <div class="watermark">ANaTT</div>

    <!-- Flag bar -->
    <div class="flag-bar"></div>

    <!-- Header avec logo ANaTT -->
    <div class="header">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:60px; vertical-align:middle; border:none; padding:0;">
                    <!-- Armoiries du Benin (SVG inline pour DomPDF) -->
                    <svg width="50" height="50" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 6h24v18c0 6-12 12-12 12S8 30 8 24V6z" fill="#CE1126" opacity="0.15"/>
                        <path d="M8 6h12v18c0 6-12 12-12 12V6z" fill="#1D9E4F" opacity="0.2"/>
                        <path d="M20 6h12v18c0 6-12 12-12 12V6z" fill="#D4A843" opacity="0.2"/>
                        <path d="M20 12l2 4 4.5.7-3.3 3.2.8 4.6L20 22.3l-4 2.2.8-4.6-3.3-3.2 4.5-.7z" fill="#1D9E4F"/>
                        <rect x="8" y="6" width="24" height="30" rx="2" stroke="#1D9E4F" stroke-width="1.5" fill="none"/>
                    </svg>
                </td>
                <td style="vertical-align:middle; border:none; padding-left:10px;">
                    <div style="font-size:18px; font-weight:bold; color:#0F5C2E; letter-spacing:1px;">ANaTT</div>
                    <div style="font-size:9px; color:#374151; text-transform:uppercase; letter-spacing:1px; line-height:1.3;">Agence Nationale des Transports Terrestres</div>
                    <div style="height:3px; margin-top:4px; background:linear-gradient(90deg, #1D9E4F 0%, #1D9E4F 33%, #D4A843 33%, #D4A843 66%, #CE1126 66%, #CE1126 100%); width:150px;"></div>
                    <div style="font-size:8px; color:#6b7280; margin-top:2px;">Republique du Benin</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Document Title -->
    <div class="doc-title">
        <h1>Ordre de Mission</h1>
        <div class="ref">N&deg; {{ $numero }}</div>
    </div>

    <!-- Agent Info -->
    <div class="section">
        <div class="section-title">Informations de l'Agent</div>
        <table class="info-grid">
            <tr>
                <td style="width:50%">
                    <div class="label">Nom et Prenom</div>
                    <div class="value">{{ $agent->prenom }} {{ $agent->nom }}</div>
                </td>
                <td>
                    <div class="label">Matricule</div>
                    <div class="value">{{ $agent->matricule }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Service</div>
                    <div class="value">{{ $agent->service }}</div>
                </td>
                <td>
                    <div class="label">Categorie</div>
                    <div class="value">{{ $agent->categorie }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Telephone</div>
                    <div class="value">{{ $agent->telephone }}</div>
                </td>
                <td>
                    <div class="label">Email</div>
                    <div class="value">{{ $agent->email }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Mission Details -->
    <div class="section">
        <div class="section-title">Details de la Mission</div>
        <table class="info-grid">
            <tr>
                <td colspan="2">
                    <div class="label">Objet de la mission</div>
                    <div class="value">{{ $objet }}</div>
                </td>
            </tr>
            <tr>
                <td style="width:50%">
                    <div class="label">Destination</div>
                    <div class="value">{{ $destination }}</div>
                </td>
                <td>
                    <div class="label">Type de destination</div>
                    <div class="value">{{ $taux->destination_type }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Date de depart</div>
                    <div class="value">{{ $date_depart }}</div>
                </td>
                <td>
                    <div class="label">Date de retour</div>
                    <div class="value">{{ $date_retour }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Duree</div>
                    <div class="value">{{ $nb_jours }} jour(s)</div>
                </td>
                <td>
                    <div class="label">Taux journalier</div>
                    <div class="value">{{ number_format($taux->montant_journalier, 0, ',', ' ') }} FCFA</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Per Diem Calculation -->
    <div class="section">
        <div class="section-title">Calcul du Per Diem</div>
        <table class="split-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:center">Quantite</th>
                    <th style="text-align:right">Taux unitaire</th>
                    <th style="text-align:right">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Per diem journalier ({{ $taux->destination_type }})</td>
                    <td style="text-align:center">{{ $nb_jours }} jour(s)</td>
                    <td style="text-align:right">{{ number_format($taux->montant_journalier, 0, ',', ' ') }} FCFA</td>
                    <td style="text-align:right">{{ $montant_per_diem }} FCFA</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" style="text-align:right">TOTAL PER DIEM</td>
                    <td style="text-align:right; font-size: 13px;">{{ $montant_per_diem }} FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="amount-box">
            <div class="label">MONTANT TOTAL DU PER DIEM</div>
            <div class="amount">{{ $montant_per_diem }} FCFA</div>
            <div class="details">{{ $nb_jours }} jour(s) x {{ number_format($taux->montant_journalier, 0, ',', ' ') }} FCFA - Categorie {{ $agent->categorie }} / {{ $taux->destination_type }}</div>
        </div>
    </div>

    <!-- Signatures -->
    <div class="section">
        <div class="section-title">Signatures et Validations</div>
        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-title">Le Demandeur</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $agent->prenom }} {{ $agent->nom }}</div>
                    <div class="sig-date">{{ $date_generation }}</div>
                </td>
                <td>
                    <div class="sig-title">Le Chef de Service</div>
                    <div class="sig-line"></div>
                    @if($valideur_chef)
                        <div class="sig-name">{{ $valideur_chef }}</div>
                        <div class="sig-date">Valide</div>
                    @else
                        <div class="sig-name" style="color:#9ca3af">En attente</div>
                    @endif
                </td>
                <td>
                    <div class="sig-title">Le Directeur General</div>
                    <div class="sig-line"></div>
                    @if($valideur_dg)
                        <div class="sig-name">{{ $valideur_dg }}</div>
                        <div class="sig-date">Approuve</div>
                    @else
                        <div class="sig-name" style="color:#9ca3af">En attente</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-bar"></div>
        ANaTT - Agence Nationale des Transports Terrestres | Republique du Benin<br>
        Document genere le {{ $date_generation }} | Ref: {{ $numero }}
    </div>
</body>
</html>
