<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État de paiement {{ $numero ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }

        .provisoire { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C;
            padding: 5px 8px; text-align: center; font-size: 9px; font-weight: bold;
            letter-spacing: 1px; margin-bottom: 8px; }

        .titre { font-size: 12px; font-weight: bold; text-align: center;
            text-transform: uppercase; letter-spacing: 2px; color: #0F5C2E; margin: 6px 0 2px; }
        .numero { text-align: center; font-size: 9px; font-weight: bold; margin-bottom: 8px; }
        .numero .absent { color: #B91C1C; font-style: italic; font-weight: normal; }

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

        .signatures { width: 100%; margin-top: 14px; }
        .signatures td { width: 50%; vertical-align: top; padding: 4px 20px; }
        .sig-role { font-size: 8px; text-transform: uppercase; font-weight: bold; }
        .sig-img { height: 46px; margin: 2px 0 -4px; }
        .sig-line { border-top: 0.5px solid #9ca3af; margin: 34px 0 4px; width: 78%; }
        .sig-nom { font-size: 8.5px; font-weight: bold; }
        .sig-code { font-size: 6.5px; color: #9ca3af; letter-spacing: 0.4px; }
        .sig-attente { font-size: 8px; color: #b45309; font-style: italic; margin-top: 34px; }

        .flag-bar { height: 3px; margin-top: 10px;
            background: linear-gradient(90deg, #1D9E4F 0 33%, #D4A843 33% 66%, #CE1126 66% 100%); }
        .pied { margin-top: 4px; font-size: 6.5px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete')

    @if($brouillon)
        <div class="provisoire">DOCUMENT PROVISOIRE — SANS VALEUR D'AUTORISATION DE PAIEMENT</div>
    @endif

    <div class="titre">État de paiement</div>
    <div class="numero">
        @if($numero)
            N°{{ $numero }}
        @else
            <span class="absent">Numéro non attribué — à saisir avant la validation finale</span>
        @endif
    </div>

    <table class="entete-etat">
        <tr><td class="cle">ÉTAT DE PAIEMENT</td><td>: Frais de mission</td></tr>
        <tr><td class="cle">BÉNÉFICIAIRES</td><td>: {{ $nb_beneficiaires }} bénéficiaire(s)</td></tr>
        <tr><td class="cle">MOTIF</td><td>: {{ $objet ?: '—' }}</td></tr>
        <tr><td class="cle">PÉRIODE</td><td>: {{ $periode }}</td></tr>
        <tr><td class="cle">Ligne budgétaire</td><td>: {{ $imputation ?: '—' }}</td></tr>
    </table>

    {{-- Les 13 colonnes de l'état officiel. Chaque bénéficiaire occupe autant de
         lignes qu'il a de types de prise en charge ; les colonnes d'identité et
         de montants sont fusionnées verticalement sur ces lignes. --}}
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
                <th>Total</th>
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

    {{-- Mention d'arrêt sur le TOTAL GÉNÉRAL : avance + reliquat. --}}
    <div class="arret">
        Arrêté le présent état de paiement à la somme de : <strong>{{ $mention_arret }}</strong>.
    </div>

    {{-- Les deux signatures de l'état officiel : « Vu » du DAF, puis le
         Directeur Général en dernière position. --}}
    <table class="signatures">
        <tr>
            <td>
                <div class="sig-role">Vu<br>{{ $visa_daf['fonction'] ?? "Le Directeur de l'Administration et des Finances" }}</div>
                @if($visa_daf)
                    @if($visa_daf['image'])
                        <img class="sig-img" src="{{ $visa_daf['image'] }}" alt="">
                    @endif
                    <div class="sig-line"></div>
                    <div class="sig-nom">{{ $visa_daf['nom'] }}</div>
                    <div class="sig-code">Visé le {{ $visa_daf['date'] }} · Vérification {{ $visa_daf['code'] }}</div>
                @else
                    <div class="sig-attente">En attente du visa</div>
                    <div class="sig-line"></div>
                @endif
            </td>
            <td>
                <div class="sig-role">{{ $validation_dg['fonction'] ?? 'Le Directeur Général' }}</div>
                @if($validation_dg)
                    @if($validation_dg['image'])
                        <img class="sig-img" src="{{ $validation_dg['image'] }}" alt="">
                    @endif
                    <div class="sig-line"></div>
                    <div class="sig-nom">{{ $validation_dg['nom'] }}</div>
                    <div class="sig-code">Validé le {{ $validation_dg['date'] }} · Vérification {{ $validation_dg['code'] }}</div>
                @else
                    <div class="sig-attente">En attente de la validation finale</div>
                    <div class="sig-line"></div>
                @endif
            </td>
        </tr>
    </table>

    <div class="flag-bar"></div>
    <div class="pied">
        @if($brouillon)
            DOCUMENT PROVISOIRE — édité le {{ $date_generation }}
        @else
            ANaTT — État de paiement{{ $numero ? ' N°'.$numero : '' }} — édité le {{ $date_generation }}
            @if($controle) · Contrôlé par {{ $controle['nom'] }} le {{ $controle['date'] }} @endif
        @endif
    </div>
</body>
</html>
