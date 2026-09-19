<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\TransactionSplitRule;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePendingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('viewAny', [PendingTransaction::class, $ledger]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : null;
        $payerAccountExistsInLedger = Rule::exists('accounts', 'id')
            ->where('ledger_id', $ledgerId)
            ->whereIn('type', AccountType::publicTypes());
        $destinationAccountExistsInLedger = Rule::exists('accounts', 'id')
            ->where('ledger_id', $ledgerId)
            ->where('type', AccountType::SpaceExpense->value);
        $participantInLedger = Rule::exists('ledger_user', 'user_id')
            ->where('ledger_id', $ledgerId)
            ->whereNull('deleted_at');

        return [
            'payer_account_id' => ['required', 'integer', $payerAccountExistsInLedger],
            'destination_account_id' => ['required', 'integer', $destinationAccountExistsInLedger],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'split_rule' => ['required', new Enum(TransactionSplitRule::class)],
            'participants' => ['nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id', $participantInLedger],
            'participants.*.share' => ['nullable', 'numeric', 'min:0'],
            'rationale' => ['nullable', 'string', 'max:2000'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
