<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLedgerSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        if (! $ledger instanceof Ledger || $this->user()?->can('update', $ledger) !== true) {
            return false;
        }

        $settlementFields = [
            'settlement_cutoff_day',
            'settlement_timezone',
            'settlement_cutoff_time',
            'settlement_auto_execute_enabled',
        ];

        if ($this->hasAny($settlementFields)) {
            return $this->user()?->can('manageSettlements', $ledger) === true;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'settlement_cutoff_day' => ['nullable', 'integer', 'between:1,31'],
            'settlement_timezone' => ['nullable', 'string', 'timezone'],
            'settlement_cutoff_time' => ['nullable', 'string', 'date_format:H:i:s'],
            'settlement_auto_execute_enabled' => ['nullable', 'boolean'],
        ];
    }
}
