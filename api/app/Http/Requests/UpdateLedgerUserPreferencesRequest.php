<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLedgerUserPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('view', $ledger) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : 0;
        $userId = $this->user()?->id;

        return [
            'default_payment_account_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($ledgerId, $userId): void {
                    if ($value === null) {
                        return;
                    }

                    $valid = Account::query()
                        ->whereKey($value)
                        ->where('ledger_id', $ledgerId)
                        ->where(function ($query) use ($userId): void {
                            $query
                                ->where(function ($owned) use ($userId): void {
                                    $owned
                                        ->where('type', AccountType::UserFunding)
                                        ->where('owner_id', $userId);
                                })
                                ->orWhere('type', AccountType::PoolAsset);
                        })
                        ->exists();

                    if (!$valid) {
                        $fail('The selected payment account is invalid.');
                    }
                },
            ],
            'default_expense_account_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($ledgerId): void {
                    if ($value === null) {
                        return;
                    }

                    $valid = Account::query()
                        ->whereKey($value)
                        ->where('ledger_id', $ledgerId)
                        ->where('type', AccountType::SpaceExpense)
                        ->exists();

                    if (!$valid) {
                        $fail('The selected expense account is invalid.');
                    }
                },
            ],
        ];
    }
}
