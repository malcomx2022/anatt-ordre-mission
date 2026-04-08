<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MissionService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function creer(array $data, User $user): OrdreMission
    {
        $nbJours = Carbon::parse($data['date_retour'])->diffInDays(Carbon::parse($data['date_depart'])) + 1;
        $taux = TauxPerDiem::findOrFail($data['taux_id']);
        $montant = $nbJours * $taux->montant_journalier;

        return OrdreMission::create([
            ...$data,
            'numero' => OrdreMission::generateNumero(),
            'nb_jours' => $nbJours,
            'montant_per_diem' => $montant,
            'statut' => 'BROUILLON',
        ]);
    }

    public function modifier(OrdreMission $ordre, array $data): OrdreMission
    {
        return DB::transaction(function () use ($ordre, $data) {
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if ($ordre->statut !== 'BROUILLON') {
                throw ValidationException::withMessages([
                    'statut' => ['Seuls les ordres en brouillon peuvent etre modifies.'],
                ]);
            }

            if (isset($data['date_depart']) && isset($data['date_retour'])) {
                $data['nb_jours'] = Carbon::parse($data['date_retour'])->diffInDays(Carbon::parse($data['date_depart'])) + 1;
                if (isset($data['taux_id'])) {
                    $taux = TauxPerDiem::findOrFail($data['taux_id']);
                    $data['montant_per_diem'] = $data['nb_jours'] * $taux->montant_journalier;
                }
            }

            $ordre->update($data);

            return $ordre->fresh(['agent', 'taux']);
        });
    }

    public function supprimer(OrdreMission $ordre): void
    {
        DB::transaction(function () use ($ordre) {
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if ($ordre->statut !== 'BROUILLON') {
                throw ValidationException::withMessages([
                    'statut' => ['Seuls les ordres en brouillon peuvent etre supprimes.'],
                ]);
            }

            $ordre->delete();
        });
    }

    public function soumettre(OrdreMission $ordre): OrdreMission
    {
        return $this->transitionStatut($ordre, 'BROUILLON', 'SOUMIS', function (OrdreMission $ordre) {
            $ordre->update(['statut' => 'SOUMIS']);

            $ordre->load('agent');
            $this->notificationService->missionSoumise($ordre);
        });
    }

    public function validerN1(OrdreMission $ordre, User $user): OrdreMission
    {
        if (! $user->hasRole('CHEF_SERVICE', 'ADMIN')) {
            throw ValidationException::withMessages([
                'role' => ['La validation N+1 necessite le role CHEF_SERVICE.'],
            ]);
        }

        $agent = $ordre->agent;
        if ($user->role === 'CHEF_SERVICE' && $user->agent && $user->agent->service !== $agent->service) {
            throw ValidationException::withMessages([
                'service' => ['Vous ne pouvez valider que les ordres de votre service.'],
            ]);
        }

        return $this->transitionStatut($ordre, 'SOUMIS', 'VALIDE_N1', function (OrdreMission $ordre) use ($user) {
            $ordre->update([
                'statut' => 'VALIDE_N1',
                'valide_par_chef' => $user->id,
            ]);

            $ordre->load(['agent', 'valideurChef']);
            $this->notificationService->missionValideeN1($ordre);
        });
    }

    public function approuver(OrdreMission $ordre, User $user): OrdreMission
    {
        if (! $user->hasRole('DIRECTEUR', 'ADMIN')) {
            throw ValidationException::withMessages([
                'role' => ['L\'approbation necessite le role DIRECTEUR.'],
            ]);
        }

        return $this->transitionStatut($ordre, 'VALIDE_N1', 'APPROUVE_DG', function (OrdreMission $ordre) use ($user) {
            $ordre->update([
                'statut' => 'APPROUVE_DG',
                'valide_par_dg' => $user->id,
            ]);

            $ordre->load('agent');
            $this->notificationService->missionApprouvee($ordre);
        });
    }

    public function rejeter(OrdreMission $ordre, string $commentaire, ?User $user = null): OrdreMission
    {
        if ($user && ! $user->hasRole('CHEF_SERVICE', 'DIRECTEUR', 'ADMIN')) {
            throw ValidationException::withMessages([
                'role' => ['Le rejet necessite le role CHEF_SERVICE ou DIRECTEUR.'],
            ]);
        }

        return DB::transaction(function () use ($ordre, $commentaire) {
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if (! in_array($ordre->statut, ['SOUMIS', 'VALIDE_N1'])) {
                throw ValidationException::withMessages([
                    'statut' => ['Cet ordre ne peut pas etre rejete.'],
                ]);
            }

            $ordre->update([
                'statut' => 'REJETE',
                'commentaire_rejet' => $commentaire,
            ]);

            $ordre->load('agent');
            $this->notificationService->missionRejetee($ordre);

            return $ordre->fresh(['agent', 'taux']);
        });
    }

    /**
     * Transition atomique de statut avec verrou pessimiste.
     * Empeche les race conditions quand 2 utilisateurs tentent
     * de modifier le statut du meme OM simultanement.
     */
    private function transitionStatut(
        OrdreMission $ordre,
        string $statutAttendu,
        string $nouveauStatut,
        callable $action,
    ): OrdreMission {
        return DB::transaction(function () use ($ordre, $statutAttendu, $nouveauStatut, $action) {
            // Verrou pessimiste: bloque les autres transactions sur cette ligne
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if ($ordre->statut !== $statutAttendu) {
                throw ValidationException::withMessages([
                    'statut' => ["Cet ordre ne peut pas passer de '{$ordre->statut}' a '{$nouveauStatut}'. Statut attendu: '{$statutAttendu}'."],
                ]);
            }

            $action($ordre);

            return $ordre->fresh(['agent', 'taux']);
        });
    }
}
