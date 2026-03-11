<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'dev@example.com'],
            [
                'name' => 'Dev User',
                'password' => 'password',
            ],
        );

        $ledger = $user->ledgers()->first();

        if (! $ledger) {
            $ledger = Ledger::query()->create([
                'name' => 'Dev Ledger',
                'settlement_mode' => 'joint_clearinghouse',
                'pool_base_budget' => 0,
            ]);
            $ledger->users()->attach($user->id, ['role' => 'admin']);
        }

        $wallet = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-WALLET',
            ],
            [
                'owner_id' => $user->id,
                'type' => AccountType::Personal,
                'name' => 'My Wallet',
            ],
        );

        $savings = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-SAVINGS',
            ],
            [
                'owner_id' => $user->id,
                'type' => AccountType::Personal,
                'name' => 'Savings',
            ],
        );

        $pool = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-POOL',
            ],
            [
                'owner_id' => null,
                'type' => AccountType::Pool,
                'name' => 'House',
            ],
        );

        $external = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-EXT',
            ],
            [
                'owner_id' => null,
                'type' => AccountType::External,
                'name' => 'Landlord',
            ],
        );

        if ($ledger->transactions()->count() > 0) {
            return;
        }

        $postTransaction = new PostManualTransactionAction();

        $postTransaction->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $wallet->id,
            'amount' => 5000,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [
                ['account_id' => $pool->id],
                ['account_id' => $savings->id],
            ],
            'description' => 'Rent split',
            'date' => now()->subDays(5)->format('Y-m-d'),
            'type' => TransactionType::Manual->value,
        ]));

        $postTransaction->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $wallet->id,
            'amount' => 1200,
            'split_rule' => TransactionSplitRule::Individual->value,
            'participants' => [
                ['account_id' => $external->id, 'amount' => 1200],
            ],
            'description' => 'Rent payment',
            'date' => now()->subDays(3)->format('Y-m-d'),
            'type' => TransactionType::Manual->value,
        ]));

        $postTransaction->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $pool->id,
            'amount' => 3400,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [
                ['account_id' => $wallet->id],
                ['account_id' => $savings->id],
            ],
            'description' => 'Groceries',
            'date' => now()->subDay()->format('Y-m-d'),
            'type' => TransactionType::Manual->value,
        ]));
    }
}
