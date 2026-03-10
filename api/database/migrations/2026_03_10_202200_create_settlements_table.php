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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestampTz('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['ledger_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
