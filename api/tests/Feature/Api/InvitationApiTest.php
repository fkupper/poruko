<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\InvitationController;
use App\Models\Invitation;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('invitations')]
#[CoversClass(InvitationController::class)]
class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testOpenInvitationIsSingleUse(): void
    {
        // Arrange
        $invitation = $this->seedOpenInvitation();

        $firstPayload = $this->generateAcceptPayload($invitation->token, 'first@example.com');
        $secondPayload = $this->generateAcceptPayload($invitation->token, 'second@example.com');

        // Act
        $firstResponse = $this->postJson(route('invitations.accept'), $firstPayload);
        $secondResponse = $this->postJson(route('invitations.accept'), $secondPayload);

        // Assert
        $firstResponse->assertCreated();
        $secondResponse->assertNotFound();

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function testEmailBoundInvitationRejectsWrongEmail(): void
    {
        // Arrange
        $invitation = $this->seedEmailBoundInvitation('invitee@example.com');
        $payload = $this->generateAcceptPayload($invitation->token, 'wrong@example.com');

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseMissing('users', ['email' => 'wrong@example.com']);
    }

    public function testEmailBoundInvitationAcceptsMatchingEmailAndSetsAcceptedAt(): void
    {
        // Arrange
        $invitation = $this->seedEmailBoundInvitation('invitee@example.com');
        $payload = $this->generateAcceptPayload($invitation->token, 'invitee@example.com');

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('user.email', 'invitee@example.com');

        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseHas('users', ['email' => 'invitee@example.com']);
    }

    public function testNewUserMissingNameReturnsValidationError(): void
    {
        // Arrange
        $invitation = $this->seedOpenInvitation();
        $payload = $this->generateAcceptPayload($invitation->token, 'newbie@example.com');
        unset($payload['name']);

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseMissing('users', ['email' => 'newbie@example.com']);
    }

    public function testExistingUserWrongPasswordReturnsValidationError(): void
    {
        // Arrange
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);
        $invitation = $this->seedOpenInvitation();
        $payload = $this->generateAcceptPayload($invitation->token, $existing->email, name: null);
        $payload['password'] = 'wrong-password';

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertFalse($existing->fresh()->ledgers()->where('ledgers.id', $invitation->ledger_id)->exists());
    }

    public function testExistingUserCorrectPasswordJoinsLedgerAndGetsFullToken(): void
    {
        // Arrange
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);
        $invitation = $this->seedOpenInvitation();
        $payload = $this->generateAcceptPayload($invitation->token, $existing->email, name: null);

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('user.email', $existing->email)
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertTrue($existing->fresh()->ledgers()->where('ledgers.id', $invitation->ledger_id)->exists());

        $token = $response->json('token');
        $this->assertIsString($token);

        $accessToken = PersonalAccessToken::query()->find((int) explode('|', $token, 2)[0]);
        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->can('*'));

        setPermissionsTeamId($invitation->ledger_id);
        $this->assertTrue($existing->fresh()->hasRole('Member'));
    }

    public function testExistingUserAlreadyMemberReturnsValidationError(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdmin();
        $member = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        $invitation = Invitation::query()->create([
            'ledger_id' => $ledger->id,
            'created_by' => $admin->id,
            'token' => Str::random(32),
            'email' => null,
            'expires_at' => now()->addDays(7),
        ]);

        $payload = $this->generateAcceptPayload($invitation->token, $member->email, name: null);

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function testSoftDeletedMemberCanRejoinViaInvitation(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdmin();
        $member = User::factory()->create([
            'email' => 'returning@example.com',
            'password' => Hash::make('password123'),
        ]);
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $member->id)
            ->firstOrFail()
            ->delete();

        setPermissionsTeamId($ledger->id);
        $member->removeRole('Member');

        $invitation = Invitation::query()->create([
            'ledger_id' => $ledger->id,
            'created_by' => $admin->id,
            'token' => Str::random(32),
            'email' => null,
            'expires_at' => now()->addDays(7),
        ]);

        $payload = $this->generateAcceptPayload($invitation->token, $member->email, name: null);

        // Act
        $response = $this->postJson(route('invitations.accept'), $payload);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('user.email', $member->email);

        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertTrue($member->fresh()->ledgers()->where('ledgers.id', $ledger->id)->exists());
        $this->assertFalse(
            LedgerUser::onlyTrashed()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $member->id)
                ->exists(),
        );

        setPermissionsTeamId($ledger->id);
        $this->assertTrue($member->fresh()->hasRole('Member'));
    }

    public function testAdminCanCreateInvitationWithOptionalEmail(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdmin();
        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->postJson(route('ledgers.invitations.store', ['ledger' => $ledger->id]), [
            'email' => 'invitee@example.com',
            'expires_in_days' => 14,
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('invitation.email', 'invitee@example.com')
            ->assertJsonStructure([
                'message',
                'invitation' => [
                    'id',
                    'ledger_id',
                    'email',
                    'token',
                    'expires_at',
                    'accepted_at',
                    'created_by',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('invitations', [
            'ledger_id' => $ledger->id,
            'email' => 'invitee@example.com',
            'accepted_at' => null,
        ]);
    }

    /*
     * Seeders.
     */

    private function seedOpenInvitation(): Invitation
    {
        [$ledger, $admin] = $this->seedLedgerWithAdmin();

        return Invitation::query()->create([
            'ledger_id' => $ledger->id,
            'created_by' => $admin->id,
            'token' => Str::random(32),
            'email' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function seedEmailBoundInvitation(string $email): Invitation
    {
        [$ledger, $admin] = $this->seedLedgerWithAdmin();

        return Invitation::query()->create([
            'ledger_id' => $ledger->id,
            'created_by' => $admin->id,
            'token' => Str::random(32),
            'email' => $email,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * @return array{0: Ledger, 1: User}
     */
    private function seedLedgerWithAdmin(): array
    {
        $admin = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');

        return [$ledger, $admin];
    }

    /*
     * Generators.
     */

    /**
     * @return array{token: string, name?: string, email: string, password: string}
     */
    private function generateAcceptPayload(string $token, string $email, ?string $name = 'auto'): array
    {
        $payload = [
            'token' => $token,
            'email' => $email,
            'password' => 'password123',
        ];

        if ($name === 'auto') {
            $payload['name'] = fake()->name();
        } elseif ($name !== null) {
            $payload['name'] = $name;
        }

        return $payload;
    }
}
