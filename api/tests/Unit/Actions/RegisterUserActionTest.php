<?php

namespace Tests\Unit\Actions;

use App\Models\User;
use App\Modules\Auth\Actions\RegisterUserAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
#[CoversClass(RegisterUserAction::class)]
class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function testCreatesUserAndReturnsToken(): void
    {
        $action = app(RegisterUserAction::class);
        $result = $action->execute([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertSame('Alice', $result['user']->name);
        $this->assertSame('alice@example.com', $result['user']->email);
        $this->assertNotEmpty($result['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
        ]);

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('password123', $result['user']->password),
            'Password should be hashed',
        );
    }
}
