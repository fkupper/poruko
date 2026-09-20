<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('settlement_transfer_key', 64)
                ->nullable()
                ->after('source_metadata');

            $table->unique(['ledger_id', 'settlement_transfer_key']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['ledger_id', 'settlement_transfer_key']);
            $table->dropColumn('settlement_transfer_key');
        });
    }
};
