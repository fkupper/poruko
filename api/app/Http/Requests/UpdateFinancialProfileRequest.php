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

        if (! $ledger instanceof Ledger || ! $user instanceof User) {
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
            'deductions' => ['required', 'array'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
