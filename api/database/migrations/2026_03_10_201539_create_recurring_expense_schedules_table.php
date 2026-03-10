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
        Schema::create('pending_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->jsonb('raw_data');
            $table->bigInteger('suggested_amount')->nullable();
            $table->string('suggested_split_rule', 50)->nullable();
            $table->jsonb('suggested_participants')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['ledger_id', 'status']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_transactions');
    }
};
