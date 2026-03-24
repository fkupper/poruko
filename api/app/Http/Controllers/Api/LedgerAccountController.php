<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeleteAccountResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Modules\Ledger\Actions\CreateAccountAction;
use App\Modules\Ledger\Actions\DeleteAccountAction;
use App\Modules\Ledger\Actions\UpdateAccountAction;
use App\Modules\Ledger\Queries\LedgerAccountIndexQuery;
use App\Modules\Ledger\Queries\PersonalAccountQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LedgerAccountController extends Controller
{
    public function __construct(
        private readonly LedgerAccountIndexQuery $ledgerAccountIndexQuery,
        private readonly PersonalAccountQuery $personalAccountQuery,
    ) {}

    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $accounts = $this->ledgerAccountIndexQuery->get($ledger);

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request, Ledger $ledger, CreateAccountAction $action): JsonResponse
    {
        $account = $action->execute($ledger, $request->validated());

        return AccountResource::make($this->withAccountIsMainMeta($account))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return AccountResource::make($this->withAccountIsMainMeta($account));
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account, UpdateAccountAction $action): AccountResource
    {
        return AccountResource::make(
            $this->withAccountIsMainMeta($action->execute($account, $request->validated())),
        );
    }

    public function destroy(Ledger $ledger, Account $account, DeleteAccountAction $action): JsonResponse
    {
        $this->authorize('delete', $account);

        return match ($action->execute($account)) {
            DeleteAccountResult::Deleted => response()->json([], Response::HTTP_NO_CONTENT),
            DeleteAccountResult::MainPersonalAccount => response()->json([
                'message' => 'Cannot delete your main personal account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
            DeleteAccountResult::LastPersonalAccount => response()->json([
                'message' => 'Cannot delete your last personal account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
            DeleteAccountResult::HasPostings => response()->json([
                'message' => 'Account cannot be deleted once postings exist.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
        };
    }

    private function withAccountIsMainMeta(Account $account): Account
    {
        $account->setAttribute('is_main', $this->personalAccountQuery->isMain($account));

        return $account;
    }
}
