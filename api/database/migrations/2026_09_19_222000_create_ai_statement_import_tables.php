<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('provider', 32);
            $table->text('api_key');
            $table->string('api_key_last_four', 4);
            $table->string('model')->nullable();
            $table->timestamps();
        });

        Schema::table('ledger_user', function (Blueprint $table): void {
            $table->boolean('ai_import_auto_create_accounts')
                ->default(false)
                ->after('default_expense_account_id');
        });

        Schema::create('bank_account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->char('external_account_fingerprint', 64);
            $table->string('external_account_name');
            $table->string('masked_identifier')->nullable();
            $table->string('ownership_type', 16);
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('suggested_account_id')
                ->nullable()
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['ledger_id', 'user_id', 'external_account_fingerprint'],
                'bank_account_mappings_unique_external',
            );
        });

        Schema::create('statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('ledger_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('status', 24)->default('queued');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('parsed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('statement_import_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_import_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('ledger_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->char('transaction_fingerprint', 64);
            $table->string('status', 24);
            $table->json('raw_data');
            $table->foreignId('pending_transaction_id')
                ->nullable()
                ->constrained('pending_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['ledger_id', 'transaction_fingerprint'],
                'statement_import_entries_unique_transaction',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_import_entries');
        Schema::dropIfExists('statement_imports');
        Schema::dropIfExists('bank_account_mappings');

        Schema::table('ledger_user', function (Blueprint $table): void {
            $table->dropColumn('ai_import_auto_create_accounts');
        });

        Schema::dropIfExists('ai_provider_settings');
    }
};
