<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('payer_account_id')
                ->nullable()
                ->after('ledger_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedBigInteger('amount')->default(0)->after('payer_account_id');

            $table->index(['ledger_id', 'payer_account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['ledger_id', 'payer_account_id', 'date']);
            $table->dropConstrainedForeignId('payer_account_id');
            $table->dropColumn('amount');
        });
    }
};
