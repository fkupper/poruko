<?php

use App\Enums\TransactionSplitRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_split_rule_check');

        $splitRules = implode(', ', array_map(
            static fn (TransactionSplitRule $splitRule): string => "'{$splitRule->value}'",
            TransactionSplitRule::cases(),
        ));

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_split_rule_check CHECK (split_rule IN ({$splitRules}))");
    }

    /**
     * Reverse the migrations.
     *
     * Note: Rollback will fail if any transactions have split_rule 'proportional' or 'manual'.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_split_rule_check');

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_split_rule_check CHECK (split_rule IN ('equal', 'individual'))");
    }
};
