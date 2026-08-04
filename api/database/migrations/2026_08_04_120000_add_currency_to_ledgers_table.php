<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table): void {
            $table->string('currency', 3)->default('EUR')->after('settlement_mode');
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
