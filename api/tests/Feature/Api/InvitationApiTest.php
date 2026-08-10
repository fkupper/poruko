<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\InvitationController;
use App\Models\Invitation;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('invitations')]
#[CoversClass(InvitationController::class)]
class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function testAdminCanCreateInvitationWithOptionalEmail(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdmin();
        Sanctum::actingAs($admin);

        // Act
        $response = $this->postJson(route('ledgers.invitations.store', ['ledger' => $ledger->id]), [
            'email' => 'invitee@example.com',
            'expires_in_days' => 14,
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('invitation.email', 'invitee@example.com');

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
     * @return array{token: string, name: string, email: string, password: string}
     */
    private function generateAcceptPayload(string $token, string $email): array
    {
        return [
            'token' => $token,
            'name' => fake()->name(),
            'email' => $email,
            'password' => 'password123',
        ];
    }
}
