{{--
    En-tête officiel ANaTT mutualisé pour TOUS les PDF de l'application.
    Mise en page : bandeau logo à gauche + bloc contact à droite, séparés du
    contenu par la barre tricolore. Source : docs/entete anatt.docx.

    Le logo est embarqué en base64 via App\Support\PdfAssets (aucun chemin disque).
    Compatible DomPDF (table + valign pour l'alignement des deux colonnes).
--}}
@php($enteteLogo = \App\Support\PdfAssets::enteteDataUri())
@php($contact = \App\Support\PdfAssets::CONTACT)

<table class="entete-officielle" style="width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        <td style="vertical-align:middle; width:58%;">
            @if($enteteLogo !== '')
                <img src="{{ $enteteLogo }}" alt="ANaTT — Agence Nationale des Transports Terrestres" style="height:58px;">
            @else
                <div style="font-size:14px; font-weight:bold; color:#0F5C2E;">ANaTT — Agence Nationale des Transports Terrestres</div>
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; letter-spacing:1px;">République du Bénin</div>
            @endif
        </td>
        <td style="vertical-align:middle; text-align:right; font-size:8.5px; color:#4b5563; line-height:1.55;">
            <div>{{ $contact['bp'] }}</div>
            <div>Tél : {{ implode(' / ', $contact['tel']) }}</div>
            <div>{{ $contact['email'] }}</div>
            <div>{{ $contact['adresse'] }}</div>
        </td>
    </tr>
</table>
<div class="flag-bar" style="height:4px; margin:0 0 16px;"></div>
