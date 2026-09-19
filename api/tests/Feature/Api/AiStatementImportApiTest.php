<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Enums\TransactionSource;
use App\Http\Controllers\Api\AiStatementImportController;
use App\Jobs\ProcessBankStatementImportJob;
use App\Models\Account;
use App\Models\AiProviderSetting;
use App\Models\BankAccountMapping;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\PendingTransaction;
use App\Models\StatementImport;
use App\Models\StatementImportEntry;
use App\Models\User;
use App\Modules\Ledger\Actions\ProcessStatementImportAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ai-import')]
#[CoversClass(AiStatementImportController::class)]
#[CoversClass(ProcessStatementImportAction::class)]
class AiStatementImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanStoreEncryptedProviderSettingsWithoutSecretDisclosure(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();
        Sanctum::actingAs($user, ['*']);

        $apiKey = 'sk-test-super-secret-value';

        $this->putJson("/api/ledgers/{$ledger->id}/ai-import/settings", [
            'provider' => 'openai',
            'api_key' => $apiKey,
            'model' => 'gpt-test',
            'auto_create_accounts' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.model', 'gpt-test')
            ->assertJsonPath('data.masked_api_key', '••••••••alue')
            ->assertJsonPath('data.auto_create_accounts', true)
            ->assertJsonMissing(['api_key' => $apiKey]);

        $setting = AiProviderSetting::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($apiKey, $setting->api_key);
        $this->assertNotSame($apiKey, $setting->getRawOriginal('api_key'));
        $this->assertTrue(
            LedgerUser::query()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->ai_import_auto_create_accounts,
        );

        $this->getJson("/api/ledgers/{$ledger->id}/ai-import/settings")
            ->assertOk()
            ->assertJsonMissing(['api_key' => $apiKey]);
    }

    public function testStatementUploadIsPrivateAndQueued(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$ledger, $user] = $this->createLedgerWithAdmin();
        AiProviderSetting::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/ledgers/{$ledger->id}/ai-import/statements", [
            'statement' => UploadedFile::fake()->createWithContent(
                'checking.csv',
                "date,description,amount\n2026-09-01,Market,-20.00",
            ),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.filename', 'checking.csv');

        $import = StatementImport::query()->firstOrFail();
        Storage::disk('local')->assertExists($import->file_path);
        Queue::assertPushed(
            ProcessBankStatementImportJob::class,
            fn (ProcessBankStatementImportJob $job): bool => $job->statementImportId === $import->id,
        );
    }

    public function testMemberWithoutAiPermissionCannotConfigureOrUpload(): void
    {
        Storage::fake('local');
        $ledger = Ledger::factory()->create();
        $member = User::factory()->create();
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');
        Sanctum::actingAs($member, ['*']);

        $this->putJson("/api/ledgers/{$ledger->id}/ai-import/settings", [
            'provider' => 'openai',
            'api_key' => 'sk-test-secret-value',
            'auto_create_accounts' => false,
        ])->assertForbidden();

        $this->postJson("/api/ledgers/{$ledger->id}/ai-import/statements", [
            'statement' => UploadedFile::fake()->create('statement.csv', 1, 'text/csv'),
        ])->assertForbidden();
    }

    public function testParserCreatesPendingExpensesSuggestsMappingsAndDeduplicates(): void
    {
        Storage::fake('local');
        [$ledger, $user] = $this->createLedgerWithAdmin();
        $paymentAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding,
            'name' => 'Everyday Checking',
        ]);
        $expenseAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense)
            ->firstOrFail();

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->update(['default_expense_account_id' => $expenseAccount->id]);

        AiProviderSetting::factory()->create(['user_id' => $user->id]);
        $this->fakeParsedStatement($this->parsedStatementPayload());

        $firstImport = $this->createStoredImport($ledger, $user, 'first.csv');
        $this->app->make(ProcessStatementImportAction::class)->execute($firstImport);

        $mapping = BankAccountMapping::query()->firstOrFail();
        $pending = PendingTransaction::query()->firstOrFail();

        $this->assertNull($mapping->account_id);
        $this->assertSame($paymentAccount->id, $mapping->suggested_account_id);
        $this->assertNull($pending->payer_account_id);
        $this->assertSame($expenseAccount->id, $pending->destination_account_id);
        $this->assertSame(TransactionSource::AiImport, $pending->source);
        $this->assertSame('completed', $firstImport->fresh()->status);
        $this->assertSame(1, $firstImport->fresh()->pending_count);
        $this->assertSame(1, $firstImport->fresh()->failed_count);

        $secondImport = $this->createStoredImport($ledger, $user, 'second.csv');
        $this->app->make(ProcessStatementImportAction::class)->execute($secondImport);

        $this->assertSame(1, $secondImport->fresh()->duplicate_count);
        $this->assertSame(1, $secondImport->fresh()->failed_count);
        $this->assertDatabaseCount('pending_transactions', 1);
        $this->assertDatabaseCount('statement_import_entries', 1);
    }

    public function testAutoCreateMapsNewAccountsAndApprovalKeepsImportProvenance(): void
    {
        Storage::fake('local');
        [$ledger, $user] = $this->createLedgerWithAdmin();
        $expenseAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense)
            ->firstOrFail();
        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->update([
                'default_expense_account_id' => $expenseAccount->id,
                'ai_import_auto_create_accounts' => true,
            ]);
        AiProviderSetting::factory()->create(['user_id' => $user->id]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'bank_accounts' => [[
                                'external_id' => 'new-bank-account',
                                'name' => 'New Bank',
                                'last_four' => '9876',
                                'ownership' => 'personal',
                            ]],
                            'transactions' => [[
                                'external_id' => 'new-tx',
                                'bank_account_external_id' => 'new-bank-account',
                                'date' => '2026-09-11',
                                'description' => 'Coffee',
                                'raw_description' => 'COFFEE SHOP',
                                'amount_cents' => 475,
                                'transaction_type' => 'expense',
                                'confidence' => 0.91,
                                'rationale' => 'Debit card purchase',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $import = $this->createStoredImport($ledger, $user, 'auto.csv');
        $this->app->make(ProcessStatementImportAction::class)->execute($import);

        $mapping = BankAccountMapping::query()->firstOrFail();
        $pending = PendingTransaction::query()->firstOrFail();
        $this->assertNotNull($mapping->account_id);
        $this->assertSame($mapping->account_id, $pending->payer_account_id);

        Sanctum::actingAs($user, ['*']);
        $this->postJson(
            "/api/ledgers/{$ledger->id}/pending-transactions/{$pending->id}/approve",
        )
            ->assertOk()
            ->assertJsonPath('data.source', 'ai_import')
            ->assertJsonPath('data.source_metadata.statement_import_id', $import->public_id)
            ->assertJsonPath(
                'data.source_metadata.statement_import_entry_id',
                StatementImportEntry::query()->firstOrFail()->id,
            )
            ->assertJsonPath('data.source_metadata.external_transaction_id', 'new-tx');
    }

    public function testMappingRejectsForeignOrWrongOwnershipAccountsAndUpdatesPendingProposal(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();
        $otherUser = User::factory()->create();
        $ledger->users()->attach($otherUser->id, ['role' => 'member']);
        $mapping = BankAccountMapping::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'ownership_type' => 'personal',
        ]);
        $otherAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $otherUser->id,
            'type' => AccountType::UserFunding,
        ]);
        $ownAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding,
        ]);
        $pending = PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'source' => TransactionSource::AiImport,
            'payer_account_id' => null,
            'raw_data' => [
                'bank_account_mapping_id' => $mapping->id,
                'description' => 'Imported expense',
            ],
        ]);
        Sanctum::actingAs($user, ['*']);

        $endpoint = "/api/ledgers/{$ledger->id}/ai-import/mappings/{$mapping->id}";

        $this->patchJson($endpoint, ['account_id' => $otherAccount->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id']);

        $this->patchJson($endpoint, ['account_id' => $ownAccount->id])
            ->assertOk()
            ->assertJsonPath('data.account_id', $ownAccount->id);

        $this->assertSame($ownAccount->id, $pending->fresh()->payer_account_id);
    }

    public function testUploadRequiresConfiguredProvider(): void
    {
        Storage::fake('local');
        [$ledger, $user] = $this->createLedgerWithAdmin();
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/ai-import/statements", [
            'statement' => UploadedFile::fake()->create('statement.csv', 1, 'text/csv'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Configure your AI provider before uploading a statement.');
    }

    public function testMissingDefaultExpenseFallsBackToSpaceExpense(): void
    {
        Storage::fake('local');
        [$ledger, $user] = $this->createLedgerWithAdmin();
        $expenseAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense)
            ->firstOrFail();
        AiProviderSetting::factory()->create(['user_id' => $user->id]);
        $this->fakeParsedStatement($this->parsedStatementPayload());

        $import = $this->createStoredImport($ledger, $user, 'fallback.csv');
        $this->app->make(ProcessStatementImportAction::class)->execute($import);

        $this->assertSame(
            $expenseAccount->id,
            PendingTransaction::query()->firstOrFail()->destination_account_id,
        );
    }

    public function testJointBankAccountAutoCreatesSharedPoolAccount(): void
    {
        Storage::fake('local');
        [$ledger, $user] = $this->createLedgerWithAdmin();
        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->update(['ai_import_auto_create_accounts' => true]);
        AiProviderSetting::factory()->create(['user_id' => $user->id]);
        $this->fakeParsedStatement([
            'bank_accounts' => [[
                'external_id' => 'joint-1',
                'name' => 'Household Checking',
                'last_four' => '4444',
                'ownership' => 'joint',
            ]],
            'transactions' => [[
                'external_id' => 'joint-tx',
                'bank_account_external_id' => 'joint-1',
                'date' => '2026-09-11',
                'description' => 'Groceries',
                'raw_description' => 'MARKET',
                'amount_cents' => 1200,
                'transaction_type' => 'expense',
                'confidence' => 0.9,
                'rationale' => 'Shared card debit',
            ]],
        ]);

        $import = $this->createStoredImport($ledger, $user, 'joint.csv');
        $this->app->make(ProcessStatementImportAction::class)->execute($import);

        $mapping = BankAccountMapping::query()->firstOrFail();
        $account = Account::query()->findOrFail($mapping->account_id);

        $this->assertSame('joint', $mapping->ownership_type);
        $this->assertSame(AccountType::PoolAsset, $account->type);
        $this->assertNull($account->owner_id);
        $this->assertSame($account->id, PendingTransaction::query()->firstOrFail()->payer_account_id);
    }

    public function testCrossLedgerMappingUpdateIsNotFound(): void
    {
        [$ledgerA, $user] = $this->createLedgerWithAdmin();
        [$ledgerB] = $this->createLedgerWithAdmin();
        $mapping = BankAccountMapping::factory()->create([
            'ledger_id' => $ledgerB->id,
            'user_id' => $user->id,
            'ownership_type' => 'personal',
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson("/api/ledgers/{$ledgerA->id}/ai-import/mappings/{$mapping->id}", [
            'account_id' => null,
        ])->assertNotFound();
    }

    /**
     * @return array{Ledger, User}
     */
    private function createLedgerWithAdmin(): array
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        return [$ledger, $user];
    }

    private function createStoredImport(Ledger $ledger, User $user, string $filename): StatementImport
    {
        $path = "statement-imports/{$user->id}/{$filename}";
        Storage::disk('local')->put($path, 'statement contents');

        return StatementImport::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'file_path' => $path,
            'original_filename' => $filename,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fakeParsedStatement(array $payload): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedStatementPayload(): array
    {
        return [
            'bank_accounts' => [[
                'external_id' => 'checking-1234',
                'name' => 'Everyday Checking',
                'last_four' => '1234',
                'ownership' => 'personal',
            ]],
            'transactions' => [
                [
                    'external_id' => 'expense-1',
                    'bank_account_external_id' => 'checking-1234',
                    'date' => '2026-09-10',
                    'description' => 'Groceries',
                    'raw_description' => 'MARKET 123',
                    'amount_cents' => 2599,
                    'transaction_type' => 'expense',
                    'confidence' => 0.97,
                    'rationale' => 'Card debit',
                ],
                [
                    'external_id' => 'income-1',
                    'bank_account_external_id' => 'checking-1234',
                    'date' => '2026-09-12',
                    'description' => 'Salary',
                    'raw_description' => 'EMPLOYER PAYROLL',
                    'amount_cents' => 200000,
                    'transaction_type' => 'income',
                    'confidence' => 0.99,
                    'rationale' => 'Incoming credit',
                ],
            ],
        ];
    }
}
