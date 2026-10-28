<?php

namespace Tests\Support;

use InvalidArgumentException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/*
 * Validateur minimal des schémas de docs/openapi.yaml (OpenAPI 3.0), pour les tests
 * de contrat. Écrit à la main : aucune dépendance Composer ajoutée (le vendor/ de
 * production est téléversé à la main sur le cPanel).
 *
 * Couvre ce que la spécification emploie réellement :
 *   $ref (#/components/…), type (integer ≠ number), nullable, enum (null compris),
 *   format date / date-time, properties, required, additionalProperties, items,
 *   minimum / maximum, allOf (fusionné), oneOf (exactement une branche).
 *
 * Mode STRICT (par défaut) : une propriété renvoyée par l'API mais absente d'un
 * objet dont le schéma énumère ses `properties` est une dérive — signalée. Un objet
 * sans `properties` (`{ type: object }`) reste libre.
 *
 * Le JSON est décodé en objets (stdClass) pour distinguer `{}` de `[]`.
 */
class OpenApiSchema
{
    /** @param array<string,mixed> $spec */
    public function __construct(private array $spec, private bool $strict = true) {}

    public static function depuisFichier(?string $chemin = null): self
    {
        return new self(Yaml::parseFile($chemin ?? base_path('docs/openapi.yaml')));
    }

    /**
     * Schéma JSON de la réponse documentée pour une opération.
     *
     * @return array<string,mixed>
     */
    public function schemaReponse(string $methode, string $chemin, int $statut): array
    {
        $operation = $this->spec['paths'][$chemin][strtolower($methode)] ?? null;
        if ($operation === null) {
            throw new InvalidArgumentException("Opération non décrite : $methode $chemin");
        }

        $reponse = $operation['responses'][(string) $statut] ?? null;
        if ($reponse === null) {
            throw new InvalidArgumentException("Réponse $statut non décrite pour $methode $chemin");
        }

        $reponse = $this->resoudre($reponse);
        $schema = $reponse['content']['application/json']['schema'] ?? null;
        if ($schema === null) {
            throw new InvalidArgumentException("Réponse $statut de $methode $chemin sans schéma application/json");
        }

        return $schema;
    }

