<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->where('created_at', '>=', $dateDebut);
        }

        if ($dateFin = $request->get('date_fin')) {
            $query->where('created_at', '<=', $dateFin);
        }

        $logs = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }

    public function show(AuditLog $audit): JsonResponse
    {
        $audit->load('user');

        return response()->json(['data' => $audit]);
    }
}
