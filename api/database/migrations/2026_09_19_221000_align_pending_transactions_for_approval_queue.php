<?php

use App\Enums\TransactionSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('pending_transactions', function (Blueprint $table): void {
            $table->foreignId('payer_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('destination_account_id')
                ->nullable()
                ->after('payer_account_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('suggested_description')
                ->nullable()
                ->after('raw_data');
            $table->date('date')
                ->nullable()
                ->after('suggested_participants');
            $table->string('source', 32)
                ->default(TransactionSource::Manual->value)
                ->after('date');
            $table->decimal('confidence', 5, 4)
                ->nullable()
                ->after('source');
            $table->text('rationale')
                ->nullable()
                ->after('confidence');
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by_user_id');
            $table->text('rejection_reason')
                ->nullable()
                ->after('reviewed_at');
            $table->foreignId('committed_transaction_id')
                ->nullable()
                ->unique()
                ->after('rejection_reason')
                ->constrained('transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('committed_transaction_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropConstrainedForeignId('destination_account_id');
            $table->dropConstrainedForeignId('payer_account_id');
            $table->dropColumn([
                'suggested_description',
                'date',
                'source',
                'confidence',
                'rationale',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};
