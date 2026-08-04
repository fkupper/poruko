<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\SettlementMode;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use App\Modules\Ledger\Data\CreateLedgerData;
use Illuminate\Support\Facades\DB;

final readonly class CreateLedgerAction
{
    public function __construct(
        private EnsureMainPersonalAccountForLedgerMemberAction $ensureAccountAction
    ) {}

    public function execute(User $user, CreateLedgerData $data): Ledger
    {
        $currencyCode = $data->currency ?? config('currencies.default', 'EUR');
        $mode = $data->settlementMode ?? SettlementMode::JointClearinghouse->value;
        $cutoffDay = $data->settlementCutoffDay ?? 1;

        return DB::transaction(function () use ($user, $data, $currencyCode, $mode, $cutoffDay): Ledger {
            $ledger = Ledger::query()->create([
                'name' => $data->name,
                'currency' => $currencyCode,
                'settlement_mode' => $mode,
                'settlement_timezone' => 'UTC',
                'settlement_cutoff_day' => $cutoffDay,
                'settlement_cutoff_time' => '00:00:00',
                'settlement_auto_execute_enabled' => false,
            ]);

            $ledger->users()->attach($user->id, ['role' => 'admin']);

            if ($mode === SettlementMode::JointClearinghouse->value) {
                Account::query()->create([
                    'ledger_id' => $ledger->id,
                    'type' => AccountType::Pool,
                    'name' => 'House Joint Account',
                    'base_budget' => 0,
                ]);
            }

            $ledgerUser = LedgerUser::query()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->ensureAccountAction->execute($ledgerUser);

            return $ledger;
        });
    }
}
