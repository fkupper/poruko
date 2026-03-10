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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('type', 50); // manual, recurring, settlement, reversal
            $table->string('split_rule', 50); // proportional, equal, individual
            $table->jsonb('participants');
            $table->string('description')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index(['ledger_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
