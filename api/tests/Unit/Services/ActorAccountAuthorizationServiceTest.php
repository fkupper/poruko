<?php

namespace Tests\Unit\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(ActorAccountAuthorizationService::class)]
class ActorAccountAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnFundingAndPoolAreAllowed(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $service = app(ActorAccountAuthorizationService::class);

        $own = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding,
        ]);
        $pool = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset,
        ]);

        $service->assertMayUsePayerAccount($user, $own);
        $service->assertMayUsePayerAccount($user, $pool);
        $service->assertMayMutateAccount($user, $own);
        $service->assertMayCreateAccount($user, CreateAccountData::fromArray([
            'name' => 'Mine',
            'type' => AccountType::UserFunding,
            'owner_id' => $user->id,
        ]));

        $this->assertTrue(true);
    }

    public function testPeerFundingIsDenied(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $peer = User::factory()->create();
        $service = app(ActorAccountAuthorizationService::class);
        $peerAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $peer->id,
            'type' => AccountType::UserFunding,
        ]);

        $this->expectException(McpAuthorizationException::class);
        $service->assertMayUsePayerAccount($user, $peerAccount);
    }
}
