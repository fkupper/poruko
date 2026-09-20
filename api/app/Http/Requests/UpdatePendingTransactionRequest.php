<?php

namespace App\Http\Requests;

use App\Enums\TransactionSplitRule;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePendingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');
        $pendingTransaction = $this->route('pendingTransaction');

        return $ledger instanceof Ledger
            && $pendingTransaction instanceof PendingTransaction
            && $pendingTransaction->ledger_id === $ledger->id
            && $this->user()?->can('update', $pendingTransaction) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : 0;

        $accountRule = static function (string $attribute, mixed $value, Closure $fail) use ($ledgerId): void {
            if ($value === null) {
                return;
            }

            if (!\App\Models\Account::query()->whereKey($value)->where('ledger_id', $ledgerId)->exists()) {
                $fail('The selected account does not belong to this ledger.');
            }
        };

        return [
            'payer_account_id' => ['sometimes', 'nullable', 'integer', $accountRule],
            'destination_account_id' => ['sometimes', 'nullable', 'integer', $accountRule],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'split_rule' => ['sometimes', 'nullable', new Enum(TransactionSplitRule::class)],
            'participants' => ['sometimes', 'array'],
            'participants.*.user_id' => [
                'required_with:participants',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($ledgerId): void {
                    $isMember = \App\Models\LedgerUser::query()
                        ->where('ledger_id', $ledgerId)
                        ->where('user_id', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$isMember) {
                        $fail('Every participant must be an active ledger member.');
                    }
                },
            ],
            'participants.*.share' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
