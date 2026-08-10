<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\CurrenciesController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('currencies')]
#[CoversClass(CurrenciesController::class)]
class CurrenciesApiTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthenticatedUserCanFetchCurrencies(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonPath('default', 'EUR')
            ->assertJsonStructure([
                'default',
                'available' => [
                    '*' => ['code', 'symbol', 'name'],
                ],
            ]);
    }

    public function testUnauthenticatedUserCannotFetchCurrencies(): void
    {
        $this->getJson('/api/currencies')
            ->assertUnauthorized();
    }
}
