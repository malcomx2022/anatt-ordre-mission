<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
 * docs/openapi.yaml reste le reflet de routes/api.php.
 *
 * La description de l'API s'était figée sur un modèle abandonné (soumission,
 * double validation N1/DG, paiement direct) sans que rien ne le signale. Ces
 * tests comparent la spécification aux routes réellement enregistrées : chaque
 * opération décrite existe, chaque route est décrite, et la permission
 * annoncée (`x-permission`) est celle qu'exige le middleware.
 */

/** @return array<string,mixed> */
function specificationOpenApi(): array
{
    return Yaml::parseFile(base_path('docs/openapi.yaml'));
}

/** « METHODE /chemin » => clé de permission (null = aucune), d'après les routes. */
function operationsDesRoutes(): array
{
    $operations = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        $permission = null;
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/(?:^permission|Middleware\\\\Authorize):(.+)$/', $middleware, $m)) {
                $permission = $m[1];
            }
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $methode) {
            $operations[$methode.' /'.substr($route->uri(), 4)] = $permission;
        }
    }

    ksort($operations);

    return $operations;
}

/** « METHODE /chemin » => x-permission (null = aucune), d'après la spécification. */
function operationsDeLaSpecification(): array
{
    $operations = [];

    foreach (specificationOpenApi()['paths'] as $chemin => $item) {
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $methode) {
            if (isset($item[$methode])) {
                $operations[strtoupper($methode).' '.$chemin] = $item[$methode]['x-permission'] ?? null;
            }
        }
    }

    ksort($operations);

    return $operations;
}

it('décrit chaque route de l’API, et rien de plus', function () {
    $routes = array_keys(operationsDesRoutes());
    $specification = array_keys(operationsDeLaSpecification());

    expect(array_values(array_diff($routes, $specification)))->toBe([], 'Routes absentes de docs/openapi.yaml')
        ->and(array_values(array_diff($specification, $routes)))->toBe([], 'Opérations décrites sans route');
});

it('annonce pour chaque opération la permission exigée par la route', function () {
    $specification = operationsDeLaSpecification();
    $ecarts = [];

    foreach (operationsDesRoutes() as $operation => $permission) {
        if (array_key_exists($operation, $specification) && $specification[$operation] !== $permission) {
            $ecarts[] = "$operation : route ".($permission ?? 'aucune').', spec '.($specification[$operation] ?? 'aucune');
        }
    }

    expect($ecarts)->toBe([]);
});

it('ne référence que des composants définis', function () {
    $spec = specificationOpenApi();
    $brut = file_get_contents(base_path('docs/openapi.yaml'));
    preg_match_all("~\\\$ref: '#/components/([a-zA-Z]+)/([a-zA-Z0-9]+)'~", $brut, $refs, PREG_SET_ORDER);

    $manquantes = collect($refs)
        ->reject(fn (array $r) => isset($spec['components'][$r[1]][$r[2]]))
        ->map(fn (array $r) => "{$r[1]}/{$r[2]}")
        ->unique()->values()->all();

    expect($refs)->not->toBeEmpty()
        ->and($manquantes)->toBe([]);
});
