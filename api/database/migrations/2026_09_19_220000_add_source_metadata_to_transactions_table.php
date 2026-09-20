<?php

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('source', 32)
                ->default(TransactionSource::Manual->value)
                ->after('type');
            $table->jsonb('source_metadata')
                ->nullable()
                ->after('source');

            $table->index(['ledger_id', 'source']);
        });

        DB::table('transactions')
            ->where('type', TransactionType::Recurring->value)
            ->update(['source' => TransactionSource::Blueprint->value]);

        DB::table('transactions')
            ->whereIn('type', [
                TransactionType::Settlement->value,
                TransactionType::Reversal->value,
            ])
            ->update(['source' => TransactionSource::System->value]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['ledger_id', 'source']);
            $table->dropColumn(['source', 'source_metadata']);
        });
    }
};
