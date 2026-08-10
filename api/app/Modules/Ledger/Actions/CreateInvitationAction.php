<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Invitation;
use App\Models\User;
use App\Modules\Ledger\Data\CreateInvitationData;
use Illuminate\Support\Str;

final readonly class CreateInvitationAction
{
    public function execute(User $actor, CreateInvitationData $data): Invitation
    {
        return Invitation::query()->create([
            'ledger_id' => $data->ledgerId,
            'created_by' => $actor->id,
            'token' => Str::random(32),
            'expires_at' => now()->addDays($data->expiresInDays),
        ]);
    }
}
