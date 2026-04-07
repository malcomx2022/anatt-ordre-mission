<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrdreMissionRequest;
use App\Http\Requests\UpdateOrdreMissionRequest;
use App\Http\Resources\OrdreMissionResource;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrdreMission::with(['agent', 'taux']);

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        if ($agentId = $request->get('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->where('date_depart', '>=', $dateDebut);
        }

        if ($dateFin = $request->get('date_fin')) {
            $query->where('date_retour', '<=', $dateFin);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('objet', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        $ordres = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return OrdreMissionResource::collection($ordres)->response();
    }

    public function store(StoreOrdreMissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $nbJours = Carbon::parse($data['date_retour'])->diffInDays(Carbon::parse($data['date_depart'])) + 1;
        $taux = TauxPerDiem::findOrFail($data['taux_id']);
        $montant = $nbJours * $taux->montant_journalier;

        $ordre = OrdreMission::create([
            ...$data,
            'numero' => OrdreMission::generateNumero(),
            'nb_jours' => $nbJours,
            'montant_per_diem' => $montant,
            'statut' => 'BROUILLON',
        ]);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->load(['agent', 'taux'])),
            'message' => 'Ordre de mission cree avec succes.',
        ], 201);
    }

    public function show(OrdreMission $ordre): JsonResponse
    {
        $ordre->load(['agent', 'taux', 'valideurChef', 'valideurDG', 'paiements']);

        return response()->json([
            'data' => new OrdreMissionResource($ordre),
        ]);
    }

    public function update(UpdateOrdreMissionRequest $request, OrdreMission $ordre): JsonResponse
    {
        if ($ordre->statut !== 'BROUILLON') {
            return response()->json(['message' => 'Seuls les ordres en brouillon peuvent etre modifies.'], 422);
        }

        $data = $request->validated();

        if (isset($data['date_depart']) && isset($data['date_retour'])) {
            $data['nb_jours'] = Carbon::parse($data['date_retour'])->diffInDays(Carbon::parse($data['date_depart'])) + 1;
            if (isset($data['taux_id'])) {
                $taux = TauxPerDiem::findOrFail($data['taux_id']);
                $data['montant_per_diem'] = $data['nb_jours'] * $taux->montant_journalier;
            }
        }

        $ordre->update($data);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->fresh(['agent', 'taux'])),
            'message' => 'Ordre de mission mis a jour.',
        ]);
    }

    public function destroy(OrdreMission $ordre): JsonResponse
    {
        if ($ordre->statut !== 'BROUILLON') {
            return response()->json(['message' => 'Seuls les ordres en brouillon peuvent etre supprimes.'], 422);
        }

        $ordre->delete();

        return response()->json(['message' => 'Ordre de mission supprime.']);
    }

    public function soumettre(OrdreMission $ordre): JsonResponse
    {
        if ($ordre->statut !== 'BROUILLON') {
            return response()->json(['message' => 'Cet ordre ne peut pas etre soumis.'], 422);
        }

        $ordre->update(['statut' => 'SOUMIS']);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->fresh(['agent', 'taux'])),
            'message' => 'Ordre de mission soumis pour validation.',
        ]);
    }

    public function validerN1(Request $request, OrdreMission $ordre): JsonResponse
    {
        if ($ordre->statut !== 'SOUMIS') {
            return response()->json(['message' => 'Cet ordre ne peut pas etre valide.'], 422);
        }

        $ordre->update([
            'statut' => 'VALIDE_N1',
            'valide_par_chef' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->fresh(['agent', 'taux'])),
            'message' => 'Ordre de mission valide par le chef de service.',
        ]);
    }

    public function approuver(Request $request, OrdreMission $ordre): JsonResponse
    {
        if ($ordre->statut !== 'VALIDE_N1') {
            return response()->json(['message' => 'Cet ordre ne peut pas etre approuve.'], 422);
        }

        $ordre->update([
            'statut' => 'APPROUVE_DG',
            'valide_par_dg' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->fresh(['agent', 'taux'])),
            'message' => 'Ordre de mission approuve par la direction.',
        ]);
    }

    public function rejeter(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate([
            'commentaire' => 'required|string|min:10',
        ]);

        if (! in_array($ordre->statut, ['SOUMIS', 'VALIDE_N1'])) {
            return response()->json(['message' => 'Cet ordre ne peut pas etre rejete.'], 422);
        }

        $ordre->update([
            'statut' => 'REJETE',
            'commentaire_rejet' => $request->commentaire,
        ]);

        return response()->json([
            'data' => new OrdreMissionResource($ordre->fresh(['agent', 'taux'])),
            'message' => 'Ordre de mission rejete.',
        ]);
    }

    public function pdf(OrdreMission $ordre): JsonResponse
    {
        // PDF generation will use DomPDF
        return response()->json([
            'data' => ['url' => "/api/ordres/{$ordre->id}/pdf/download"],
            'message' => 'PDF genere.',
        ]);
    }

    public function historique(OrdreMission $ordre): JsonResponse
    {
        $logs = \App\Models\AuditLog::where('cible', "ordre_mission:{$ordre->id}")
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $logs]);
    }
}
