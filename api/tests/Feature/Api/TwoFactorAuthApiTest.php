<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
#[Group('two-factor')]
#[CoversClass(AuthController::class)]
class TwoFactorAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testLoginWithTwoFactorEnabledReturnsIssueTwoFactorToken(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();

        // Act
        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonStructure(['token', 'user' => ['id']]);

        $token = $response->json('token');
        $this->assertIsString($token);

        $accessToken = $this->findTokenFromPlainText($token);
        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->can('issue-2fa'));
        $this->assertFalse($accessToken->can('*'));
    }

    public function testIssueTwoFactorTokenCannotListLedgers(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        // Act & Assert
        $this->withToken($token)
            ->getJson(route('ledgers.index'))
            ->assertForbidden();
    }

    public function testIssueTwoFactorTokenCannotFetchMe(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        // Act & Assert
        $this->withToken($token)
            ->getJson(route('auth.me'))
            ->assertForbidden();
    }

    public function testIssueTwoFactorTokenCannotCreateLedger(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        // Act & Assert
        $this->withToken($token)
            ->postJson(route('ledgers.store'), [
                'name' => 'Blocked Ledger',
                'currency' => 'EUR',
            ])
            ->assertForbidden();
    }

    public function testIssueTwoFactorTokenCanReachChallengeAndInvalidCodeReturnsUnprocessable(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        $this->mock(TwoFactorAuthenticationProvider::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        // Act & Assert
        $this->withToken($token)
            ->postJson(route('auth.two-factor-challenge'), [
                'code' => '000000',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The provided two factor authentication code was invalid.');
    }

    public function testFullAbilityTokenCanReachChallengeBecauseStarSatisfiesIssueTwoFactorAbility(): void
    {
        // Arrange
        // Sanctum's PersonalAccessToken::can() returns true for any ability when the
        // token has '*', so CheckForAnyAbility('issue-2fa') also admits full API tokens.
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        $this->mock(TwoFactorAuthenticationProvider::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        // Act & Assert
        $this->withToken($token)
            ->postJson(route('auth.two-factor-challenge'), [
                'code' => '000000',
            ])
            ->assertUnprocessable();
    }

    public function testSuccessfulChallengeDeletesTempTokenAndReturnsApiTokenThatCanAccessLedgers(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $tempToken = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;
        $tempTokenId = (int) explode('|', $tempToken, 2)[0];

        $this->mock(TwoFactorAuthenticationProvider::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        // Act
        $response = $this->withToken($tempToken)
            ->postJson(route('auth.two-factor-challenge'), [
                'code' => '123456',
            ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id']]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tempTokenId,
        ]);

        $apiToken = $response->json('token');
        $this->assertIsString($apiToken);

        $accessToken = $this->findTokenFromPlainText($apiToken);
        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->can('*'));

        app('auth')->forgetGuards();

        $this->withToken($apiToken)
            ->getJson(route('ledgers.index'))
            ->assertOk();
    }

    public function testValidRecoveryCodeWorksOnceAndReuseFails(): void
    {
        // Arrange
        $recoveryCode = 'recovery-code-one';
        $user = $this->seedUserWithTwoFactor(recoveryCodes: [$recoveryCode, 'recovery-code-two']);
        $firstTempToken = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        // Act
        $firstResponse = $this->withToken($firstTempToken)
            ->postJson(route('auth.two-factor-challenge'), [
                'recovery_code' => $recoveryCode,
            ]);

        // Assert
        $firstResponse
            ->assertOk()
            ->assertJsonStructure(['token']);

        $remaining = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
        $this->assertSame(['recovery-code-two'], $remaining);

        app('auth')->forgetGuards();

        $secondTempToken = $user->createToken('2fa-token-2', ['issue-2fa'])->plainTextToken;

        $this->withToken($secondTempToken)
            ->postJson(route('auth.two-factor-challenge'), [
                'recovery_code' => $recoveryCode,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The provided two factor authentication code was invalid.');
    }

    public function testLoginWithoutTwoFactorReturnsFullApiTokenThatCanAccessProtectedRoutes(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => null,
        ]);

        // Act
        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('two_factor', false)
            ->assertJsonStructure(['token']);

        $token = $response->json('token');
        $accessToken = $this->findTokenFromPlainText($token);
        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->can('*'));

        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson(route('ledgers.index'))
            ->assertOk();
    }

    public function testUnauthenticatedChallengeReturnsUnauthorized(): void
    {
        // Act & Assert
        $this->postJson(route('auth.two-factor-challenge'), [
            'code' => '123456',
        ])->assertUnauthorized();
    }

    public function testChallengeWithEmptyBodyReturnsValidationErrors(): void
    {
        // Arrange
        $user = $this->seedUserWithTwoFactor();
        $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

        // Act & Assert
        $this->withToken($token)
            ->postJson(route('auth.two-factor-challenge'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'recovery_code']);
    }

    public function testEnforceTwoFactorBlocksProtectedRoutesButAllowsLogoutAndEnrollmentPaths(): void
    {
        // Arrange
        Config::set('auth.enforce_2fa', true);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => null,
        ]);
        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        // Act & Assert — protected route blocked
        $this->withToken($token)
            ->getJson(route('ledgers.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Two-factor authentication must be enabled.');

        // Logout remains allowed with full token
        $this->withToken($token)
            ->postJson(route('auth.logout'))
            ->assertOk();

        // Fresh token for enrollment path check (logout deleted the previous one)
        $enrollmentToken = $user->createToken('api-token-2', ['*'])->plainTextToken;

        $enableResponse = $this->withToken($enrollmentToken)
            ->postJson('/api/auth/user/two-factor-authentication');

        // Must not be blocked by EnforceTwoFactor (Fortify may still 401/404/422/302/403 for its own reasons)
        $this->assertNotSame(
            'Two-factor authentication must be enabled.',
            $enableResponse->json('message')
        );
    }

    /*
     * Seeders.
     */

    /**
     * @param list<string> $recoveryCodes
     */
    private function seedUserWithTwoFactor(array $recoveryCodes = ['aaaa-bbbb', 'cccc-dddd']): User
    {
        return User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /*
     * Helpers.
     */

    private function findTokenFromPlainText(string $plainTextToken): ?PersonalAccessToken
    {
        $tokenId = (int) explode('|', $plainTextToken, 2)[0];

        return PersonalAccessToken::query()->find($tokenId);
    }
}
