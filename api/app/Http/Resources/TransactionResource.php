<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlement_id' => $this->settlement_id,
            'ledger_id' => $this->ledger_id,
            'payer_account_id' => $this->payer_account_id,
            'payer_account_name' => $this->whenLoaded('payerAccount', fn () => $this->payerAccount?->name),
            'payer_account_owner_id' => $this->whenLoaded('payerAccount', fn () => $this->payerAccount?->owner_id),
            'destination_account_id' => $this->destination_account_id,
            'destination_account_name' => $this->whenLoaded('destinationAccount', fn () => $this->destinationAccount?->name),
            'amount' => $this->amount,
            'type' => $this->type,
            'source' => $this->source,
            'source_metadata' => $this->source_metadata,
            'source_recurring_transaction_id' => $this->source_recurring_transaction_id,
            'split_rule' => $this->split_rule,
            'participants' => $this->participants,
            'description' => $this->description,
            'date' => $this->date ? CarbonImmutable::parse($this->date)->format('Y-m-d') : null,
            'postings' => PostingResource::collection($this->whenLoaded('postings')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
