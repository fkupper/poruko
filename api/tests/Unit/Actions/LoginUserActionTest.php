<?php

namespace Tests\Unit\Actions;

use App\Models\User;
use App\Modules\Auth\Actions\LoginUserAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
#[CoversClass(LoginUserAction::class)]
class LoginUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsUserAndTokenWhenCredentialsAreValid(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

        $action = app(LoginUserAction::class);
        $result = $action->execute([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
    }

    public function testReturnsNullWhenPasswordIsWrong(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

        $action = app(LoginUserAction::class);
        $result = $action->execute([
            'email' => 'alice@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenEmailDoesNotExist(): void
    {
        $action = app(LoginUserAction::class);
        $result = $action->execute([
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $this->assertNull($result);
    }
}
