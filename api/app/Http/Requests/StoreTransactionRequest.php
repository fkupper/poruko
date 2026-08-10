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
        $accountExistsInLedger = Rule::exists('accounts', 'id')
            ->where('ledger_id', $ledgerId)
            ->whereIn('type', AccountType::publicTypes());

        $participantInLedger = Rule::exists('ledger_user', 'user_id')->where('ledger_id', $ledgerId);

        return [
            'payer_account_id' => ['required', 'integer', $accountExistsInLedger],
            'destination_account_id' => ['required', 'integer', $accountExistsInLedger],
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
            'payer_account_id.required' => 'The payer account is required.',
            'payer_account_id.exists' => 'The selected payer account does not exist in this ledger.',
            'amount.required' => 'The amount is required.',
            'amount.min' => 'The amount must be at least 1.',
            'date.required' => 'The transaction date is required.',
            'split_rule.required' => 'The split rule is required.',
            'participants.required' => 'Participants are required when using individual split.',
            'participants.*.user_id.exists' => 'One or more selected participants do not exist or are not members of this ledger.',
        ];
    }
}
