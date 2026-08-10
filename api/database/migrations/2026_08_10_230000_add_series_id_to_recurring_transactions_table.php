<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->uuid('series_id')->nullable();
        });

        DB::statement('UPDATE recurring_transactions SET series_id = gen_random_uuid() WHERE series_id IS NULL');

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->uuid('series_id')->nullable(false)->change();
            $table->index(['ledger_id', 'series_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropIndex(['ledger_id', 'series_id']);
            $table->dropColumn('series_id');
        });
    }
};
