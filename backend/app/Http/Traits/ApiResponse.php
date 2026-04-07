<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = [];

        if ($data instanceof ResourceCollection) {
            return $data->additional(['message' => $message])->response()->setStatusCode($code);
        }

        if ($data !== null) {
            $response['data'] = $data instanceof JsonResource ? $data : $data;
        }

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    protected function created($data = null, string $message = 'Ressource creee avec succes.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = ['message' => $message];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(string $message = 'Ressource introuvable.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Acces refuse.'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function unprocessable(string $message, array $errors = []): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }
}
