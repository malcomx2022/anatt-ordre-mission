<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
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
            $request->only(['action', 'entite', 'user_id', 'date_debut', 'date_fin']),
            $this->getPerPage($request),
        );

        return AuditLogResource::collection($logs)->response();
    }

    public function show(AuditLog $audit): JsonResponse
    {
        return $this->success(new AuditLogResource($this->auditService->afficher($audit)));
    }
}