    /**
     * Valide une valeur décodée (objets en stdClass) contre un schéma.
     *
     * @param  array<string,mixed>  $schema
     * @return list<string> écarts, vide si conforme
     */
    public function valider(mixed $valeur, array $schema, string $pointeur = '$'): array
    {
        $schema = $this->normaliser($schema);

        if ($valeur === null && ! isset($schema['oneOf'])) {
            if (($schema['nullable'] ?? false) === true
                || (isset($schema['enum']) && in_array(null, $schema['enum'], true))
                || $this->estLibre($schema)) {
                return [];
            }

            return ["$pointeur : null non autorisé (nullable absent)"];
        }

        if (isset($schema['oneOf'])) {
            $valides = 0;
            $detail = [];
            foreach ($schema['oneOf'] as $i => $branche) {
                $ecarts = $this->valider($valeur, $branche, $pointeur);
                $ecarts === [] ? $valides++ : $detail[] = "branche $i : ".implode(' ; ', $ecarts);
            }

            return $valides === 1 ? [] : ["$pointeur : $valides branche(s) oneOf satisfaite(s) au lieu d'une".($detail ? ' — '.implode(' | ', $detail) : '')];
        }

        $ecarts = [];

        if (isset($schema['type']) && ! $this->typeConforme($valeur, $schema['type'])) {
            return ["$pointeur : type attendu {$schema['type']}, reçu ".$this->typeDe($valeur)];
        }

        if (isset($schema['enum']) && ! in_array($valeur, $schema['enum'], true)) {
            $ecarts[] = "$pointeur : valeur ".json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' hors énumération ['.implode(', ', array_map(fn ($v) => json_encode($v), $schema['enum'])).']';
        }

        if (is_string($valeur) && isset($schema['format'])) {
            $ecarts = [...$ecarts, ...$this->validerFormat($valeur, $schema['format'], $pointeur)];
        }

        if (is_int($valeur) || is_float($valeur)) {
            if (isset($schema['minimum']) && $valeur < $schema['minimum']) {
                $ecarts[] = "$pointeur : $valeur < minimum {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $valeur > $schema['maximum']) {
                $ecarts[] = "$pointeur : $valeur > maximum {$schema['maximum']}";
            }
        }

        if (is_array($valeur) && isset($schema['items'])) {
            foreach ($valeur as $i => $element) {
                $ecarts = [...$ecarts, ...$this->valider($element, $schema['items'], "{$pointeur}[$i]")];
            }
        }

        if ($valeur instanceof stdClass) {
            $ecarts = [...$ecarts, ...$this->validerObjet($valeur, $schema, $pointeur)];
        }

        return $ecarts;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @return list<string>
     */
    private function validerObjet(stdClass $objet, array $schema, string $pointeur): array
    {
        $ecarts = [];
        $valeurs = get_object_vars($objet);
        $proprietes = $schema['properties'] ?? null;

        foreach ($schema['required'] ?? [] as $requise) {
            if (! array_key_exists($requise, $valeurs)) {
                $ecarts[] = "$pointeur : propriété requise « $requise » absente";
            }
        }

        foreach ($valeurs as $cle => $valeur) {
            $chemin = "$pointeur.$cle";

            if (is_array($proprietes) && array_key_exists($cle, $proprietes)) {
                $ecarts = [...$ecarts, ...$this->valider($valeur, $proprietes[$cle] ?? [], $chemin)];
            } elseif (isset($schema['additionalProperties'])) {
                if (is_array($schema['additionalProperties'])) {
                    $ecarts = [...$ecarts, ...$this->valider($valeur, $schema['additionalProperties'], $chemin)];
                } elseif ($schema['additionalProperties'] === false) {
                    $ecarts[] = "$chemin : propriété non autorisée";
                }
            } elseif ($this->strict && is_array($proprietes)) {
                $ecarts[] = "$chemin : propriété non documentée";
            }
        }

        return $ecarts;
    }

    /**
     * Résout un $ref et fusionne un allOf (propriétés, requis, type) en un seul schéma.
     *
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     */
    private function normaliser(array $schema): array
    {
        $schema = $this->resoudre($schema);

        if (! isset($schema['allOf'])) {
            return $schema;
        }

        $fusion = $schema;
        unset($fusion['allOf']);

        foreach ($schema['allOf'] as $branche) {
            $branche = $this->normaliser($branche);
            foreach ($branche as $cle => $valeur) {
                $fusion[$cle] = match ($cle) {
                    'properties' => [...($fusion['properties'] ?? []), ...$valeur],
                    'required' => array_values(array_unique([...($fusion['required'] ?? []), ...$valeur])),
                    default => $fusion[$cle] ?? $valeur,   // le schéma englobant (nullable, description…) prévaut
                };
            }
        }

        return $fusion;
    }

    /**
     * @param  array<string,mixed>  $noeud
     * @return array<string,mixed>
     */
    private function resoudre(array $noeud): array
    {
        $vus = [];
        while (isset($noeud['$ref'])) {
            $ref = $noeud['$ref'];
            if (! str_starts_with($ref, '#/') || isset($vus[$ref])) {
                throw new InvalidArgumentException("Référence non résoluble : $ref");
            }
            $vus[$ref] = true;

            $cible = $this->spec;
            foreach (explode('/', substr($ref, 2)) as $segment) {
                if (! is_array($cible) || ! array_key_exists($segment, $cible)) {
                    throw new InvalidArgumentException("Référence introuvable : $ref");
                }
                $cible = $cible[$segment];
            }

            // Les voisins d'un $ref sont ignorés en OpenAPI 3.0 ; on garde au plus la description.
            $noeud = $cible;
        }

        return $noeud;
    }

    /** Schéma sans aucune contrainte de type : `{}` ou `{ description: … }`. */
    private function estLibre(array $schema): bool
    {
        return ! isset($schema['type']) && ! isset($schema['enum']) && ! isset($schema['properties'])
            && ! isset($schema['items']) && ! isset($schema['oneOf']);
    }

    private function typeConforme(mixed $valeur, string $type): bool
    {
        return match ($type) {
            'integer' => is_int($valeur),
            'number' => is_int($valeur) || is_float($valeur),
            'string' => is_string($valeur),
            'boolean' => is_bool($valeur),
            'array' => is_array($valeur),
            'object' => $valeur instanceof stdClass,
            default => throw new InvalidArgumentException("Type OpenAPI inconnu : $type"),
        };
    }

    private function typeDe(mixed $valeur): string
    {
        return match (true) {
            is_int($valeur) => 'integer',
            is_float($valeur) => 'number',
            is_string($valeur) => 'string ('.json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).')',
            is_bool($valeur) => 'boolean',
            is_array($valeur) => 'array',
            $valeur instanceof stdClass => 'object',
            default => get_debug_type($valeur),
        };
    }

    /** @return list<string> */
    private function validerFormat(string $valeur, string $format, string $pointeur): array
    {
        $motif = match ($format) {
            'date' => '/^\d{4}-\d{2}-\d{2}$/',
            'date-time' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/',
            'email' => '/^[^@\s]+@[^@\s]+$/',
            'uuid' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            default => null,   // password, binary… : non contrôlés
        };

        return $motif === null || preg_match($motif, $valeur) === 1
            ? []
            : ["$pointeur : ".json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)." ne respecte pas le format $format"];
    }
}
