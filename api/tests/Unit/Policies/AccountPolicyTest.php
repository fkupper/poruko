<?php

namespace Tests\Unit\Policies;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Policies\AccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountPolicy::class)]
class AccountPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function testUseAsSourceAllowsOwnAndSharedAccountsOnly(): void
    {
        $ledger = Ledger::factory()->create();
        $bob = User::factory()->create(['name' => 'Bob']);
        $clara = User::factory()->create(['name' => 'Clara']);
        $ledger->users()->attach($bob->id, ['role' => 'member']);
        $ledger->users()->attach($clara->id, ['role' => 'member']);

        $bobAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $bob->id,
            'type' => AccountType::UserLiability->value,
        ]);
        $claraAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $clara->id,
            'type' => AccountType::UserLiability->value,
        ]);
        $poolAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset->value,
        ]);

        $policy = new AccountPolicy();

        $this->assertTrue($policy->useAsSource($bob, $bobAccount));
        $this->assertTrue($policy->useAsSource($bob, $poolAccount));
        $this->assertFalse($policy->useAsSource($bob, $claraAccount));
        $this->assertTrue($policy->useAsSource($clara, $claraAccount));
        $this->assertFalse($policy->useAsSource($clara, $bobAccount));
    }
}
