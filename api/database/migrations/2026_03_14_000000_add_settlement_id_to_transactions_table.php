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
            $table->foreignId('settlement_id')
                ->nullable()
                ->after('ledger_id')
                ->constrained('settlements')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['settlement_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['settlement_id', 'date']);
            $table->dropConstrainedForeignId('settlement_id');
        });
    }
};
