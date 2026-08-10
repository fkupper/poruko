<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('token');
            $table->timestamp('accepted_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn(['email', 'accepted_at']);
        });
    }
};
