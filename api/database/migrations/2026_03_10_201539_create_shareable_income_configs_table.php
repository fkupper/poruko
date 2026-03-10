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
        Schema::create('financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->jsonb('incomes');
            $table->jsonb('deductions');
            $table->timestamps();

            $table->unique(['ledger_id', 'user_id', 'valid_from']);
            $table->index(['ledger_id', 'user_id', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_profiles');
    }
};
