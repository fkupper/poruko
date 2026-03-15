<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\HealthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('health')]
#[CoversClass(HealthController::class)]
class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function testHealthEndpointReturnsOk(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}
