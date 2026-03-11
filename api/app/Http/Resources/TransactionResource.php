<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'ledger_id' => $this->ledger_id,
            'payer_account_id' => $this->payer_account_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'split_rule' => $this->split_rule,
            'participants' => $this->participants,
            'description' => $this->description,
            'date' => $this->date?->format('Y-m-d'),
            'postings' => PostingResource::collection($this->whenLoaded('postings')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
