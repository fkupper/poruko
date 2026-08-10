<?php

namespace App\Http\Resources;

use App\Modules\Ledger\Data\LedgerMemberListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LedgerMemberListItem $member */
        $member = $this->resource;

        return [
            'id' => $member->id,
            'name' => $member->name,
            'shareable_income' => $member->shareable_income,
            'email' => $member->email,
            'role' => $member->role,
            'is_active' => $member->is_active,
        ];
    }
}
