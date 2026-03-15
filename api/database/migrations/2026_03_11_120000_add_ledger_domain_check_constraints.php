<?php

use App\Enums\PostingDirection;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $transactionTypes = implode(', ', array_map(
            static fn (TransactionType $type): string => "'{$type->value}'",
            TransactionType::cases(),
        ));
        $transactionSplitRules = implode(', ', array_map(
            static fn (TransactionSplitRule $splitRule): string => "'{$splitRule->value}'",
            TransactionSplitRule::cases(),
        ));
        $postingDirections = implode(', ', array_map(
            static fn (PostingDirection $direction): string => "'{$direction->value}'",
            PostingDirection::cases(),
        ));

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ({$transactionTypes}))");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_split_rule_check CHECK (split_rule IN ({$transactionSplitRules}))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE postings ADD CONSTRAINT postings_direction_check CHECK (direction IN ({$postingDirections}))");
        DB::statement('ALTER TABLE postings ADD CONSTRAINT postings_amount_positive_check CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE postings DROP CONSTRAINT IF EXISTS postings_amount_positive_check');
        DB::statement('ALTER TABLE postings DROP CONSTRAINT IF EXISTS postings_direction_check');
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_amount_positive_check');
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_split_rule_check');
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check');
    }
};
