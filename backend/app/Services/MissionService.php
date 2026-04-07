<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MissionService
{
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
    }

    public function supprimer(OrdreMission $ordre): void
    {
        if ($ordre->statut !== 'BROUILLON') {
            throw ValidationException::withMessages([
                'statut' => ['Seuls les ordres en brouillon peuvent etre supprimes.'],
            ]);
        }

        $ordre->delete();
    }

    public function soumettre(OrdreMission $ordre): OrdreMission
    {
        if ($ordre->statut !== 'BROUILLON') {
            throw ValidationException::withMessages([
                'statut' => ['Cet ordre ne peut pas etre soumis.'],
            ]);
        }

        $ordre->update(['statut' => 'SOUMIS']);

        return $ordre->fresh(['agent', 'taux']);
    }

    public function validerN1(OrdreMission $ordre, User $user): OrdreMission
    {
        if ($ordre->statut !== 'SOUMIS') {
            throw ValidationException::withMessages([
                'statut' => ['Cet ordre ne peut pas etre valide.'],
            ]);
        }

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

        $ordre->update([
            'statut' => 'VALIDE_N1',
            'valide_par_chef' => $user->id,
        ]);

        return $ordre->fresh(['agent', 'taux']);
    }

    public function approuver(OrdreMission $ordre, User $user): OrdreMission
    {
        if ($ordre->statut !== 'VALIDE_N1') {
            throw ValidationException::withMessages([
                'statut' => ['Cet ordre ne peut pas etre approuve.'],
            ]);
        }

        if (! $user->hasRole('DIRECTEUR', 'ADMIN')) {
            throw ValidationException::withMessages([
                'role' => ['L\'approbation necessite le role DIRECTEUR.'],
            ]);
        }

        $ordre->update([
            'statut' => 'APPROUVE_DG',
            'valide_par_dg' => $user->id,
        ]);

        return $ordre->fresh(['agent', 'taux']);
    }

    public function rejeter(OrdreMission $ordre, string $commentaire): OrdreMission
    {
        if (! in_array($ordre->statut, ['SOUMIS', 'VALIDE_N1'])) {
            throw ValidationException::withMessages([
                'statut' => ['Cet ordre ne peut pas etre rejete.'],
            ]);
        }

        $ordre->update([
            'statut' => 'REJETE',
            'commentaire_rejet' => $commentaire,
        ]);

        return $ordre->fresh(['agent', 'taux']);
    }
}
