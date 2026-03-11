<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
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

    public function store(StoreAccountRequest $request, Ledger $ledger): JsonResponse
    {
        $account = $ledger->accounts()->create($request->validated());

        return AccountResource::make($account)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return AccountResource::make($account);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): AccountResource
    {
        $account->update($request->validated());

        return AccountResource::make($account->refresh());
    }

    public function destroy(Ledger $ledger, Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->postings()->exists()) {
            return response()->json([
                'message' => 'Account cannot be deleted once postings exist.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $account->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
