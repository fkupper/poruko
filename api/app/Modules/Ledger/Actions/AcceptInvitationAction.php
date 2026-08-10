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
            $user = User::create([
                'name' => $data->name,
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

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
