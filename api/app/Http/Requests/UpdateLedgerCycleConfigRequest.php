<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLedgerCycleConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('manageSettlements', $ledger) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settlement_timezone' => ['required', 'timezone:all'],
            'settlement_cutoff_day' => ['required', 'integer', 'between:1,31'],
            'settlement_cutoff_time' => ['required', 'date_format:H:i:s'],
            'settlement_auto_execute_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settlement_timezone.required' => 'The settlement timezone is required.',
            'settlement_timezone.timezone' => 'The settlement timezone must be a valid timezone.',
            'settlement_cutoff_day.required' => 'The cutoff day is required.',
            'settlement_cutoff_day.between' => 'The cutoff day must be between 1 and 31.',
            'settlement_cutoff_time.required' => 'The cutoff time is required.',
            'settlement_cutoff_time.date_format' => 'The cutoff time must be in HH:mm:ss format.',
            'settlement_auto_execute_enabled.required' => 'Auto-execute setting is required.',
        ];
    }
}
