<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État général de paiement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }

        .titre { font-size: 12px; font-weight: bold; text-align: center;
            text-transform: uppercase; letter-spacing: 2px; color: #0F5C2E; margin: 6px 0 2px; }
        .numero { text-align: center; font-size: 9px; font-weight: bold; margin-bottom: 8px; }

        .entete-etat { width: 100%; margin-bottom: 8px; }
        .entete-etat td { padding: 1px 0; vertical-align: top; font-size: 8.5px; }
        .entete-etat .cle { width: 130px; font-weight: bold; }

        table.grille { width: 100%; border-collapse: collapse; }
        table.grille th, table.grille td { border: 0.5px solid #6b7280; padding: 2px 3px; }
        table.grille th { background: #EDFAF2; font-size: 7px; text-transform: uppercase;
            text-align: center; font-weight: bold; }
        table.grille td { font-size: 7.5px; vertical-align: middle; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .totaux td { font-weight: bold; background: #EDFAF2; }

        .arret { margin-top: 8px; font-size: 8.5px; }

        .references { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .references th, .references td { border: 0.5px solid #6b7280; padding: 3px 5px;
            font-size: 8px; text-align: left; }
        .references th { background: #EDFAF2; text-transform: uppercase; font-size: 7px; }

        .flag-bar { height: 3px; margin-top: 10px;
            background: linear-gradient(90deg, #1D9E4F 0 33%, #D4A843 33% 66%, #CE1126 66% 100%); }
        .pied { margin-top: 4px; font-size: 6.5px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    <div class="titre">État général de paiement — avances et reliquats</div>
    <div class="numero">
        @foreach($references as $ref)
            {{ $ref['numero'] ? 'N°'.$ref['numero'] : 'N° non attribué' }}@if(!$loop->last) &nbsp;·&nbsp; @endif
        @endforeach
    </div>

    <table class="entete-etat">
        <tr><td class="cle">ÉTAT GÉNÉRAL DE PAIEMENT</td><td>: Frais de mission</td></tr>
        <tr><td class="cle">BÉNÉFICIAIRES</td><td>: {{ $nb_beneficiaires }} bénéficiaire(s)</td></tr>
        <tr><td class="cle">MOTIF</td><td>: {{ $objet ?: '—' }}</td></tr>
        <tr><td class="cle">PÉRIODE</td><td>: {{ $periode }}</td></tr>
        <tr><td class="cle">Ligne budgétaire</td><td>: {{ $imputation ?: '—' }}</td></tr>
    </table>

    {{-- Même tableau que l'état par tranche, mais les DEUX colonnes de tranche
         sont remplies : chacune provient de l'instantané scellé de son état. --}}
    <table class="grille">
        <thead>
            <tr>
                <th>N°</th>
                <th>Nom &amp; prénoms</th>
                <th>Structure</th>
                <th>Grade/<br>indice</th>
                <th>Lieu de<br>mission</th>
                <th>Motif</th>
                <th>Taux/jour</th>
                <th>Nbre de<br>jours</th>
                <th>Total<br>partiel</th>
                <th>Total<br>général</th>
                <th>Avance</th>
                <th>Reliquat</th>
                <th>Contact</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $ligne)
                @php($n = max(1, count($ligne['detail'])))
                @foreach($ligne['detail'] as $i => $d)
                    <tr>
                        @if($i === 0)
                            <td class="ctr" rowspan="{{ $n }}">{{ $ligne['numero'] }}</td>
                            <td rowspan="{{ $n }}">{{ $ligne['beneficiaire'] }}</td>
                            <td class="ctr" rowspan="{{ $n }}">{{ $ligne['structure'] }}</td>
                            <td class="ctr" rowspan="{{ $n }}">{{ $ligne['grade_indice'] }}</td>
                            <td class="ctr" rowspan="{{ $n }}">{{ $destination ?: '—' }}</td>
                        @endif
                        <td>{{ $d['motif'] }}</td>
                        <td class="num">{{ $d['taux'] }}</td>
                        <td class="ctr">{{ $d['nb_jours'] }}</td>
                        <td class="num">{{ $d['partiel'] }}</td>
                        @if($i === 0)
                            <td class="num" rowspan="{{ $n }}">{{ $ligne['total'] }}</td>
                            <td class="num" rowspan="{{ $n }}">{{ $ligne['avance'] }}</td>
                            <td class="num" rowspan="{{ $n }}">{{ $ligne['reliquat'] }}</td>
                            <td class="ctr" rowspan="{{ $n }}">{{ $ligne['contact'] }}</td>
                        @endif
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="13" class="ctr">Aucun bénéficiaire dans cet état.</td></tr>
            @endforelse

            <tr class="totaux">
                <td colspan="9" class="num">TOTAL</td>
                <td class="num">{{ $total }}</td>
                <td class="num">{{ $total_avance }}</td>
                <td class="num">{{ $total_reliquat }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Mention d'arrêt sur le total général (avances + reliquats). --}}
    <div class="arret">
        Arrêté le présent état général de paiement à la somme de : <strong>{{ $mention_arret }}</strong>.
    </div>

    {{-- Pas de nouvelle signature : l'état général consolide deux documents
         déjà arrêtés ; il cite leur registre, leur validation et leur signataire. --}}
    <table class="references">
        <thead>
            <tr>
                <th>Document consolidé</th>
                <th>N° registre</th>
                <th>Validation finale</th>
                <th>Signataire</th>
            </tr>
        </thead>
        <tbody>
            @foreach($references as $ref)
                <tr>
                    <td>{{ $ref['libelle'] }}</td>
                    <td>{{ $ref['numero'] ?? '—' }}</td>
                    <td>{{ $ref['valide_le'] ?? '—' }}</td>
                    <td>{{ $ref['signataire'] ?? '—' }}{{ $ref['fonction'] ? ', '.$ref['fonction'] : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flag-bar"></div>
    <div class="pied">
        ANaTT — État général de paiement (avances + reliquats) — édité le {{ $date_generation }}
    </div>
</body>
</html>
