<?php

use App\Models\Bareme;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OmAmendement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\EtatPaiementService;
use App\Services\MissionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\MysqlReel;

/*
 * Verrou de l'amendement (MissionService::amender → exigerAmendementPossible) :
 * les états de la mission sont lus avec lockForUpdate, pour qu'un contrôle
 * concurrent (EtatPaiementService::transition, qui verrouille aussi l'état) ne
 * signe pas l'état entre la garde et la resynchronisation des montants.
 *
 * Sous SQLite, lockForUpdate n'émet rien : seul InnoDB le prouve. Une SECONDE
 * session (autre connexion PDO, innodb_lock_wait_timeout = 1 s) joue le contrôle
 * concurrent. Une lecture sans verrou (lecture cohérente MVCC) ne la bloquerait pas.
 */

beforeEach(function () {
    MysqlReel::preparer($this);

    $this->gestionnaire = User::factory()->gestionnaireOm()->create();

    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);
    $this->mission = Mission::factory()->create(['zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false]);
    // OM 6 j = 270 000, avance 189 000 (70 %), reliquat 81 000.
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'AVANCE', 'montant' => 189000, 'statut' => 'EN_ATTENTE']);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'statut' => 'EN_ATTENTE']);

    // État des avances établi (BROUILLON) : il attend le contrôle.
    $this->etat = app(EtatPaiementService::class)->recapituler($this->mission, $this->gestionnaire);
    expect($this->etat->statut)->toBe('BROUILLON');

    $this->amendement = ['nb_jours' => 8, 'motif' => 'Rallonge de la mission de deux jours.'];
});

afterEach(function () {
    DB::disconnect(MysqlReel::CONNEXION_CONCURRENTE);
});

/**
 * Le contrôle concurrent, tel que EtatPaiementService::transition le fait :
 * SELECT … FOR UPDATE sur l'état. Renvoie null s'il obtient le verrou, sinon
 * l'exception (attente de verrou expirée).
 */
function controleConcurrent(int $etatId): ?Throwable
{
    $session = MysqlReel::sessionConcurrente();
    $session->beginTransaction();
    try {
        EtatPaiement::on(MysqlReel::CONNEXION_CONCURRENTE)->lockForUpdate()->findOrFail($etatId);

        return null;
    } catch (Throwable $e) {
        return $e;
    } finally {
        $session->rollBack();
    }
}

it('pendant l’amendement, l’état de la mission est verrouillé : un contrôle concurrent attend', function () {
    // Juste après la lecture des états par l'amendement (transaction encore
    // ouverte), le contrôle concurrent tente de prendre l'état.
    $sonde = null;
    $sondee = false;
    DB::listen(function (QueryExecuted $q) use (&$sonde, &$sondee) {
        if ($sondee || $q->connectionName === MysqlReel::CONNEXION_CONCURRENTE) {
            return;
        }
        if (preg_match('/^select \* from `etats_paiement` where `mission_id` = \?/i', $q->sql)) {
            $sondee = true;
            $sonde = controleConcurrent($this->etat->id);
        }
    });

    app(MissionService::class)->amender($this->om, $this->amendement, $this->gestionnaire);

    expect($sondee)->toBeTrue('la lecture des états de la mission n’a pas été observée');
    expect($sonde)->toBeInstanceOf(QueryException::class, 'le contrôle concurrent a obtenu l’état pendant l’amendement : aucun verrou');
    expect(MysqlReel::estAttenteDeVerrouExpiree($sonde))->toBeTrue();

    // Amendement validé ; le verrou est rendu au COMMIT : le contrôle passe.
    expect($this->om->fresh()->nb_jours)->toBe(8)
        ->and(controleConcurrent($this->etat->id))->toBeNull();
});

it('un amendement attend la signature concurrente qui tient l’état, puis échoue sans rien écrire', function () {
    // Le contrôle concurrent tient l'état (transaction ouverte, non validée).
    $signature = MysqlReel::sessionConcurrente();
    $signature->beginTransaction();
    EtatPaiement::on(MysqlReel::CONNEXION_CONCURRENTE)->lockForUpdate()->findOrFail($this->etat->id);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $echec = null;
    try {
        app(MissionService::class)->amender($this->om, $this->amendement, $this->gestionnaire);
    } catch (QueryException $e) {
        $echec = $e;
    }

    // C'est bien la LECTURE VERROUILLANTE des états qui a attendu (et non une
    // écriture ultérieure) : une lecture simple serait passée sans attendre.
    expect($echec)->toBeInstanceOf(QueryException::class)
        ->and(MysqlReel::estAttenteDeVerrouExpiree($echec))->toBeTrue()
        ->and(strtolower($echec->getSql()))->toContain('from `etats_paiement` where `mission_id` = ?')
        ->toEndWith('for update');

    // Transaction annulée : l'OM, ses versements et le journal sont intacts.
    expect($this->om->fresh()->nb_jours)->toBe(6)
        ->and((int) Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->value('montant'))->toBe(81000)
        ->and(OmAmendement::count())->toBe(0);

    // La signature concurrente se termine : l'amendement passe alors.
    $signature->rollBack();
    app(MissionService::class)->amender($this->om, $this->amendement, $this->gestionnaire);
    expect($this->om->fresh()->nb_jours)->toBe(8);
});
