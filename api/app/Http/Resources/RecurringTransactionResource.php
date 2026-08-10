<?php

namespace App\Http\Resources;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionSplitRule;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringTransaction
 */
class RecurringTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RecurringTransaction $model */
        $model = $this->resource;
        $validTo = $model->valid_to !== null ? $model->valid_to->format('Y-m-d') : null;
        $isActive = $validTo === null && !$model->trashed();

        return [
            'id' => $model->id,
            'ledger_id' => $model->ledger_id,
            'payer_account_id' => $model->payer_account_id,
            'payer_account_name' => $this->whenLoaded('payerAccount', fn () => $model->payerAccount?->name),
            'destination_account_id' => $model->destination_account_id,
            'destination_account_name' => $this->whenLoaded('destinationAccount', fn () => $model->destinationAccount?->name),
            'amount' => $model->amount,
            'description' => $model->description,
            'split_rule' => $model->split_rule instanceof TransactionSplitRule ? $model->split_rule->value : (string) $model->split_rule,
            'participants' => $model->participants,
            'frequency' => $model->frequency instanceof RecurringFrequency ? $model->frequency->value : (string) $model->frequency,
            'valid_from' => $model->valid_from->format('Y-m-d'),
            'valid_to' => $validTo,
            'is_active' => $isActive,
            'status' => $model->status,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];
    }
}
