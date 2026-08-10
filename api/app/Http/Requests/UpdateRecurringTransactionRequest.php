<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionSplitRule;
use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recurringTransaction = $this->route('recurringTransaction');

        return $this->user()?->can('update', $recurringTransaction) === true;
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
            'payer_account_id' => ['sometimes', 'required', 'integer', $payerAccountExistsInLedger],
            'destination_account_id' => ['sometimes', 'required', 'integer', $destinationAccountExistsInLedger],
            'amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'split_rule' => [
                'sometimes',
                'required',
                Rule::in([
                    TransactionSplitRule::Equal->value,
                    TransactionSplitRule::Proportional->value,
                ]),
            ],
            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id', $participantInLedger],
            'participants.*.share' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'required', 'date'],
            'frequency' => ['sometimes', 'nullable', new Enum(RecurringFrequency::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payer_account_id.exists' => 'The selected payer account does not exist in this ledger.',
            'destination_account_id.exists' => 'The selected destination account must be a Space Expense account in this ledger.',
            'amount.min' => 'The amount must be greater than zero.',
            'split_rule.in' => 'Recurring transactions only support equal or proportional split rules.',
            'participants.*.user_id.exists' => 'One or more selected participants do not exist or are not members of this ledger.',
        ];
    }
}
