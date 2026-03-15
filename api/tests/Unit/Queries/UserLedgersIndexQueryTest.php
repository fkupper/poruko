<?php

namespace Tests\Unit\Queries;

use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Queries\UserLedgersIndexQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(UserLedgersIndexQuery::class)]
class UserLedgersIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testUserSeesOnlyTheirLedgers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userLedger = Ledger::factory()->create(['name' => 'My Ledger']);
        $userLedger->users()->attach($user->id, ['role' => 'admin']);

        $otherLedger = Ledger::factory()->create(['name' => 'Other Ledger']);
        $otherLedger->users()->attach($otherUser->id, ['role' => 'admin']);

        $query = app(UserLedgersIndexQuery::class);

        // Act
        $result = $query->execute($user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($userLedger->id, $result->first()?->id);
    }

    public function testUsersCountIsCorrect(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $ledger->users()->attach(User::factory()->create()->id, ['role' => 'member']);

        $query = app(UserLedgersIndexQuery::class);

        // Act
        $result = $query->execute($user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame(2, $result->first()?->users_count);
    }
}
