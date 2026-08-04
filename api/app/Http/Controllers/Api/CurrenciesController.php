<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurrenciesController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var string $default */
        $default = config('currencies.default', 'EUR');

        /** @var array<string, array{code: string, symbol: string, name: string}> $available */
        $available = config('currencies.available', []);

        return response()->json([
            'default' => $default,
            'available' => array_values($available),
        ]);
    }
}
