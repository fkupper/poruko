<?php

namespace Tests\Feature\Mcp;

use App\Enums\McpTokenAbility;
use App\Http\Middleware\AuthenticateMcp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(AuthenticateMcp::class)]
class McpHttpAuthTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'phpunit',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    public function testMcpKeyCanInitializeTheServer(): void
    {
        [, $user] = $this->createMcpLedger();
        $plain = $user->createToken('Cursor', [McpTokenAbility::Mcp->value])->plainTextToken;

        $this->withToken($plain)
            ->postJson('/mcp/poruko', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Poruko Ledger');
    }

    public function testFullSessionTokenCanStillInitialize(): void
    {
        [, $user] = $this->createMcpLedger();
        $plain = $user->createToken('api-token', ['*'])->plainTextToken;

        $this->withToken($plain)
            ->postJson('/mcp/poruko', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Poruko Ledger');
    }

    public function testTwoFactorTokenCannotInitialize(): void
    {
        [, $user] = $this->createMcpLedger();
        $plain = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        $this->withToken($plain)
            ->postJson('/mcp/poruko', $this->initializePayload())
            ->assertForbidden();
    }

    public function testMissingTokenIsUnauthorized(): void
    {
        $this->postJson('/mcp/poruko', $this->initializePayload())
            ->assertUnauthorized();
    }

    public function testSignedUrlAuthenticatesWithoutBearer(): void
    {
        [, $user] = $this->createMcpLedger();
        $token = $user->createToken('Inspector', [McpTokenAbility::Mcp->value])->accessToken;
        $signedUrl = app(\App\Modules\Mcp\Services\McpTokenService::class)->signedUrl($token)['url'];

        $this->postJson($signedUrl, $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Poruko Ledger');
    }

    public function testSignedUrlStopsWorkingAfterRevoke(): void
    {
        [, $user] = $this->createMcpLedger();
        $newToken = $user->createToken('Inspector', [McpTokenAbility::Mcp->value]);
        $signedUrl = app(\App\Modules\Mcp\Services\McpTokenService::class)->signedUrl($newToken->accessToken)['url'];
        $newToken->accessToken->delete();

        $this->postJson($signedUrl, $this->initializePayload())
            ->assertUnauthorized();
    }

    public function testUnsignedTokenIdQueryIsRejected(): void
    {
        [, $user] = $this->createMcpLedger();
        $token = $user->createToken('Inspector', [McpTokenAbility::Mcp->value])->accessToken;

        $this->postJson('/mcp/poruko?mcp_token=' . $token->id, $this->initializePayload())
            ->assertUnauthorized();
    }
}
