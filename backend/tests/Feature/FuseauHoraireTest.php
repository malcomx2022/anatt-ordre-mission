<?php

use App\Models\User;
use App\Models\Versement;
use App\Services\RapportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Fuseau de l'application : heure du Bénin (Africa/Porto-Novo, UTC+1), décision D2.
 * En UTC, un paiement confirmé entre minuit et 1 h, heure du Bénin, le 1er du mois
 * tombait encore le mois précédent dans les rapports et dans date_paiement.
 */

afterEach(fn () => Carbon::setTestNow());

it('règle l’application à l’heure du Bénin', function () {
    expect(config('app.timezone'))->toBe('Africa/Porto-Novo')
        ->and(now()->getOffset())->toBe(3600);
});

it('range dans le bon mois un paiement confirmé juste après minuit, heure du Bénin', function () {
    // 31/10 à 23 h 30 UTC = 1er novembre à 0 h 30 au Bénin.
    Carbon::setTestNow(Carbon::parse('2026-10-31T23:30:00Z')->setTimezone(config('app.timezone')));
    Versement::factory()->confirme()->create(['montant' => 45000, 'date_paiement' => now()]);

    $rapports = app(RapportService::class);

    expect($rapports->mensuel('2026-11')['synthese']['nb_versements'])->toBe(1)
        ->and($rapports->mensuel('2026-10')['synthese']['nb_versements'])->toBe(0);
});

it('sérialise toujours les dates de l’API en UTC (instant inchangé pour le navigateur)', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-31T23:30:00Z')->setTimezone(config('app.timezone')));
    $user = User::factory()->admin()->create();

    expect($user->fresh()->created_at->toJSON())->toBe('2026-10-31T23:30:00.000000Z');
});

it('reprend les horodatages UTC existants d’une heure, sans toucher aux dates seules', function () {
    $migration = require database_path('migrations/2026_10_26_090000_passer_horodatages_au_fuseau_applicatif.php');

    $versement = Versement::factory()->confirme()->create();
    // Valeurs telles que l'application les écrivait en UTC avant le changement.
    DB::table('versements')->where('id', $versement->id)->update([
        'date_paiement' => '2026-10-31 23:30:00',
        'created_at' => '2026-10-31 22:00:00',
    ]);
    $mission = $versement->ordreMission->mission;
    $depart = DB::table('missions')->where('id', $mission->id)->value('date_depart');

    $migration->up();

    $ligne = DB::table('versements')->where('id', $versement->id)->first();
    expect(substr((string) $ligne->date_paiement, 0, 19))->toBe('2026-11-01 00:30:00')
        ->and(substr((string) $ligne->created_at, 0, 19))->toBe('2026-10-31 23:00:00')
        ->and(DB::table('missions')->where('id', $mission->id)->value('date_depart'))->toBe($depart)
        // Lu par l'application (Porto-Novo), c'est le même instant qu'avant.
        ->and($versement->fresh()->date_paiement->toJSON())->toBe('2026-10-31T23:30:00.000000Z');

    $migration->down();

    expect(substr((string) DB::table('versements')->where('id', $versement->id)->value('date_paiement'), 0, 19))
        ->toBe('2026-10-31 23:30:00');
});
