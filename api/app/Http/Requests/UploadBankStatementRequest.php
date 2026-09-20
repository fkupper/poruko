<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UploadBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');
        $user = $this->user();

        return $ledger instanceof Ledger
            && $user instanceof User
            && $user->can('view', $ledger)
            && $user->can('ai_ingestion');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statement' => [
                'required',
                'file',
                'max:10240',
                'extensions:csv,txt,tsv',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statement.extensions' => 'Upload a CSV, TSV, or text bank statement.',
            'statement.max' => 'Bank statements must be 10 MB or smaller.',
        ];
    }
}
