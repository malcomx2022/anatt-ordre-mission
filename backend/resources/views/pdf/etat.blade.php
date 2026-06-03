<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #0F172A; }
        .entete { margin-bottom: 16px; }
        .entete h1 { font-size: 16px; color: #14713A; margin: 0; }
        .entete .meta { font-size: 10px; color: #6B7280; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0F5C2E; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #E5E7EB; }
        tr:nth-child(even) td { background: #EDFAF2; }
        .montant { text-align: right; }
        .flag-bar { height: 3px; margin-top: 16px;
            background: linear-gradient(90deg, #1D9E4F 0 33%, #D4A843 33% 66%, #CE1126 66% 100%); }
        .footer { margin-top: 6px; font-size: 9px; color: #9CA3AF; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    <div class="entete">
        <h1>{{ $titre }}</h1>
        <div class="meta">
            @if(!empty($periode['date_debut']) || !empty($periode['date_fin']))
                Période : {{ $periode['date_debut'] ?? '—' }} au {{ $periode['date_fin'] ?? '—' }} ·
            @endif
            Généré le {{ $genere_le }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($entetes as $i => $entete)
                    <th @if($i > 0) class="montant" @endif>{{ $entete }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $ligne)
                <tr>
                    @foreach($ligne as $i => $cellule)
                        <td @if($i > 0) class="montant" @endif>
                            {{ $i === count($ligne) - 1 && is_numeric($cellule) ? number_format((int) $cellule, 0, ',', ' ') : $cellule }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($entetes) }}">Aucune donnée confirmée pour cette période.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="flag-bar"></div>
    <div class="footer">ANaTT — Agence Nationale des Transports Terrestres · État de paiement</div>
</body>
</html>
