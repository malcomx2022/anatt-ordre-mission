<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feed in-app de la cloche : notifications personnelles de l'utilisateur courant.
 *
 * Chaque utilisateur ne voit et ne modifie QUE ses propres notifications. Aucune
 * permission spécifique — c'est du personnel, ouvert à tout compte authentifié.
 */
class NotificationController extends Controller
{
    /** Les 30 dernières notifications de l'utilisateur + compteur de non-lues. */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $items = Notification::pour($userId)->latest()->limit(30)->get();

        return response()->json([
            'data' => $items->map(fn (Notification $n) => $this->presenter($n))->all(),
            'meta' => ['non_lues' => Notification::pour($userId)->nonLues()->count()],
        ]);
    }

    /** Marque UNE notification comme lue (uniquement la sienne). */
    public function marquerLu(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== (int) $request->user()->id, 404);

        if ($notification->lu_le === null) {
            $notification->update(['lu_le' => now()]);
        }

        return $this->success($this->presenter($notification->refresh()));
    }

    /** Marque toutes les notifications de l'utilisateur comme lues. */
    public function marquerToutLu(Request $request): JsonResponse
    {
        Notification::pour((int) $request->user()->id)->nonLues()->update(['lu_le' => now()]);

        return $this->success(['non_lues' => 0], 'Notifications marquées comme lues.');
    }

    /** @return array<string,mixed> */
    private function presenter(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'titre' => $n->titre,
            'message' => $n->message,
            'lu' => $n->lu_le !== null,
            'created_at' => $n->created_at?->toISOString(),
        ];
    }
}
