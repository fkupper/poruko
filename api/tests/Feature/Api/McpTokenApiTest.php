<?php

namespace Tests\Feature\Api;

use App\Enums\McpTokenAbility;
use App\Http\Controllers\Api\McpTokenController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(McpTokenController::class)]
class McpTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanCreateListAndRevokeMcpKeys(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $create = $this->postJson('/api/mcp-tokens', ['name' => 'Cursor'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cursor')
            ->assertJsonPath('meta.authorization_header', 'Authorization');

        $plain = $create->json('meta.token');
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);
        $this->assertStringContainsString('/mcp/poruko', (string) $create->json('meta.mcp_url'));
        $this->assertStringStartsWith('Bearer ' . $plain, (string) $create->json('meta.client_config.headers.Authorization'));
        $this->assertSame(
            'Bearer ' . $plain,
            $create->json('meta.client_config.cursor.mcpServers.poruko.headers.Authorization'),
        );
        $this->assertSame(
            'http',
            $create->json('meta.client_config.claude.mcpServers.poruko.type'),
        );
        $this->assertNotEmpty($create->json('meta.signed_url.url'));

        $tokenId = $create->json('data.id');

        $this->getJson('/api/mcp-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tokenId)
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('meta.token');

        $this->deleteJson("/api/mcp-tokens/{$tokenId}")
            ->assertOk()
            ->assertJsonPath('message', 'MCP key revoked.');

        $this->getJson('/api/mcp-tokens')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testSessionTokensAreNotListedAsMcpKeys(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token', ['*']);
        $user->createToken('Cursor', [McpTokenAbility::Mcp->value]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/mcp-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cursor');
    }

    public function testUserCannotRevokeAnotherUsersMcpKey(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $token = $owner->createToken('Cursor', [McpTokenAbility::Mcp->value])->accessToken;
        Sanctum::actingAs($stranger, ['*']);

        $this->deleteJson("/api/mcp-tokens/{$token->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }

    public function testNameIsRequired(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->postJson('/api/mcp-tokens', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function testSignedUrlRequiresOwnedMcpKey(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Inspector', [McpTokenAbility::Mcp->value])->accessToken;
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/mcp-tokens/{$token->id}/signed-url", [
            'expires_in_hours' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.expires_in_hours', 2)
            ->assertJsonStructure(['data' => ['url', 'expires_at', 'expires_in_hours']]);

        $this->assertStringContainsString('mcp_token=' . $token->id, (string) $this->postJson(
            "/api/mcp-tokens/{$token->id}/signed-url",
        )->json('data.url'));
    }

    public function testMcpKeyCannotCallRestApi(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('Cursor', [McpTokenAbility::Mcp->value])->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/ledgers')
            ->assertForbidden();
    }
}
