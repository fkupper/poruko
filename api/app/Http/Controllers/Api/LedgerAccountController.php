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
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Ledger\Data\UpdateAccountData;
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
        $data = CreateAccountData::fromArray($request->validated());
        $account = $action->execute($ledger, $data);

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
        $data = UpdateAccountData::fromArray($request->validated());

        return AccountResource::make(
            $this->withAccountIsMainMeta($action->execute($account, $data)),
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
            DeleteAccountResult::AttachedToProcess => response()->json([
                'message' => 'Account cannot be deleted because it is attached to an active process.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
        };
    }

    private function withAccountIsMainMeta(Account $account): Account
    {
        $account->setAttribute('is_main', $this->personalAccountQuery->isMain($account));

        return $account;
    }
}
