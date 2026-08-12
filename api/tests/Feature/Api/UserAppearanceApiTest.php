<?php

namespace Tests\Feature\Api;

use App\Enums\UiColorMode;
use App\Enums\UiTheme;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
#[CoversClass(AuthController::class)]
class UserAppearanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testMeIncludesDefaultAppearancePreferences(): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->getJson(route('auth.me'));

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('user.theme', UiTheme::Poruko->value)
            ->assertJsonPath('user.color_mode', UiColorMode::System->value);
    }

    public function testCanUpdateAppearancePreferences(): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->putJson(route('auth.appearance.update'), [
            'theme' => UiTheme::Quiet->value,
            'color_mode' => UiColorMode::Dark->value,
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('user.theme', UiTheme::Quiet->value)
            ->assertJsonPath('user.color_mode', UiColorMode::Dark->value);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => UiTheme::Quiet->value,
            'color_mode' => UiColorMode::Dark->value,
        ]);

        $this->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonPath('user.theme', UiTheme::Quiet->value)
            ->assertJsonPath('user.color_mode', UiColorMode::Dark->value);
    }

    public function testCanSelectNeonTokyoTheme(): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->putJson(route('auth.appearance.update'), [
            'theme' => UiTheme::NeonTokyo->value,
            'color_mode' => UiColorMode::Light->value,
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('user.theme', UiTheme::NeonTokyo->value)
            ->assertJsonPath('user.color_mode', UiColorMode::Light->value);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => UiTheme::NeonTokyo->value,
            'color_mode' => UiColorMode::Light->value,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $errors
     */
    #[DataProvider('invalidAppearancePayloadDataProvider')]
    public function testUpdateAppearanceValidatesPayload(array $payload, array $errors): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->putJson(route('auth.appearance.update'), $payload);

        // Assert
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidAppearancePayloadDataProvider(): array
    {
        return [
            'missing both' => [
                [],
                ['theme', 'color_mode'],
            ],
            'invalid theme' => [
                [
                    'theme' => 'neon-spreadsheet',
                    'color_mode' => UiColorMode::Light->value,
                ],
                ['theme'],
            ],
            'invalid color mode' => [
                [
                    'theme' => UiTheme::Neutral->value,
                    'color_mode' => 'auto',
                ],
                ['color_mode'],
            ],
        ];
    }

    public function testGuestCannotUpdateAppearance(): void
    {
        // Act
        $response = $this->putJson(route('auth.appearance.update'), [
            'theme' => UiTheme::Neutral->value,
            'color_mode' => UiColorMode::Light->value,
        ]);

        // Assert
        $response->assertUnauthorized();
    }
}
