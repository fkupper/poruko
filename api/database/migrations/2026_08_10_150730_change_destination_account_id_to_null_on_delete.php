<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('destination_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->foreign('destination_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('destination_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->foreign('destination_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();
        });
    }
};
