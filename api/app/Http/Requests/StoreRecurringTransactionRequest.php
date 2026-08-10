<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionSplitRule;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('create', [RecurringTransaction::class, $ledger]) === true;
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
            'payer_account_id' => ['required', 'integer', $accountExistsInLedger],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'split_rule' => ['required', new Enum(TransactionSplitRule::class)],
            'participants' => [
                Rule::when(
                    $this->input('split_rule') === TransactionSplitRule::Individual->value,
                    ['required', 'array', 'size:1'],
                    ['nullable', 'array'],
                ),
            ],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id', $participantInLedger],
            'participants.*.share' => Rule::when(
                $this->input('split_rule') === TransactionSplitRule::Manual->value,
                ['required', 'numeric', 'gt:0'],
                ['nullable', 'numeric', 'min:0'],
            ),
            'start_date' => ['required', 'date'],
            'frequency' => ['nullable', new Enum(RecurringFrequency::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_account_id.required' => 'The payer account is required.',
            'debit_account_id.required' => 'The debit account is required.',
            'amount.required' => 'The amount is required.',
            'amount.min' => 'The amount must be at least 1.',
            'start_date.required' => 'The start date is required.',
            'split_rule.required' => 'The split rule is required.',
        ];
    }
}
