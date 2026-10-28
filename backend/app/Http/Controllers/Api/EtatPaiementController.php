<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Services\EtatPaiementPdf;
use App\Services\EtatPaiementService;
use App\Support\MontantEnLettres;
use App\Support\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * État de paiement d'une mission : récapitulatif, brouillon téléchargeable,
 * puis circuit contrôle → visa DAF → validation finale du DG.
 */
class EtatPaiementController extends Controller
{
    public function __construct(
        private readonly EtatPaiementService $service,
        private readonly EtatPaiementPdf $pdf,
    ) {}

    /**
     * File de travail : les états, filtrables par statut.
     *
     * C'est l'écran d'entrée du circuit — le contrôleur, le DAF et le DG y
     * retrouvent les états qui attendent leur intervention.
     */
    public function index(Request $request): JsonResponse
    {
        $etats = EtatPaiement::with('mission')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('tranche'), fn ($q) => $q->where('tranche', $request->string('tranche')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request) {
                $like = '%'.$request->string('search').'%';
                $inner->where('numero', 'like', $like)
                    ->orWhereHas('mission', fn ($m) => $m->where('objet', 'like', $like)
                        ->orWhere('destination', 'like', $like));
            }))
            ->orderByDesc('id')
            ->paginate($this->getPerPage($request, 20));

        return response()->json([
            'data' => collect($etats->items())->map(fn (EtatPaiement $e) => $this->presenter($e, partAvance: false)),
            'meta' => [
                'current_page' => $etats->currentPage(),
                'last_page' => $etats->lastPage(),
                'per_page' => $etats->perPage(),
                'total' => $etats->total(),
            ],
        ]);
    }

    /**
     * États de la mission, par tranche : l'écran suit les deux documents.
     * `data: null` tant qu'aucun récapitulatif n'a été produit.
     */
    public function show(Mission $mission): JsonResponse
    {
        $etats = EtatPaiement::where('mission_id', $mission->id)->get()->keyBy('tranche');

        if ($etats->isEmpty()) {
            return $this->success(null);
        }

        return $this->success([
            'avance' => isset($etats[EtatPaiement::TRANCHE_AVANCE])
                ? $this->presenter($etats[EtatPaiement::TRANCHE_AVANCE])
                : null,
            'reliquat' => isset($etats[EtatPaiement::TRANCHE_RELIQUAT])
                ? $this->presenter($etats[EtatPaiement::TRANCHE_RELIQUAT])
                : null,
        ]);
    }

    /** Récapitulatif : crée ou rafraîchit l'état d'une tranche en BROUILLON. */
    public function recapituler(Request $request, Mission $mission): JsonResponse
    {
        $tranche = $request->validate([
            'tranche' => ['nullable', 'string', 'in:'.implode(',', EtatPaiement::TRANCHES)],
        ])['tranche'] ?? EtatPaiement::TRANCHE_AVANCE;

        $etat = $this->service->recapituler($mission, $request->user(), $tranche);

        return $this->success(
            $this->presenter($etat),
            "Récapitulatif {$etat->libelleTranche()} établi. Le brouillon est téléchargeable.",
        );
    }

    /** Numérotation manuelle, selon le registre de l'agence. */
    public function definirNumero(Request $request, EtatPaiement $etat): JsonResponse
    {
        $request->validate(['numero' => 'required|string|max:100']);

        $etat = $this->service->definirNumero($etat, $request->string('numero'), $request->user());

        return $this->success($this->presenter($etat), 'Numéro enregistré.');
    }

    /** Étape 1 — contrôle de conformité. */
    public function controler(Request $request, EtatPaiement $etat): JsonResponse
    {
        $etat = $this->service->controler($etat, $request->user());

        return $this->success($this->presenter($etat), 'État contrôlé : transmis au DAF pour visa.');
    }

    /** Étape 2 — visa du DAF. */
    public function viser(Request $request, EtatPaiement $etat): JsonResponse
    {
        $etat = $this->service->viser($etat, $request->user());

        return $this->success($this->presenter($etat), 'Visa du DAF apposé : transmis au Directeur Général.');
    }

    /** Étape 3 — validation finale : ouvre la génération des fichiers de paiement. */
    public function approuver(Request $request, EtatPaiement $etat): JsonResponse
    {
        $etat = $this->service->approuver($etat, $request->user());

        return $this->success(
            $this->presenter($etat),
            'État de paiement validé et verrouillé. Les fichiers de paiement peuvent être générés.',
        );
    }

    /** Rejet motivé, possible à chaque étape. */
    public function rejeter(Request $request, EtatPaiement $etat): JsonResponse
    {
        $request->validate(['motif' => 'required|string|min:10']);

        $etat = $this->service->rejeter($etat, $request->string('motif'), $request->user());

        return $this->success($this->presenter($etat), 'État de paiement rejeté.');
    }

    /** Brouillon filigrané : l'épreuve que l'on relit avant d'engager le circuit. */
    public function brouillon(Request $request, Mission $mission)
    {
        $etat = $this->etatPourTranche($request, $mission);

        return $this->pdf->telecharger($etat, brouillon: true);
    }

    /** État définitif, avec les signatures apposées. */
    public function telecharger(Request $request, Mission $mission)
    {
        $etat = $this->etatPourTranche($request, $mission);

        return $this->pdf->telecharger($etat, brouillon: ! $etat->estValide());
    }

    /**
     * État général consolidé (avances + reliquats) : le document de clôture
     * de la mission. 422 tant que les deux tranches ne sont pas validées.
     */
    public function telechargerFinal(Mission $mission)
    {
        return $this->pdf->telechargerFinal($mission);
    }

    /** État de la tranche demandée (AVANCE par défaut) — 404 si non établi. */
    private function etatPourTranche(Request $request, Mission $mission): EtatPaiement
    {
        $tranche = $request->validate([
            'tranche' => ['nullable', 'string', 'in:'.implode(',', EtatPaiement::TRANCHES)],
        ])['tranche'] ?? EtatPaiement::TRANCHE_AVANCE;

        return EtatPaiement::where('mission_id', $mission->id)
            ->where('tranche', $tranche)
            ->firstOrFail();
    }

    /**
     * Représentation de l'état pour l'UI : statut, montants, étapes franchies et
     * prochaine action attendue.
     *
     * @return array<string,mixed>
     */
    private function presenter(EtatPaiement $etat, bool $partAvance = true): array
    {
        return [
            'id' => $etat->id,
            'mission_id' => $etat->mission_id,
            'tranche' => $etat->tranche,
            'mission' => $etat->relationLoaded('mission') && $etat->mission
                ? [
                    'id' => $etat->mission->id,
                    'objet' => $etat->mission->objet,
                    'destination' => $etat->mission->destination,
                    'date_depart' => $etat->mission->date_depart?->toDateString(),
                    'date_retour' => $etat->mission->date_retour?->toDateString(),
                ]
                : null,
            // Sans préfixe : les écrans posent eux-mêmes le « N° ».
            'numero' => EtatPaiement::normaliserNumero($etat->numero),
            'statut' => $etat->statut,
            'nb_beneficiaires' => $etat->nb_beneficiaires,
            'montant_total' => $etat->montant_total,
            'montant_avance' => $etat->montant_avance,
            'montant_reliquat' => $etat->montant_reliquat,
            'montant_total_lettres' => MontantEnLettres::mention($etat->montant_total),
            // Part d'avance des OM de la mission (null : exonérée) — l'écran l'affiche
            // à la place du réglage courant, qui a pu changer depuis la retranscription.
            // La file de travail (index) s'en passe : une requête de plus par ligne.
            'part_avance' => $partAvance ? $etat->mission?->partAvanceReelle() : null,
            'controle' => $this->etape($etat->controle_nom, $etat->controle_fonction, $etat->controle_le, $etat->signature_controle_hash),
            'visa_daf' => $this->etape($etat->visa_daf_nom, $etat->visa_daf_fonction, $etat->visa_daf_le, $etat->signature_daf_hash),
            'validation_dg' => $this->etape($etat->valide_dg_nom, $etat->valide_dg_fonction, $etat->valide_dg_le, $etat->signature_dg_hash),
            'commentaire_rejet' => $etat->commentaire_rejet,
            'prochaine_etape' => match ($etat->statut) {
                'BROUILLON' => 'CONTROLE',
                'CONTROLE' => 'VISA_DAF',
                'VISA_DAF' => 'VALIDATION_DG',
                default => null,
            },
            'paiement_ouvert' => $etat->estValide(),
        ];
    }

    /** @return array{nom:string, fonction:?string, date:?string, code:?string}|null */
    private function etape(?string $nom, ?string $fonction, mixed $date, ?string $hash): ?array
    {
        if (empty($nom)) {
            return null;
        }

        return [
            'nom' => $nom,
            'fonction' => $fonction,
            'date' => $date?->toISOString(),
            'code' => Workflow::codeVerification($hash),
        ];
    }
}
