<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Invitation;
use App\Models\LedgerUser;
use App\Models\User;
use App\Modules\Ledger\Data\AcceptInvitationData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AcceptInvitationAction
{
    public function __construct(
        private EnsureMainPersonalAccountForLedgerMemberAction $ensureAccountAction
    ) {}

    public function execute(AcceptInvitationData $data): User
    {
        $invitation = Invitation::query()
            ->where('token', $data->token)
            ->whereNull('accepted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$invitation) {
            throw new NotFoundHttpException('Invalid or expired invitation token.');
        }

        if ($invitation->email !== null && strcasecmp($invitation->email, $data->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['This invitation is bound to a different email address.'],
            ]);
        }

        return DB::transaction(function () use ($data, $invitation): User {
            $existing = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($data->email)])
                ->first();

            if ($existing !== null) {
                $user = $this->acceptAsExistingUser($existing, $invitation, $data);
            } else {
                $user = $this->acceptAsNewUser($invitation, $data);
            }

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }

    private function acceptAsExistingUser(User $user, Invitation $invitation, AcceptInvitationData $data): User
    {
        if (!Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $activeMembership = LedgerUser::query()
            ->where('ledger_id', $invitation->ledger_id)
            ->where('user_id', $user->id)
            ->first();

        if ($activeMembership !== null) {
            throw ValidationException::withMessages([
                'email' => ['You are already a member of this ledger.'],
            ]);
        }

        $trashedMembership = LedgerUser::onlyTrashed()
            ->where('ledger_id', $invitation->ledger_id)
            ->where('user_id', $user->id)
            ->first();

        if ($trashedMembership !== null) {
            $trashedMembership->restore();

            setPermissionsTeamId($invitation->ledger_id);

            if (!$user->hasRole('Member')) {
                $user->assignRole('Member');
            }

            $this->ensureAccountAction->execute($trashedMembership->fresh());

            return $user;
        }

        $invitation->ledger->users()->attach($user->id, ['role' => 'member']);

        setPermissionsTeamId($invitation->ledger_id);
        $user->assignRole('Member');

        $ledgerUser = LedgerUser::query()
            ->where('ledger_id', $invitation->ledger_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->ensureAccountAction->execute($ledgerUser);

        return $user;
    }

    private function acceptAsNewUser(Invitation $invitation, AcceptInvitationData $data): User
    {
        $name = trim((string) $data->name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['A name is required.'],
            ]);
        }

        $user = User::create([
            'name' => $name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        $invitation->ledger->users()->attach($user->id, ['role' => 'member']);

        setPermissionsTeamId($invitation->ledger_id);
        $user->assignRole('Member');

        $ledgerUser = LedgerUser::query()
            ->where('ledger_id', $invitation->ledger_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->ensureAccountAction->execute($ledgerUser);

        return $user;
    }
}
