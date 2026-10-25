<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Comptes nominatifs du circuit de validation : CONTROLEUR -> DAF -> DG.
 *
 * Conçu pour être exécuté EN PRODUCTION, contrairement aux comptes de
 * démonstration d'UserSeeder :
 *
 *   - aucun mot de passe en dur : un mot de passe temporaire aléatoire est
 *     généré et affiché UNE SEULE FOIS dans la console ;
 *   - must_change_password est posé, l'intéressé doit le changer à sa première
 *     connexion (le middleware ForcePasswordChange verrouille l'API sinon) ;
 *   - idempotent : relancer le seeder ne réinitialise JAMAIS le mot de passe
 *     d'un compte existant, il se contente d'aligner le rôle et la fonction.
 *
 * Usage : php artisan db:seed --class=ComptesCircuitSeeder
 */
class ComptesCircuitSeeder extends Seeder
{
    /** Profil attribué à chaque poste du circuit. */
    private const PROFILS = [
        'controleur' => Permissions::CONTROLEUR,
        'daf' => Permissions::DAF,
        'dg' => Permissions::DG,
    ];

    public function run(): void
    {
        $postes = config('profils.circuit', []);
        $crees = [];
        $existants = [];
        $ignores = [];
        $proteges = [];

        foreach (self::PROFILS as $cle => $profil) {
            $poste = $postes[$cle] ?? [];
            $email = $poste['email'] ?? null;
            $nom = $poste['name'] ?? null;

            // Un poste non renseigné dans le .env est simplement passé :
            // on ne fabrique jamais un compte à partir de valeurs devinées.
            if (empty($email) || empty($nom)) {
                $ignores[] = strtoupper($cle);

                continue;
            }

            $existant = User::where('email', $email)->first();

            if ($existant && $existant->role === Permissions::SUPER_ADMIN) {
                // Un super administrateur n'est jamais rétrogradé par ce seeder :
                // il peut être le dernier (il en faut toujours un actif).
                $proteges[] = "{$email} (SUPER_ADMIN conservé, poste ".strtoupper($cle).' non attribué)';

                continue;
            }

            if ($existant) {
                // Compte déjà présent : on aligne le rôle et la fonction, sans
                // jamais toucher au mot de passe ni réactiver un compte désactivé.
                $existant->forceFill([
                    'role' => $profil,
                    'fonction' => $poste['fonction'] ?? null,
                ])->save();

                $existants[] = "{$email} ({$profil})";

                continue;
            }

            $motDePasse = $this->motDePasseTemporaire();

            User::create([
                'name' => $nom,
                'email' => $email,
                'password' => Hash::make($motDePasse),
                'role' => $profil,
                'fonction' => $poste['fonction'] ?? null,
                'actif' => true,
                'must_change_password' => true,
            ]);

            $crees[] = [$profil, $nom, $email, $motDePasse];
        }

        $this->rapporter($crees, $existants, $ignores, $proteges);
    }

    /**
     * Mot de passe temporaire : 20 caractères aléatoires. Il n'est affiché
     * qu'une fois et n'est stocké nulle part en clair.
     */
    private function motDePasseTemporaire(): string
    {
        return Str::password(20, symbols: false);
    }

    /**
     * @param  array<int,array{0:string,1:string,2:string,3:string}>  $crees
     * @param  array<int,string>  $existants
     * @param  array<int,string>  $ignores
     * @param  array<int,string>  $proteges
     */
    private function rapporter(array $crees, array $existants, array $ignores, array $proteges = []): void
    {
        if ($crees !== []) {
            $this->command?->newLine();
            $this->command?->warn('Mots de passe temporaires — notez-les MAINTENANT, ils ne seront plus affichés :');
            $this->command?->table(
                ['Profil', 'Nom', 'Email', 'Mot de passe temporaire'],
                $crees,
            );
            $this->command?->warn('Chaque titulaire devra le changer à sa première connexion.');
        }

        foreach ($existants as $ligne) {
            $this->command?->info("Compte déjà existant, rôle et fonction alignés : {$ligne}");
        }

        foreach ($proteges as $ligne) {
            $this->command?->warn("Compte super administrateur laissé tel quel : {$ligne}. Choisissez une autre adresse pour ce poste.");
        }

        foreach ($ignores as $poste) {
            $this->command?->comment("Poste {$poste} ignoré : renseignez {$poste}_NAME et {$poste}_EMAIL dans le .env.");
        }

        if ($crees === [] && $existants === [] && $proteges === []) {
            $this->command?->comment('Aucun compte de circuit à créer.');
        }
    }
}
