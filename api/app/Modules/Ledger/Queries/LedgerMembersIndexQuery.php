<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Support\Collection;

class LedgerMembersIndexQuery
{
    public function __construct(
        private readonly FinancialProfileService $financialProfileService,
    ) {}

    /**
     * @return Collection<int, object{id: int, name: string, shareable_income: int}>
     */
    public function execute(Ledger $ledger, string $date): Collection
    {
        $users = $ledger->users()->get();
        $userIds = $users->pluck('id')->all();

        $shareableByUser = $this->financialProfileService->shareableIncomeForUsers(
            $ledger,
            $userIds,
            $date,
        );

        return $users->map(function ($user) use ($shareableByUser) {
            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'shareable_income' => $shareableByUser[$user->id] ?? 0,
            ];
        });
    }
}
