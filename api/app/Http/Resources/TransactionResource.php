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
            'credit_account_id' => $this->credit_account_id,
            'credit_account_name' => $this->whenLoaded('creditAccount', fn () => $this->creditAccount?->name),
            'debit_account_id' => $this->debit_account_id,
            'debit_account_name' => $this->whenLoaded('debitAccount', fn () => $this->debitAccount?->name),
            'amount' => $this->amount,
            'type' => $this->type,
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
