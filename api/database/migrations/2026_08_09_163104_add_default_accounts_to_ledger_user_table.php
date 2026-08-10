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
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->foreignId('default_payment_account_id')
                ->nullable()
                ->after('main_personal_account_id')
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_expense_account_id')
                ->nullable()
                ->after('default_payment_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_expense_account_id');
            $table->dropConstrainedForeignId('default_payment_account_id');
        });
    }
};
