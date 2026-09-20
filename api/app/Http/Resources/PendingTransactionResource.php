<?php

namespace App\Http\Resources;

use App\Models\PendingTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PendingTransaction
 */
class PendingTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'proposed_by_user_id' => $this->user_id,
            'proposed_by_user_name' => $this->whenLoaded('proposer', fn () => $this->proposer->name),
            'payer_account_id' => $this->payer_account_id,
            'payer_account_name' => $this->whenLoaded('payerAccount', fn () => $this->payerAccount?->name),
            'destination_account_id' => $this->destination_account_id,
            'destination_account_name' => $this->whenLoaded('destinationAccount', fn () => $this->destinationAccount?->name),
            'raw_data' => $this->raw_data,
            'raw_description' => $this->raw_data['description'] ?? null,
            'suggested_description' => $this->suggested_description,
            'suggested_amount' => $this->suggested_amount,
            'suggested_split_rule' => $this->suggested_split_rule,
            'suggested_participants' => $this->suggested_participants,
            'date' => $this->date?->format('Y-m-d'),
            'source' => $this->source,
            'confidence' => $this->confidence,
            'rationale' => $this->rationale,
            'status' => $this->status,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'committed_transaction_id' => $this->committed_transaction_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
