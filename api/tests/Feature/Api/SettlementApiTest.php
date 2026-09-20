<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Http\Controllers\Api\SettlementController;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('settlements')]
#[CoversClass(SettlementController::class)]
class SettlementApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanPreviewSettlementForLedger(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview?date=2026-03-31")
            ->assertOk()
            ->assertJsonPath('data.ledger_id', $ledger->id)
            ->assertJsonPath('data.period_start', '2026-03-01')
            ->assertJsonPath('data.period_end', '2026-03-31')
            ->assertJsonStructure([
                'data' => [
                    'period_start',
                    'period_end',
                    'settlement_mode',
                    'summary',
                    'user_breakdowns',
                    'required_transfers',
                    'safety_gate' => ['auto_allowed', 'reason'],
                ],
            ]);
    }

    public function testMemberCanFetchSettlementPeriods(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements/periods")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['period_start', 'period_end', 'label', 'status', 'executed_at'],
                ],
            ]);
    }

    public function testPreviewUsesPreviousCalendarMonthForCutoffDayOne(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 1,
            'settlement_cutoff_time' => '00:00:00',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview?date=2026-04-01")
            ->assertOk()
            ->assertJsonPath('data.period_start', '2026-04-01')
            ->assertJsonPath('data.period_end', '2026-04-30');
    }

    public function testNonMemberCannotPreviewSettlementForForeignLedger(): void
    {
        [$ledger] = $this->createLedgerWithAdmin();
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview?date=2026-03-31")
            ->assertForbidden();
    }

    public function testMemberCanListRecentSettlements(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();

        $settlement = Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
        ]);
        $payerAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $user->id)->value('main_personal_account_id'),
        );
        $destinationAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => AccountType::PoolAsset->value,
            'owner_id' => null,
        ]);
        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'settlement_id' => $settlement->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 1200,
            'type' => TransactionType::Settlement->value,
            'split_rule' => TransactionSplitRule::Individual->value,
            'participants' => [],
            'description' => 'Settlement transfer for 2026-02-28',
            'date' => '2026-02-28',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ledger_id', $ledger->id)
            ->assertJsonPath('data.0.confirmation_required', true)
            ->assertJsonPath('data.0.transactions.0.settlement_id', $settlement->id)
            ->assertJsonPath('data.0.transactions.0.payer_account_name', $payerAccount->name)
            ->assertJsonPath('data.0.transactions.0.destination_account_name', $destinationAccount->name);
    }

    public function testAdminCanConfirmCycleWhenSafetyGateBlocksAutoExecution(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin([
            'settlement_auto_execute_enabled' => false,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $member = User::factory()->create(['name' => 'Member B']);
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        $adminAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $admin->id)->value('main_personal_account_id'),
        );
        Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset->value,
        ]);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
        ]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $admin->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $member->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);

        app(PostManualTransactionAction::class)->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $adminAccount->id,
            'destination_account_id' => $externalAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'Shared groceries',
            'date' => '2026-03-15',
        ]));

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/confirm", [
            'period_end' => '2026-03-31',
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'period_start', 'period_end', 'executed_at'],
            ]);

        $this->assertDatabaseCount('settlements', 1);
        $this->assertDatabaseHas('settlements', [
            'ledger_id' => $ledger->id,
            'period_end' => '2026-03-31',
        ]);
        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
            'date' => '2026-03-31',
        ]);

        $transaction = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', TransactionType::Settlement->value)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction?->settlement_id);
    }

    public function testMemberCannotConfirmSettlementCycle(): void
    {
        // Arrange
        [$ledger] = $this->createLedgerWithAdmin([
            'settlement_auto_execute_enabled' => false,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $member = User::factory()->create(['name' => 'Member B']);
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        Sanctum::actingAs($member, ['*']);

        // Act & Assert
        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/confirm", [
            'period_end' => '2026-03-31',
        ])->assertForbidden();
    }

    public function testAdminCanRecordAnIdempotentMidCycleTransfer(): void
    {
        [$ledger, $admin, , $fromAccount, $toAccount] = $this->createLedgerWithPendingTransfer(payer: 'member');
        Sanctum::actingAs($admin, ['*']);
        $payload = [
            'period_end' => '2026-03-31',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 5_000,
            'idempotency_key' => '33f2f725-2380-4aad-96dc-0b88299aaf4a',
        ];

        $firstId = $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', TransactionType::Settlement->value)
            ->assertJsonPath('data.source', TransactionSource::System->value)
            ->assertJsonPath('data.source_metadata.operation', 'mid_cycle_settlement_transfer')
            ->assertJsonPath('data.destination_account_id', $toAccount->id)
            ->json('data.id');

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $firstId);

        $this->assertSame(
            1,
            Transaction::query()
                ->where('ledger_id', $ledger->id)
                ->where('settlement_transfer_key', $payload['idempotency_key'])
                ->count(),
        );
    }

    public function testMemberCannotRecordMidCycleTransfer(): void
    {
        [$ledger, , $member, $fromAccount, $toAccount] = $this->createLedgerWithPendingTransfer(payer: 'admin');
        Sanctum::actingAs($member, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", [
            'period_end' => '2026-03-31',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 5_000,
            'idempotency_key' => '256f0249-d575-412c-9d97-b5eecd36c28d',
        ])->assertForbidden();
    }

    public function testAdminCannotRecordTransferFromAnotherMembersAccount(): void
    {
        [$ledger, $admin, $member, $fromAccount, $toAccount] = $this->createLedgerWithPendingTransfer(payer: 'admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", [
            'period_end' => '2026-03-31',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 5_000,
            'idempotency_key' => '9a1c2d3e-4f56-7890-abcd-ef1234567890',
        ])->assertForbidden()
            ->assertJsonPath('message', 'You cannot record a mid-cycle transfer from another member\'s account.');

        $this->assertSame($member->id, $fromAccount->owner_id);
        $this->assertDatabaseMissing('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
        ]);
    }

    public function testPreviewMarksOnlyOwnSourceTransfersAsRecordable(): void
    {
        [$ledger, $admin, $member, $fromAccount] = $this->createLedgerWithPendingTransfer(payer: 'admin');

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview?date=2026-03-31")
            ->assertOk()
            ->assertJsonPath('data.required_transfers.0.from_account_id', $fromAccount->id)
            ->assertJsonPath('data.required_transfers.0.from_account_owner_id', $member->id)
            ->assertJsonPath('data.required_transfers.0.can_record', false);

        Sanctum::actingAs($member, ['*']);
        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview?date=2026-03-31")
            ->assertOk()
            ->assertJsonPath('data.required_transfers.0.from_account_id', $fromAccount->id)
            ->assertJsonPath('data.required_transfers.0.can_record', false);

        [$ownLedger, $ownAdmin, , $ownFromAccount] = $this->createLedgerWithPendingTransfer(payer: 'member');
        Sanctum::actingAs($ownAdmin, ['*']);
        $this->getJson("/api/ledgers/{$ownLedger->id}/settlements/preview?date=2026-03-31")
            ->assertOk()
            ->assertJsonPath('data.required_transfers.0.from_account_id', $ownFromAccount->id)
            ->assertJsonPath('data.required_transfers.0.from_account_owner_id', $ownAdmin->id)
            ->assertJsonPath('data.required_transfers.0.can_record', true);
    }

    public function testMidCycleTransferRejectsForeignAccountsAndMalformedKeys(): void
    {
        [$ledger, $admin, , , $toAccount] = $this->createLedgerWithPendingTransfer();
        $foreignAccount = Account::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", [
            'period_end' => '2026-03-31',
            'from_account_id' => $foreignAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 1_000,
            'idempotency_key' => 'not-a-uuid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", [
            'period_end' => '2026-03-31',
            'from_account_id' => $foreignAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 1_000,
            'idempotency_key' => 'b28ea143-ed09-4d8f-b3d3-8eec4c01107e',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['from_account_id']);
    }

    public function testAdminCannotRecordTransferForSettledCycle(): void
    {
        [$ledger, $admin, , $fromAccount, $toAccount] = $this->createLedgerWithPendingTransfer();
        Sanctum::actingAs($admin, ['*']);

        Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'executed_at' => '2026-04-01 00:00:00',
        ]);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/transfers", [
            'period_end' => '2026-03-31',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 5_000,
            'idempotency_key' => '7d4c1e2a-9b83-4f51-8c0d-2a6e5f9b1c70',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['period_end']);
    }

    public function testConfirmReturnsValidationErrorWhenCycleDoesNotRequireManualConfirmation(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin([
            'settlement_auto_execute_enabled' => true,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/2026-03-31/confirm")
            ->assertStatus(422);
    }

    public function testMemberCanUpdateCycleConfig(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 1,
            'settlement_cutoff_time' => '00:00:00',
            'settlement_auto_execute_enabled' => false,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/ledgers/{$ledger->id}/cycle-config", [
            'settlement_timezone' => 'Europe/London',
            'settlement_cutoff_day' => 15,
            'settlement_cutoff_time' => '23:59:00',
            'settlement_auto_execute_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.settlement_timezone', 'Europe/London')
            ->assertJsonPath('data.settlement_cutoff_day', 15)
            ->assertJsonPath('data.settlement_cutoff_time', '23:59:00')
            ->assertJsonPath('data.settlement_auto_execute_enabled', true);

        $ledger->refresh();
        $this->assertSame('Europe/London', $ledger->settlement_timezone);
        $this->assertSame(15, $ledger->settlement_cutoff_day);
        $this->assertTrue($ledger->settlement_auto_execute_enabled);
    }

    public function testPreviewAllowsMissingDate(): void
    {
        [$ledger, $user] = $this->createLedgerWithAdmin();
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/settlements/preview")
            ->assertStatus(200);
    }

    public function testCycleConfigRejectsInvalidTimezone(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin();
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/ledgers/{$ledger->id}/cycle-config", [
            'settlement_timezone' => 'Mars/Olympus',
            'settlement_cutoff_day' => 15,
            'settlement_cutoff_time' => '23:59:00',
            'settlement_auto_execute_enabled' => true,
        ])->assertStatus(422);
    }

    public function testConfirmRejectsInvalidCycleParam(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin([
            'settlement_auto_execute_enabled' => false,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/settlements/not-a-date/confirm")
            ->assertStatus(422);
    }

    public function testMemberCannotUpdateCycleConfig(): void
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin();
        $member = User::factory()->create();
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');
        Sanctum::actingAs($member, ['*']);

        $this->patchJson("/api/ledgers/{$ledger->id}/cycle-config", [
            'settlement_timezone' => 'Europe/London',
            'settlement_cutoff_day' => 15,
            'settlement_cutoff_time' => '23:59:00',
            'settlement_auto_execute_enabled' => true,
        ])->assertForbidden();
    }

    /*
     * Seeders.
     */

    /**
     * @param array<string, mixed> $ledgerOverrides
     * @return array{Ledger, User}
     */
    private function createLedgerWithAdmin(array $ledgerOverrides = []): array
    {
        $admin = User::factory()->create();
        $ledger = Ledger::factory()->create(array_merge([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
            'settlement_auto_execute_enabled' => false,
        ], $ledgerOverrides));
        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');

        return [$ledger, $admin];
    }

    /**
     * @return array{Ledger, User, User, Account, Account}
     */
    private function createLedgerWithPendingTransfer(string $payer = 'admin'): array
    {
        [$ledger, $admin] = $this->createLedgerWithAdmin([
            'settlement_mode' => 'direct_p2p',
        ]);
        $member = User::factory()->create(['name' => 'Member B']);
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        $adminAccount = Account::query()->findOrFail(
            DB::table('ledger_user')
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $admin->id)
                ->value('main_personal_account_id'),
        );
        $memberAccount = Account::query()->findOrFail(
            DB::table('ledger_user')
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $member->id)
                ->value('main_personal_account_id'),
        );
        $adminLiabilityAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('owner_id', $admin->id)
            ->where('type', AccountType::UserLiability->value)
            ->firstOrFail();
        $memberLiabilityAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('owner_id', $member->id)
            ->where('type', AccountType::UserLiability->value)
            ->firstOrFail();
        $expenseAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
        ]);

        foreach ([$admin, $member] as $user) {
            FinancialProfile::factory()->create([
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'valid_from' => '2026-01-01',
                'incomes' => [['description' => 'Income', 'amount' => 100_000]],
                'deductions' => [],
            ]);
        }

        $payerAccount = $payer === 'member' ? $memberAccount : $adminAccount;
        $fromAccount = $payer === 'member' ? $adminLiabilityAccount : $memberLiabilityAccount;
        $toAccount = $payer === 'member' ? $memberAccount : $adminAccount;

        app(PostManualTransactionAction::class)->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $expenseAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Shared groceries',
            'date' => '2026-03-15',
        ]));

        return [$ledger, $admin, $member, $fromAccount, $toAccount];
    }
}
