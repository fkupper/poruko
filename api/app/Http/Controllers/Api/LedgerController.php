<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerResource;
use App\Models\User;
use App\Modules\Ledger\Queries\UserLedgersIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LedgerController extends Controller
{
    public function index(Request $request, UserLedgersIndexQuery $query): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return LedgerResource::collection(
            $query->execute($user),
        );
    }
}
