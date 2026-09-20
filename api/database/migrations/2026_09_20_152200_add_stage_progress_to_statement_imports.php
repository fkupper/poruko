<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('statement_imports', function (Blueprint $table): void {
            $table->string('stage', 32)->default('queued')->after('status');
            $table->unsignedInteger('progress_current')->default(0)->after('failed_count');
            $table->unsignedInteger('progress_total')->default(0)->after('progress_current');
        });
    }

    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table): void {
            $table->dropColumn(['stage', 'progress_current', 'progress_total']);
        });
    }
};
