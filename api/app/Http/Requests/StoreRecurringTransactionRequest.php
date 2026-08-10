<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
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
            'split_rule' => ['required', new Enum(TransactionSplitRule::class)],
            'participants' => $this->participantsRules(),
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
     * @return list<string>
     */
    private function participantsRules(): array
    {
        $splitRule = $this->input('split_rule');

        if ($splitRule === TransactionSplitRule::Individual->value) {
            return ['required', 'array', 'size:1'];
        }

        if ($splitRule === TransactionSplitRule::Manual->value) {
            return ['required', 'array', 'min:1'];
        }

        return ['nullable', 'array'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payer_account_id.required' => 'The payer account is required.',
            'payer_account_id.exists' => 'The selected payer account does not exist in this ledger.',
            'destination_account_id.required' => 'The destination account is required.',
            'destination_account_id.exists' => 'The selected destination account must be a Space Expense account in this ledger.',
            'amount.required' => 'The amount is required.',
            'amount.min' => 'The amount must be greater than zero.',
            'start_date.required' => 'The start date is required.',
            'split_rule.required' => 'The split rule is required.',
            'participants.required' => 'Participants are required for the selected split rule.',
            'participants.min' => 'Manual split requires at least one participant.',
            'participants.size' => 'Individual split requires exactly one participant.',
            'participants.*.user_id.exists' => 'One or more selected participants do not exist or are not members of this ledger.',
        ];
    }
}
