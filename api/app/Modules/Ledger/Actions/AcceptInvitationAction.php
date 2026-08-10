<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Invitation;
use App\Models\LedgerUser;
use App\Models\User;
use App\Modules\Ledger\Data\AcceptInvitationData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$invitation) {
            throw new NotFoundHttpException('Invalid or expired invitation token.');
        }

        return DB::transaction(function () use ($data, $invitation): User {
            // Create the user
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
            ]);

            // Attach user to the ledger
            $invitation->ledger->users()->attach($user->id, ['role' => 'member']);

            // Assign Spatie role (using Spatie teams feature mapped to ledger_id)
            setPermissionsTeamId($invitation->ledger_id);
            $user->assignRole('Member');

            $ledgerUser = LedgerUser::query()
                ->where('ledger_id', $invitation->ledger_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Setup their personal accounts
            $this->ensureAccountAction->execute($ledgerUser);

            return $user;
        });
    }
}
