<?php

namespace App\Support;

/**
 * Assets partagés pour les documents PDF (DomPDF).
 *
 * Le logo officiel (bandeau armoiries + ANaTT + tricolore + « République du Bénin »)
 * est embarqué en data-URI base64 : DomPDF n'a alors aucune dépendance à un chemin
 * disque/réseau et le rendu est identique en CLI, en file (queue) et en HTTP.
 *
 * Source : docs/entete anatt.docx (en-tête officiel ANaTT).
 */
class PdfAssets
{
    private static ?string $enteteDataUri = null;

    /** Chemin du bandeau d'en-tête officiel embarqué. */
    private const ENTETE_LOGO = 'views/pdf/images/entete-anatt.jpg';

    /** Coordonnées de l'en-tête officiel (en-tête du docx ANaTT). */
    public const CONTACT = [
        'bp' => '01 BP 1230 Cotonou',
        'tel' => ['+229 01 91 85 11 11', '+229 01 21 31 39 98'],
        'email' => 'anatt.contact@gouv.bj',
        'adresse' => 'Rue 108 - Tokpa Hoho, Lot 35 B - Immeuble KOUGBLENOU',
    ];

    /**
     * Data-URI base64 du bandeau d'en-tête officiel (mémoïsé pour le process).
     * Retourne une chaîne vide si le fichier est introuvable (le template
     * dégrade alors proprement sans casser la génération).
     */
    public static function enteteDataUri(): string
    {
        if (self::$enteteDataUri !== null) {
            return self::$enteteDataUri;
        }

        $chemin = resource_path(self::ENTETE_LOGO);

        if (! is_file($chemin)) {
            return self::$enteteDataUri = '';
        }

        return self::$enteteDataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($chemin));
    }
}
