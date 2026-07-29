<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Services\MissionService;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bac à sable pour essayer le circuit CONTROLEUR -> DAF -> DG de bout en bout.
 *
 * À ne pas confondre avec ComptesCircuitSeeder, qui est le seeder de PRODUCTION :
 * celui-ci pose un mot de passe CONNU sur les comptes du circuit, ce qui n'a de
 * sens que pour une démonstration. Il refuse donc de s'exécuter en production.
 *
 * Il installe trois choses, chacune indispensable à l'essai :
 *   1. les comptes des trois signataires, mot de passe connu, sans changement
 *      forcé à la première connexion (la friction n'apporte rien ici) ;
 *   2. les identités imprimées sous les signatures — sans elles l'état sort
 *      sans signataire, ce qui est la première surprise de tout le monde ;
 *   3. une mission neuve dont les OM sont au statut IMPORTE et qui n'a PAS
 *      encore d'état, pour partir du tout début : récapitulatif, puis contrôle,
 *      puis visa DAF, puis validation DG.
 *
 * Usage : php artisan db:seed --class=DemoCircuitSeeder
 */
class DemoCircuitSeeder extends Seeder
{
    /** Mot de passe de démonstration, volontairement explicite. */
    public const MOT_DE_PASSE = 'CircuitTest2026!';

    /** Référence de la mission d'essai, qui sert aussi de garde anti-doublon. */
    private const REFERENCE = 'DEMO-CIRCUIT';

    /** Comptes installés : email => [profil, prénoms, nom, fonction imprimée]. */
    private const COMPTES = [
        'controleur' => ['demo.controleur@anatt.bj', Permissions::CONTROLEUR, 'Paul', 'AGBO', 'Contrôleur de gestion'],
        'daf' => ['demo.daf@anatt.bj', Permissions::DAF, 'Euloge D.', 'ATOHOUN', "Directeur de l'Administration et des Finances"],
        'dg' => ['demo.dg@anatt.bj', Permissions::DG, 'Richard', 'DADA', 'Directeur Général'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error(
                'DemoCircuitSeeder pose un mot de passe connu : refusé en production. '
                .'Utilisez ComptesCircuitSeeder.'
            );

            return;
        }

        $this->creerComptes();
        $this->enregistrerIdentites();
        $this->creerMissionDEssai();
    }

    /** Les trois signataires, mot de passe connu et connexion immédiate. */
    private function creerComptes(): void
    {
        $lignes = [];

        foreach (self::COMPTES as [$email, $profil, $prenoms, $nom, $fonction]) {
            $user = User::firstOrNew(['email' => $email]);

            $user->forceFill([
                'name' => "{$prenoms} {$nom}",
                'password' => Hash::make(self::MOT_DE_PASSE),
                'role' => $profil,
                'fonction' => $fonction,
                'actif' => true,
                // Démonstration : on veut se connecter et enchaîner, pas changer
                // un mot de passe. ComptesCircuitSeeder fait l'inverse, à raison.
                'must_change_password' => false,
            ])->save();

            $lignes[] = [$profil, $user->name, $email, self::MOT_DE_PASSE];
        }

        $this->command?->newLine();
        $this->command?->table(['Profil', 'Nom', 'Email', 'Mot de passe'], $lignes);
    }

    /**
     * Identités imprimées sous les signatures, dans le fichier que lit l'écran
     * Administration -> Paramètres. L'image de signature reste à téléverser
     * depuis l'écran Signatures : un seeder n'a pas de paraphe à proposer.
     */
    private function enregistrerIdentites(): void
    {
        $chemin = storage_path('app/settings.json');
        $params = file_exists($chemin)
            ? (json_decode((string) file_get_contents($chemin), true) ?: [])
            : [];

        foreach (self::COMPTES as $cle => [, , $prenoms, $nom, $fonction]) {
            $params["{$cle}Prenoms"] = $prenoms;
            $params["{$cle}Nom"] = $nom;
            $params["{$cle}Fonction"] = $fonction;
        }

        if (! is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0755, true);
        }

        file_put_contents(
            $chemin,
            json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->command?->info('Identités des signataires enregistrées (storage/app/settings.json).');
        $this->command?->comment('Téléversez les images depuis Administration -> Signatures.');
    }

    /** Mission neuve, OM au statut IMPORTE, aucun état : le circuit part de zéro. */
    private function creerMissionDEssai(): void
    {
        $existante = OrdreMission::where('reference_semo', 'like', self::REFERENCE.'%')->first();

        if ($existante) {
            $mission = Mission::find($existante->mission_id);
            $etat = $mission ? EtatPaiement::where('mission_id', $mission->id)->first() : null;

            $this->command?->info(
                "Mission d'essai déjà présente (#{$existante->mission_id}, état : "
                .($etat?->statut ?? 'aucun').').'
            );

            return;
        }

        $gestionnaire = User::where('role', Permissions::GESTIONNAIRE_OM)->first()
            ?? User::where('role', Permissions::SUPER_ADMIN)->first();

        $agents = Agent::where('actif', true)->take(3)->get();

        if ($gestionnaire === null || $agents->count() < 3) {
            $this->command?->warn(
                "Mission d'essai ignorée : il faut un gestionnaire et au moins 3 agents actifs. "
                .'Lancez d’abord `php artisan db:seed`.'
            );

            return;
        }

        $mission = app(MissionService::class)->creerAvecOrdres([
            'objet' => 'Essai du circuit de validation — mission de démonstration',
            'destination' => 'Parakou',
            'zone_code' => 'INTERIEUR',
            'date_depart' => now()->subDays(4)->toDateString(),
            'date_retour' => now()->subDays(2)->toDateString(),
            'imputation_budgetaire' => 'DEMO-CIRCUIT-2026',
            'prise_en_charge_tiers' => false,
            'ordres' => $agents->values()->map(fn (Agent $agent, int $i) => [
                'reference_semo' => sprintf('%s-%03d', self::REFERENCE, $i + 1),
                'agent_id' => $agent->id,
                // Le dernier bénéficiaire est le conducteur, payé selon SA catégorie.
                'role' => $i === $agents->count() - 1 ? 'CAV' : 'PARTICIPANT',
                'type_prise_en_charge' => 'JOURNEE_COMPLETE',
            ])->all(),
        ], $gestionnaire);

        $total = (int) $mission->ordresMission()->sum('montant_total');

        $this->command?->info(sprintf(
            "Mission d'essai #%d créée : %d bénéficiaires, %s FCFA. Aucun état — "
            .'commencez par le récapitulatif.',
            $mission->id,
            $mission->ordresMission()->count(),
            number_format($total, 0, ',', ' '),
        ));
    }
}
