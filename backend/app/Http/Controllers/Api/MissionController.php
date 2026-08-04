<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMissionRequest;
use App\Http\Resources\MissionResource;
use App\Http\Resources\OrdreMissionResource;
use App\Models\AuditLog;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Services\MissionService;
use App\Services\PdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
        private readonly PdfGenerator $pdfGenerator,
    ) {}

    /**
     * Liste filtrée des ordres de mission.
     * Filtres : statut, période (date_debut/date_fin), opérateur, zone, agent, recherche.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrdreMission::with(['agent', 'mission', 'versements']);

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        if ($zone = $request->get('zone')) {
            $query->where('zone_code', $zone);
        }

        if ($agentId = $request->get('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($operateur = $request->get('operateur')) {
            $query->whereHas('agent', fn ($q) => $q->where('operateur', $operateur));
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->whereHas('mission', fn ($q) => $q->where('date_depart', '>=', $dateDebut));
        }

        if ($dateFin = $request->get('date_fin')) {
            $query->whereHas('mission', fn ($q) => $q->where('date_retour', '<=', $dateFin));
        }

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $ordres = $query->orderByDesc('created_at')->paginate($this->getPerPage($request));

        return OrdreMissionResource::collection($ordres)->response();
    }

    /**
     * Liste des missions — point d'entrée naturel du circuit.
     *
     * Une mission est l'unité de travail réelle : elle porte N ordres de mission
     * et DEUX états de paiement (avances puis reliquats). Chaque ligne indique où
     * en sont ces deux documents, ce qui évite d'avoir à passer par un OM pour
     * retrouver l'état à signer.
     */
    public function missions(Request $request): JsonResponse
    {
        // etatPaiement (relation scopée AVANCE) sert aux filtres et à l'avance ;
        // etatsPaiement charge les DEUX tranches, dont on extrait le reliquat.
        $missions = Mission::query()
            ->with(['etatPaiement', 'etatsPaiement'])
            ->withCount(['ordresMission as nb_ordres' => fn ($q) => $q->whereNotIn('statut', ['REJETE', 'ANNULE'])])
            ->withSum(['ordresMission as montant_total' => fn ($q) => $q->whereNotIn('statut', ['REJETE', 'ANNULE'])], 'montant_total')
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.$request->string('search').'%';
                $q->where(fn ($inner) => $inner->where('objet', 'like', $like)
                    ->orWhere('destination', 'like', $like)
                    ->orWhere('imputation_budgetaire', 'like', $like));
            })
            // « sans_etat » : les missions dont le récapitulatif reste à établir.
            ->when($request->input('etat') === 'sans_etat', fn ($q) => $q->whereDoesntHave('etatPaiement'))
            ->when($request->filled('etat') && $request->input('etat') !== 'sans_etat',
                fn ($q) => $q->whereHas('etatPaiement', fn ($e) => $e->where('statut', $request->string('etat'))))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => collect($missions->items())->map(fn (Mission $m) => [
                'id' => $m->id,
                'objet' => $m->objet,
                'destination' => $m->destination,
                'zone_code' => $m->zone_code,
                'date_depart' => $m->date_depart?->toDateString(),
                'date_retour' => $m->date_retour?->toDateString(),
                'imputation_budgetaire' => $m->imputation_budgetaire,
                'prise_en_charge_tiers' => (bool) $m->prise_en_charge_tiers,
                'nb_ordres' => (int) $m->nb_ordres,
                'montant_total' => (int) $m->montant_total,
                // null tant que le récapitulatif n'a pas été établi.
                'etat_statut' => $m->etatPaiement?->statut,
                // Sans préfixe : les écrans posent eux-mêmes le « N° ».
                'etat_numero' => EtatPaiement::normaliserNumero($m->etatPaiement?->numero),
                // Avancement du second document du circuit (état des reliquats),
                // extrait explicitement par tranche (indépendant de l'ordre de chargement).
                'etat_reliquat_statut' => $m->etatsPaiement->firstWhere('tranche', 'RELIQUAT')?->statut,
                'etat_reliquat_numero' => EtatPaiement::normaliserNumero(
                    $m->etatsPaiement->firstWhere('tranche', 'RELIQUAT')?->numero,
                ),
            ]),
            'meta' => [
                'current_page' => $missions->currentPage(),
                'last_page' => $missions->lastPage(),
                'per_page' => $missions->perPage(),
                'total' => $missions->total(),
            ],
        ]);
    }

    public function show(OrdreMission $ordre): JsonResponse
    {
        // On charge l'état de paiement de la mission : c'est LUI qui porte le
        // circuit de validation (contrôle → visa DAF → validation DG). La fiche
        // OM n'affiche donc pas d'action de circuit, elle renvoie vers l'état.
        $ordre->load(['agent', 'mission.etatPaiement', 'versements']);

        return $this->success(new OrdreMissionResource($ordre));
    }

    /**
     * Retranscription d'une mission SEMO : 1 mission + N ordres de mission
     * (participants + CAV), avec calcul d'indemnité et versements EN_ATTENTE.
     */
    public function storeMission(StoreMissionRequest $request): JsonResponse
    {
        $mission = $this->missionService->creerAvecOrdres($request->validated(), $request->user());

        return $this->created(
            new MissionResource($mission),
            'Mission retranscrite : '.$mission->ordresMission->count().' ordre(s) de mission créé(s).',
        );
    }

    public function pdf(OrdreMission $ordre): JsonResponse
    {
        $filename = $this->pdfGenerator->genererOrdreMission($ordre);

        return $this->success([
            'url' => "/api/ordres/{$ordre->id}/pdf/download",
            'fichier' => $filename,
        ], 'PDF genere.');
    }

    public function pdfDownload(OrdreMission $ordre)
    {
        return $this->pdfGenerator->telecharger($ordre);
    }

    /**
     * Brouillon de l'OM : épreuve filigranée téléchargeable AVANT validation.
     *
     * Route distincte de /pdf plutôt qu'un paramètre : la sémantique du PDF
     * officiel reste intacte et les deux usages se distinguent dans l'audit.
     * Toujours régénéré (jamais servi depuis le cache) : un brouillon doit
     * refléter l'état courant de l'OM au moment où on le demande.
     */
    public function pdfBrouillon(OrdreMission $ordre)
    {
        $this->pdfGenerator->genererOrdreMission($ordre, brouillon: true);

        return $this->pdfGenerator->telecharger($ordre, brouillon: true);
    }

    public function historique(OrdreMission $ordre): JsonResponse
    {
        $logs = AuditLog::where('entite', 'ordre_mission')
            ->where('entite_id', $ordre->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($logs);
    }
}
