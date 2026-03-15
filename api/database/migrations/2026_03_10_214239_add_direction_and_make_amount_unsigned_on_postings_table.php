<?php

use App\Enums\PostingDirection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('postings', function (Blueprint $table) {
            $table->string('direction', 10)->default(PostingDirection::Debit->value)->after('amount');
            $table->unsignedBigInteger('amount')->change();

            $table->index(['transaction_id', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postings', function (Blueprint $table) {
            $table->dropIndex(['transaction_id', 'direction']);
            $table->dropColumn('direction');
            $table->bigInteger('amount')->change();
        });
    }
};
