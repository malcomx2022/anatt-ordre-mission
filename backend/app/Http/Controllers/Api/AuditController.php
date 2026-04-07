<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->auditService->lister(
            $request->only(['action', 'user_id', 'date_debut', 'date_fin']),
            $request->integer('per_page', 20),
        );

        return response()->json($logs);
    }

    public function show(AuditLog $audit): JsonResponse
    {
        return $this->success($this->auditService->afficher($audit));
    }
}
