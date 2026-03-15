<?php

namespace App\Http\Requests;

use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('create', [Transaction::class, $ledger]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : null;
        $accountExistsInLedger = Rule::exists('accounts', 'id')->where('ledger_id', $ledgerId);

        $participantInLedger = Rule::exists('ledger_user', 'user_id')->where('ledger_id', $ledgerId);

        return [
            'credit_account_id' => ['required', 'integer', $accountExistsInLedger],
            'debit_account_id' => ['required', 'integer', $accountExistsInLedger, 'different:credit_account_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['nullable', Rule::in([TransactionType::Manual->value])],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_account_id.required' => 'The credit account is required.',
            'credit_account_id.exists' => 'The selected credit account does not exist in this ledger.',
            'debit_account_id.required' => 'The debit account is required.',
            'debit_account_id.exists' => 'The selected debit account does not exist in this ledger.',
            'amount.required' => 'The amount is required.',
            'amount.min' => 'The amount must be at least 1.',
            'date.required' => 'The transaction date is required.',
            'split_rule.required' => 'The split rule is required.',
            'participants.required' => 'Participants are required when using individual split.',
            'participants.*.user_id.exists' => 'One or more selected participants do not exist or are not members of this ledger.',
        ];
    }
}
