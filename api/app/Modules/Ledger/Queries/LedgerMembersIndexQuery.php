<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Data\LedgerMemberListItem;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Support\Collection;

class LedgerMembersIndexQuery
{
    public function __construct(
        private readonly FinancialProfileService $financialProfileService,
    ) {}

    /**
     * @return Collection<int, LedgerMemberListItem>
     */
    public function execute(Ledger $ledger, string $date): Collection
    {
        $users = $ledger->allUsers()->get();

        /** @var list<int> $userIds */
        $userIds = array_values($users->modelKeys());

        $shareableByUser = $this->financialProfileService->shareableIncomeForUsers(
            $ledger,
            $userIds,
            $date,
        );

        return $users->map(function (User $user) use ($shareableByUser): LedgerMemberListItem {
            return new LedgerMemberListItem(
                id: $user->id,
                name: $user->name,
                shareable_income: $shareableByUser[$user->id] ?? 0,
                email: $user->email,
                role: $user->pivot->role ?? 'member',
                is_active: $user->pivot->deleted_at === null,
            );
        });
    }
}
