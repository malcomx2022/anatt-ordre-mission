<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Téléphonie mobile (Bénin)
    |--------------------------------------------------------------------------
    | Numérotation nationale à 10 chiffres, débutant par « 01 ».
    | La cohérence numéro ↔ opérateur s'appuie sur les deux chiffres
    | suivant « 01 » (positions 3 et 4 du numéro).
    |
    | ⚠️ Tranches indicatives ANaTT — ajustables selon l'attribution ARCEP
    |    en vigueur, sans modifier le code applicatif.
    */

    'telephone' => [
        // 10 chiffres : « 01 » + 8 chiffres.
        'regex' => '/^01\d{8}$/',
        'longueur' => 10,
    ],

    'operateurs' => [
        'MTN' => ['50', '51', '52', '53', '54', '55', '56', '57', '58', '59',
            '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'],
        'MOOV' => ['40', '41', '42', '43', '44', '45', '46', '47', '48', '49',
            '60', '61', '62', '63', '64', '65', '66', '67', '68', '69'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Déduction de la zone du barème depuis la destination
    |--------------------------------------------------------------------------
    | Correspondance destination (ville béninoise / pays / agglomération)
    | → code de zone du barème ANaTT. Comparaison insensible à la casse et
    | aux accents. Liste non exhaustive et extensible ; à défaut de
    | correspondance, le code de zone doit être fourni explicitement.
    |
    | Rappel : EXT_ZONE_II et EXT_ZONE_III ont des taux identiques.
    */
    'zones' => [

        // Bénin (grille « repas »)
        'INTERIEUR' => [
            'COTONOU', 'PORTO-NOVO', 'PORTO NOVO', 'PARAKOU', 'BOHICON', 'ABOMEY',
            'ABOMEY-CALAVI', 'CALAVI', 'OUIDAH', 'LOKOSSA', 'NATITINGOU', 'DJOUGOU',
            'KANDI', 'MALANVILLE', 'SAVALOU', 'SAVE', 'DASSA', 'DASSA-ZOUME',
            'COME', 'APLAHOUE', 'POBE', 'SAKETE', 'ALLADA', 'GRAND-POPO',
            'TANGUIETA', 'KEROU', 'NIKKI', 'BEMBEREKE', 'BASSILA', 'COVE',
        ],

        // Europe, Amérique, Éthiopie, Japon, Koweït, Abuja, Guinée Équatoriale, Libreville, Angola
        'EXT_ZONE_I' => [
            'FRANCE', 'PARIS', 'BELGIQUE', 'BRUXELLES', 'ALLEMAGNE', 'ESPAGNE',
            'ROYAUME-UNI', 'ETATS-UNIS', 'CANADA', 'BRESIL', 'ETHIOPIE', 'ADDIS-ABEBA',
            'JAPON', 'TOKYO', 'KOWEIT', 'ABUJA', 'GUINEE EQUATORIALE', 'MALABO',
            'GABON', 'LIBREVILLE', 'ANGOLA', 'LUANDA',
        ],

        // Zone Franc, RDC, Ghana, Nigeria (sauf Abuja, Guinée Éq., Libreville)
        'EXT_ZONE_II' => [
            'TOGO', 'LOME', 'COTE D IVOIRE', "COTE D'IVOIRE", 'ABIDJAN', 'SENEGAL',
            'DAKAR', 'BURKINA FASO', 'OUAGADOUGOU', 'MALI', 'BAMAKO', 'NIGER',
            'NIAMEY', 'GUINEE', 'CONAKRY', 'CAMEROUN', 'YAOUNDE', 'DOUALA',
            'TCHAD', 'CENTRAFRIQUE', 'CONGO', 'BRAZZAVILLE', 'RDC', 'KINSHASA',
            'GHANA', 'ACCRA', 'NIGERIA', 'LAGOS',
        ],

        // Afrique hors zone franc, Asie, Océanie (sauf exceptions reclassées en zone I)
        'EXT_ZONE_III' => [
            'AFRIQUE DU SUD', 'KENYA', 'NAIROBI', 'TANZANIE', 'OUGANDA', 'RWANDA',
            'MAROC', 'ALGERIE', 'TUNISIE', 'EGYPTE', 'CHINE', 'INDE', 'EMIRATS',
            'DUBAI', 'TURQUIE', 'AUSTRALIE',
        ],
    ],

];
