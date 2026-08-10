<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::exists('accounts', 'id')
                    ->where('ledger_id', $ledgerId)
                    ->where('type', AccountType::UserFunding->value)
                    ->where('owner_id', $userId),
            ],
            'default_expense_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('ledger_id', $ledgerId)
                    ->where('type', AccountType::SpaceExpense->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_payment_account_id.exists' => 'The selected payment account is invalid.',
            'default_expense_account_id.exists' => 'The selected expense account is invalid.',
        ];
    }
}
