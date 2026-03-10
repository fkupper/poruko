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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained('ledgers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('type', 50); // personal, pool, external
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();

            $table->index(['ledger_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
