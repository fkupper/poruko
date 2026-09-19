<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankAccountMapping;
use App\Models\Ledger;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');
        $mapping = $this->route('bankAccountMapping');

        return $ledger instanceof Ledger
            && $mapping instanceof BankAccountMapping
            && $mapping->ledger_id === $ledger->id
            && $mapping->user_id === $this->user()?->id
            && $this->user()?->can('ai_ingestion') === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $mapping = $this->route('bankAccountMapping');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : 0;
        $userId = $this->user()?->id;

        return [
            'account_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($ledgerId, $mapping, $userId): void {
                    if ($value === null) {
                        return;
                    }

                    $account = Account::query()
                        ->whereKey($value)
                        ->where('ledger_id', $ledgerId)
                        ->first();

                    if (!$account instanceof Account || !$mapping instanceof BankAccountMapping) {
                        $fail('The selected account does not belong to this ledger.');

                        return;
                    }

                    $valid = $mapping->ownership_type === 'joint'
                        ? $account->type === AccountType::PoolAsset && $account->owner_id === null
                        : $account->type === AccountType::UserFunding && $account->owner_id === $userId;

                    if (!$valid) {
                        $fail(
                            $mapping->ownership_type === 'joint'
                                ? 'Joint bank accounts must map to a shared pool account.'
                                : 'Personal bank accounts must map to one of your personal funding accounts.',
                        );
                    }
                },
            ],
        ];
    }
}
