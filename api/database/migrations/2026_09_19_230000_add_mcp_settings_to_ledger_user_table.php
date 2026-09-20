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
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->boolean('mcp_enabled')->default(false);
            $table->boolean('mcp_allow_read')->default(true);
            $table->boolean('mcp_allow_write')->default(false);
            $table->boolean('mcp_allow_destructive')->default(false);
            $table->string('mcp_post_mode')->default('approval_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->dropColumn([
                'mcp_enabled',
                'mcp_allow_read',
                'mcp_allow_write',
                'mcp_allow_destructive',
                'mcp_post_mode',
            ]);
        });
    }
};
