<?php

use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

/*
 * Filet de sécurité sur l'exposition de l'API.
 *
 * Une route ajoutée hors d'un groupe `permission:` est ouverte à tout compte
 * authentifié, LECTEUR compris — la faute est invisible en relecture et ne se
 * voit qu'en production. Ces tests refusent toute route sans permission qui ne
 * figure pas explicitement dans la liste des routes libres ci-dessous.
 */

/**
 * Routes API volontairement sans permission.
 *   - publiques : la session n'existe pas encore ;
 *   - libres : tout compte authentifié y a droit, quel que soit son profil.
 */
const ROUTES_LIBRES = [
    'api/auth/login',                 // public
    'api/auth/mot-de-passe/email',    // public
    'api/auth/mot-de-passe/reset',    // public
    'api/auth/logout',                // son propre jeton
    'api/auth/me',                    // son propre profil
    'api/auth/me/permissions',        // ses propres droits
    'api/auth/mot-de-passe/changer',  // son propre mot de passe
    'api/notifications',              // ses propres notifications (feed de la cloche)
    'api/notifications/lues',         // marque ses propres notifications lues
    'api/notifications/{notification}/lu', // marque une des siennes lue
    'api/admin/parametres/part-avance', // information publique (taux d'avance)
    'api/admin/parametres/annee-fiscale', // information publique (exercice courant)
    'api/admin/parametres/application', // information publique (nom de l'application, opérateurs ouverts)
];

/** URI => clés de permission exigées, pour toutes les routes de l'API. */
function permissionsParRoute(): array
{
    $table = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        $cles = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            // `permission:` est un alias d'Illuminate\Auth\Middleware\Authorize :
            // c'est la classe résolue qui apparaît ici, pas l'alias.
            if (preg_match('/(?:^permission|Middleware\\\\Authorize):(.+)$/', $middleware, $m)) {
                $cles = array_merge($cles, explode(',', $m[1]));
            }
        }

        $table[$route->uri()] = array_values(array_unique(array_merge($table[$route->uri()] ?? [], $cles)));
    }

    return $table;
}

it('protège chaque route de l’API par une permission', function () {
    $sansPermission = array_keys(array_filter(
        permissionsParRoute(),
        fn (array $cles, string $uri) => $cles === [] && ! in_array($uri, ROUTES_LIBRES, true),
        ARRAY_FILTER_USE_BOTH,
    ));

    // Message lisible : la liste des URI fautives, pas un simple « false ».
    expect($sansPermission)->toBe([]);
});

it('n’emploie que des clés de permission déclarées', function () {
    $inconnues = [];

    foreach (permissionsParRoute() as $uri => $cles) {
        foreach (array_diff($cles, Permissions::TOUTES) as $cle) {
            $inconnues[] = "$uri -> $cle";
        }
    }

    expect($inconnues)->toBe([]);
});

it('déclare toutes les routes libres, sans liste morte', function () {
    $uris = array_keys(permissionsParRoute());

    // Une entrée obsolète dans ROUTES_LIBRES rouvrirait silencieusement une
    // route le jour où elle réapparaît sous le même nom.
    expect(array_diff(ROUTES_LIBRES, $uris))->toBe([]);
});

it('aligne la matrice PHP et la configuration éditable', function () {
    foreach (Permissions::PROFILS as $profil) {
        if ($profil === Permissions::SUPER_ADMIN) {
            continue;
        }

        $config = config("profils.matrice.$profil");
        $constante = Permissions::MATRICE[$profil] ?? null;

        expect($config)->not->toBeNull("Profil $profil absent de config/profils.php")
            ->and(array_diff($config, $constante))->toBe([])
            ->and(array_diff($constante, $config))->toBe([]);
    }
});

it('n’attribue que des clés déclarées dans la matrice', function () {
    foreach (config('profils.matrice') as $profil => $cles) {
        expect(array_diff($cles, Permissions::TOUTES))->toBe([]);
    }

    expect(array_diff(config('profils.permissions'), Permissions::TOUTES))->toBe([])
        ->and(array_diff(Permissions::TOUTES, config('profils.permissions')))->toBe([]);
});

it('confie la numérotation de l’état aux trois acteurs du circuit, et à eux seuls', function () {
    $detenteurs = array_values(array_filter(
        Permissions::PROFILS,
        fn (string $p) => $p !== Permissions::SUPER_ADMIN
            && in_array(Permissions::OM_NUMEROTER, Permissions::pour($p), true),
    ));

    expect($detenteurs)->toBe([
        Permissions::DAF,
        Permissions::DG,
        Permissions::CONTROLEUR,
    ]);
});
