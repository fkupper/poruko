<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger-users')]
class LedgerMemberMainPersonalAccountTest extends TestCase
{
    use RefreshDatabase;

    public function testAttachCreatesMainPersonalAccountAndSetsPivot(): void
    {
        $user = User::factory()->create(['name' => 'Alex']);
        $ledger = Ledger::factory()->create();

        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $mainId = DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->value('main_personal_account_id');

        $this->assertNotNull($mainId);

        $account = Account::query()->findOrFail($mainId);

        $this->assertSame(AccountType::UserFunding, $account->type);
        $this->assertSame($user->id, $account->owner_id);
        $this->assertSame("Alex's Funding Account", $account->name);
        $this->assertSame($ledger->id, $account->ledger_id);
    }
}
