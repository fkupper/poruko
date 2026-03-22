<?php

namespace App\Http\Requests;

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
        $accountExistsInLedger = Rule::exists('accounts', 'id')->where('ledger_id', $ledgerId);
        $participantInLedger = Rule::exists('ledger_user', 'user_id')->where('ledger_id', $ledgerId);

        return [
            'credit_account_id' => ['sometimes', 'required', 'integer', $accountExistsInLedger],
            'debit_account_id' => ['sometimes', 'required', 'integer', $accountExistsInLedger, 'different:credit_account_id'],
            'amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'split_rule' => ['sometimes', 'required', new Enum(TransactionSplitRule::class)],
            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id', $participantInLedger],
            'participants.*.share' => Rule::when(
                $this->input('split_rule') === TransactionSplitRule::Manual->value,
                ['required', 'numeric', 'gt:0'],
                ['nullable', 'numeric', 'min:0'],
            ),
            'start_date' => ['sometimes', 'required', 'date'],
            'frequency' => ['sometimes', 'nullable', new Enum(RecurringFrequency::class)],
        ];
    }
}
