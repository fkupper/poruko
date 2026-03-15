<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
#[CoversClass(AuthController::class)]
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testUserCanRegisterAndReceiveToken(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function testUserCanLoginAndReceiveToken(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'invalid-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function testLoginFailsWithNonExistentEmail(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function testRegisterFailsWithDuplicateEmail(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $expectedErrors
     */
    #[DataProvider('registerValidationDataProvider')]
    public function testRegisterFailsValidation(array $payload, array $expectedErrors): void
    {
        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function registerValidationDataProvider(): array
    {
        return [
            'invalid email and short password' => [
                'payload' => [
                    'name' => 'Test User',
                    'email' => 'invalid-email',
                    'password' => 'short',
                ],
                'expectedErrors' => ['email', 'password'],
            ],
            'missing name' => [
                'payload' => [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                ],
                'expectedErrors' => ['name'],
            ],
            'missing email' => [
                'payload' => [
                    'name' => 'Test User',
                    'password' => 'password123',
                ],
                'expectedErrors' => ['email'],
            ],
            'missing password' => [
                'payload' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
                'expectedErrors' => ['password'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $expectedErrors
     */
    #[DataProvider('loginValidationDataProvider')]
    public function testLoginFailsValidation(array $payload, array $expectedErrors): void
    {
        $this->postJson('/api/auth/login', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function loginValidationDataProvider(): array
    {
        return [
            'missing email and password' => [
                'payload' => [],
                'expectedErrors' => ['email', 'password'],
            ],
            'invalid email format' => [
                'payload' => [
                    'email' => 'not-an-email',
                    'password' => 'password123',
                ],
                'expectedErrors' => ['email'],
            ],
            'missing email' => [
                'payload' => [
                    'password' => 'password123',
                ],
                'expectedErrors' => ['email'],
            ],
            'missing password' => [
                'payload' => [
                    'email' => 'test@example.com',
                ],
                'expectedErrors' => ['password'],
            ],
        ];
    }

    public function testAuthenticatedUserCanFetchMe(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function testUnauthenticatedUserCannotFetchMe(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function testAuthenticatedUserCanLogoutAndRevokeCurrentToken(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        [$tokenId] = explode('|', $token, 2);

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) $tokenId,
        ]);

        app('auth')->forgetGuards();

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnauthorized();
    }

    public function testUnauthenticatedUserCannotLogout(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();
    }

    public function testLogoutRevokesOnlyCurrentToken(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('token-a')->plainTextToken;
        $tokenB = $user->createToken('token-b')->plainTextToken;

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$tokenA}",
        ])->assertOk();

        app('auth')->forgetGuards();

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$tokenA}",
        ])->assertUnauthorized();

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$tokenB}",
        ])->assertOk();
    }
}
