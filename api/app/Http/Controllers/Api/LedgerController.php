<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerRequest;
use App\Http\Resources\LedgerResource;
use App\Models\User;
use App\Modules\Ledger\Actions\CreateLedgerAction;
use App\Modules\Ledger\Data\CreateLedgerData;
use App\Modules\Ledger\Queries\UserLedgersIndexQuery;
use Illuminate\Http\JsonResponse;
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

    public function store(StoreLedgerRequest $request, CreateLedgerAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ledger = $action->execute(
            user: $user,
            data: CreateLedgerData::fromArray($request->validated()),
        );

        return LedgerResource::make($ledger)
            ->response()
            ->setStatusCode(201);
    }
}
