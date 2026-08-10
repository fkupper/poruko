<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
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

    protected function prepareForValidation(): void
    {
        if ($this->has('participants') && is_array($this->input('participants'))) {
            $participants = array_map(function (array $item): array {
                if (!isset($item['share']) && isset($item['share_amount'])) {
                    $item['share'] = (int) $item['share_amount'];
                }

                return $item;
            }, $this->input('participants'));

            $this->merge([
                'participants' => $participants,
            ]);
        }
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
            'type' => ['nullable', Rule::in([TransactionType::Manual->value])],
            'split_rule' => ['required', new Enum(TransactionSplitRule::class)],
            'participants' => $this->participantsRules(),
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id', $participantInLedger],
            'participants.*.share' => Rule::when(
                $this->input('split_rule') === TransactionSplitRule::Manual->value,
                ['required', 'numeric', 'gt:0'],
                ['nullable', 'numeric', 'min:0'],
            ),
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
            'amount.min' => 'The amount must be at least 1.',
            'date.required' => 'The transaction date is required.',
            'split_rule.required' => 'The split rule is required.',
            'participants.required' => 'Participants are required for the selected split rule.',
            'participants.min' => 'Manual split requires at least one participant.',
            'participants.size' => 'Individual split requires exactly one participant.',
            'participants.*.user_id.exists' => 'One or more selected participants do not exist or are not members of this ledger.',
        ];
    }
}
