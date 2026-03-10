<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController
{
    /**
     * @group System
     * Health check
     *
     * Simple readiness endpoint.
     *
     * @response 200 {"status":"ok"}
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}

