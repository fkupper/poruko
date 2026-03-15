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
        Schema::table('ledgers', function (Blueprint $table): void {
            $table->string('settlement_timezone')
                ->nullable()
                ->after('pool_base_budget');
            $table->unsignedTinyInteger('settlement_cutoff_day')
                ->nullable()
                ->after('settlement_timezone');
            $table->time('settlement_cutoff_time')
                ->nullable()
                ->after('settlement_cutoff_day');
            $table->boolean('settlement_auto_execute_enabled')
                ->default(false)
                ->after('settlement_cutoff_time');

            $table->index(
                [
                    'settlement_auto_execute_enabled',
                    'settlement_timezone',
                    'settlement_cutoff_day',
                    'settlement_cutoff_time',
                ],
                'ledgers_settlement_cycle_due_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table): void {
            $table->dropIndex('ledgers_settlement_cycle_due_idx');

            $table->dropColumn([
                'settlement_timezone',
                'settlement_cutoff_day',
                'settlement_cutoff_time',
                'settlement_auto_execute_enabled',
            ]);
        });
    }
};
