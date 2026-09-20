<?php

use App\Enums\PostingDirection;
use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $settlementTransactions = DB::table('transactions')
            ->where('type', TransactionType::Settlement->value)
            ->whereNull('destination_account_id')
            ->select('id')
            ->cursor();

        foreach ($settlementTransactions as $transaction) {
            $destinationAccountId = DB::table('postings')
                ->where('transaction_id', $transaction->id)
                ->where('direction', PostingDirection::Debit->value)
                ->value('account_id');

            if ($destinationAccountId !== null) {
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update(['destination_account_id' => $destinationAccountId]);
            }
        }
    }

    public function down(): void
    {
    }
};
