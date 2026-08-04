<?php

namespace App\Http\Requests;

use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');
        $user = $this->route('user');

        if (!$ledger instanceof Ledger || !$user instanceof User) {
            return false;
        }

        return $this->user()?->can('update', [FinancialProfile::class, $ledger, $user]) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'incomes' => ['required', 'array', 'min:1'],
            'incomes.*.description' => ['required', 'string', 'max:255'],
            'incomes.*.amount' => ['required', 'integer', 'min:0'],
            'deductions' => ['present', 'array'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incomes.required' => 'At least one income entry is required.',
            'incomes.min' => 'At least one income entry is required.',
            'incomes.*.description.required' => 'Income description is required.',
            'incomes.*.amount.required' => 'Income amount is required.',
            'incomes.*.amount.min' => 'Income amount cannot be negative.',
            'deductions.required' => 'Deductions array is required.',
            'deductions.*.description.required' => 'Deduction description is required.',
            'deductions.*.amount.required' => 'Deduction amount is required.',
            'deductions.*.amount.min' => 'Deduction amount cannot be negative.',
        ];
    }
}
