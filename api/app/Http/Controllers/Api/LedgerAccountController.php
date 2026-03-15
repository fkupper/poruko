<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Modules\Ledger\Actions\CreateAccountAction;
use App\Modules\Ledger\Actions\DeleteAccountAction;
use App\Modules\Ledger\Actions\UpdateAccountAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LedgerAccountController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $accounts = $ledger->accounts()
            ->latest()
            ->get();

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request, Ledger $ledger, CreateAccountAction $action): JsonResponse
    {
        $account = $action->execute($ledger, $request->validated());

        return AccountResource::make($account)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return AccountResource::make($account);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account, UpdateAccountAction $action): AccountResource
    {
        return AccountResource::make($action->execute($account, $request->validated()));
    }

    public function destroy(Ledger $ledger, Account $account, DeleteAccountAction $action): JsonResponse
    {
        $this->authorize('delete', $account);

        $result = $action->execute($account);

        if (!$result['deleted']) {
            return response()->json([
                'message' => $result['message'] ?? 'Account cannot be deleted.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
