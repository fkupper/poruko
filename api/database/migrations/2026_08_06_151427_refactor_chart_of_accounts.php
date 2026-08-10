<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['ledger_id', 'credit_account_id', 'date']);
            $table->dropConstrainedForeignId('debit_account_id');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->renameColumn('credit_account_id', 'payer_account_id');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['ledger_id', 'payer_account_id', 'date']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('debit_account_id');
        });

        Schema::table('recurring_transactions', function (Blueprint $table): void {
            $table->renameColumn('credit_account_id', 'payer_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['ledger_id', 'payer_account_id', 'date']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->renameColumn('payer_account_id', 'credit_account_id');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('debit_account_id')
                ->nullable()
                ->after('credit_account_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['ledger_id', 'credit_account_id', 'date']);
        });
    }
};
