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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('payer_account_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->bigInteger('amount');
            $table->string('split_rule', 50);
            $table->jsonb('participants');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['ledger_id', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
